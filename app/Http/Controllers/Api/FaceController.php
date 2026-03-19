<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FaceEmbedding;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FaceController extends Controller
{
    private const EMBEDDING_DIM = 512;
    private const EMBEDDING_BYTES = self::EMBEDDING_DIM * 4;
    private const DEFAULT_MATCH_THRESHOLD = 0.4;
    private const DUPLICATE_FACE_THRESHOLD = 0.6;

    /**
     * GET /api/face/embeddings?updated_after={ISO8601}
     * Download tenant face embeddings for local device cache (incremental or full).
     *
     * Returns: [{
     *   user_id, name, profile_picture_url,
     *   embedding: base64, model_version, updated_at,
     *   entities: [{ type, value }, ...]
     * }]
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'updated_after' => ['nullable', 'date'],
            'filters'       => ['nullable', 'array'],
            'filters.*'     => ['nullable', 'array'],
            'filters.*.*'   => ['integer'],
        ]);

        $query = FaceEmbedding::with(['user']);

        if ($request->filled('updated_after')) {
            $query->where('face_embeddings.updated_at', '>=', $request->input('updated_after'));
        }

        if ($request->filled('filters')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->withoutGlobalScopes()->filterByEntities($request->input('filters'));
            });
        }

        $embeddings = $query->get()->map(function (FaceEmbedding $e) {
            $user = $e->getRelation('user');

            $entities = [];
            if ($user && !empty($user->taxonomy_properties)) {
                $entityIds = array_values($user->taxonomy_properties);
                // Hydrate entities for Face payload (Optional cache hit opportunity here)
                $entities = \App\Models\TenantEntity::with('type')
                    ->whereIn('id', $entityIds)
                    ->get()
                    ->map(fn ($entity) => [
                        'type'  => $entity->type?->name,
                        'value' => $entity->name,
                    ])->values()->all();
            }

            return [
                'user_id'             => $e->user_id,
                'name'                => $user?->name,
                'member_uid'          => $user?->member_uid,
                'embedding'           => base64_encode($e->getRawOriginal('embedding')),
                'model_version'       => $e->model_version,
                'updated_at'          => $e->updated_at?->toIso8601String(),
                'entities'            => $entities,
            ];
        });

        return response()->json($embeddings);
    }

    /**
     * GET /api/face/users
     * Get all users for the tenant (to support enrollment on device).
     */
    public function users(Request $request): JsonResponse
    {
        $request->validate([
            'filters'     => ['nullable', 'array'],
            'filters.*'   => ['nullable', 'array'],
            'filters.*.*' => ['integer'],
        ]);

        $tenantId = $request->user()->tenant_id;

        $query = User::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->with(['faceEmbedding']);

        if ($request->filled('filters')) {
            $query->filterByEntities($request->input('filters'));
        }

        $users = $query->get()->map(function ($user) {
                return [
                    'id'                  => $user->id,
                    'name'                => $user->name,
                    'member_uid'          => $user->member_uid,
                    'has_embedding'       => $user->faceEmbedding !== null,
                    'entities'            => $this->hydrateTaxonomies($user),
                ];
            });

        return response()->json($users);
    }

    private function hydrateTaxonomies(User $user): array
    {
        $props = $user->taxonomy_properties;
        if (empty($props)) {
            return [];
        }

        $entityIds = array_values($props);
        $entities = \App\Models\TenantEntity::with('type')->whereIn('id', $entityIds)->get();

        return $entities->map(fn ($e) => [
            'type'  => $e->type?->name,
            'value' => $e->name,
        ])->values()->toArray();
    }

    /**
     * POST /api/face/enroll
     * Store or update a face embedding for a user.
     * Body: { user_id: int, embedding: base64string, model_version?: string }
     *
     * The embedding must be a base64-encoded 2048-byte buffer
     * (512 × float32, L2-normalized) produced by w600k_mbf.onnx.
     */
    public function enroll(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id'       => ['required', 'integer', 'exists:users,id'],
            'embedding'     => ['required', 'string'],
            'model_version' => ['sometimes', 'string', 'max:50'],
        ]);

        $decoded = $this->decodeAndNormalizeEmbedding($data['embedding']);
        if ($decoded === null) {
            return response()->json([
                'message' => 'Invalid embedding: expected 2048-byte base64 (512 × float32).',
            ], 422);
        }
        $normalizedVector = $decoded['vector'];
        $normalizedBytes = $decoded['bytes'];

        $device = $request->user();
        $tenantId = $device->tenant_id;
        $modelVersion = $data['model_version'] ?? 'w600k_mbf';

        // Verify user belongs to this tenant
        $user = User::withoutGlobalScopes()->find($data['user_id']);
        if (!$user || $user->tenant_id !== $tenantId) {
            return response()->json(['message' => 'User not found in tenant.'], 404);
        }

        $bestDuplicateUserId = null;
        $bestDuplicateSimilarity = -1.0;
        $duplicates = FaceEmbedding::where('tenant_id', $tenantId)
            ->where('user_id', '!=', $data['user_id'])
            ->where('model_version', $modelVersion)
            ->select('user_id', 'embedding')
            ->get();

        foreach ($duplicates as $candidate) {
            $candidateVec = $this->unpackEmbedding($candidate->getRawOriginal('embedding'));
            if ($candidateVec === null) {
                continue;
            }

            $sim = $this->cosineSimilarity($normalizedVector, $candidateVec);
            if ($sim > $bestDuplicateSimilarity) {
                $bestDuplicateSimilarity = $sim;
                $bestDuplicateUserId = $candidate->user_id;
            }
        }

        if ($bestDuplicateUserId !== null && $bestDuplicateSimilarity > self::DUPLICATE_FACE_THRESHOLD) {
            return response()->json([
                'code' => 'ERR_DUPLICATE_FACE',
                'message' => 'This face is already enrolled to another user.',
                'matched_user_id' => $bestDuplicateUserId,
                'similarity' => round($bestDuplicateSimilarity, 6),
            ], 409);
        }

        FaceEmbedding::updateOrCreate(
            ['tenant_id' => $tenantId, 'user_id' => $data['user_id']],
            [
                'embedding'     => $normalizedBytes,
                'model_version' => $modelVersion,
                'device_id'     => $device->id,
            ]
        );

        return response()->json(['message' => 'Enrolled.'], 201);
    }

    /**
     * POST /api/face/match
     * Server-side cosine similarity fallback matching.
     * Body: { embedding: base64string }
     * Returns: { user_id, similarity, matched: bool } or { matched: false }
     */
    public function match(Request $request): JsonResponse
    {
        $data = $request->validate([
            'embedding' => ['required', 'string'],
            'model_version' => ['sometimes', 'string', 'max:50'],
        ]);

        $decoded = $this->decodeAndNormalizeEmbedding($data['embedding']);
        if ($decoded === null) {
            return response()->json([
                'message' => 'Invalid embedding: expected 2048-byte base64 (512 × float32).',
            ], 422);
        }
        $queryVec = $decoded['vector'];

        $device   = $request->user();
        $tenantId = $device->tenant_id;

        $candidatesQuery = FaceEmbedding::where('tenant_id', $tenantId)
            ->select('user_id', 'embedding')
            ->orderBy('id');
        if (!empty($data['model_version'])) {
            $candidatesQuery->where('model_version', $data['model_version']);
        }
        $candidates = $candidatesQuery->get();

        if ($candidates->isEmpty()) {
            return response()->json(['matched' => false, 'user_id' => null, 'similarity' => 0]);
        }

        $bestSim    = -1.0;
        $bestUserId = null;

        foreach ($candidates as $candidate) {
            $vec = $this->unpackEmbedding($candidate->getRawOriginal('embedding'));
            if ($vec === null) {
                continue;
            }
            $sim = $this->cosineSimilarity($queryVec, $vec);

            if ($sim > $bestSim) {
                $bestSim    = $sim;
                $bestUserId = $candidate->user_id;
            }
        }

        $threshold = $this->resolveMatchThreshold($tenantId);
        $matched   = $bestUserId !== null && $bestSim > $threshold;

        return response()->json([
            'matched'    => $matched,
            'user_id'    => $matched ? $bestUserId : null,
            'similarity' => round($bestSim, 6),
        ]);
    }

    private function resolveMatchThreshold(int $tenantId): float
    {
        $settings = Tenant::query()->find($tenantId)?->settings;

        if (is_array($settings)) {
            $candidate = $settings['match_threshold'] ?? null;
            if (is_numeric($candidate)) {
                $threshold = (float) $candidate;
                if ($threshold > 0 && $threshold < 1) {
                    return $threshold;
                }
            }
        }

        return self::DEFAULT_MATCH_THRESHOLD;
    }

    private function decodeAndNormalizeEmbedding(string $base64): ?array
    {
        $rawBytes = base64_decode($base64, true);
        if ($rawBytes === false || strlen($rawBytes) !== self::EMBEDDING_BYTES) {
            return null;
        }

        $vector = $this->unpackEmbedding($rawBytes);
        if ($vector === null) {
            return null;
        }

        $norm = $this->vectorNorm($vector);
        if ($norm < 1e-10) {
            return null;
        }

        $normalized = [];
        foreach ($vector as $value) {
            $normalized[] = $value / $norm;
        }

        return [
            'vector' => $normalized,
            'bytes' => pack('g*', ...$normalized),
        ];
    }

    private function unpackEmbedding(string $rawBytes): ?array
    {
        if (strlen($rawBytes) !== self::EMBEDDING_BYTES) {
            return null;
        }

        $vector = array_values(unpack('g' . self::EMBEDDING_DIM, $rawBytes));
        if (count($vector) !== self::EMBEDDING_DIM) {
            return null;
        }

        foreach ($vector as $value) {
            if (!is_finite((float) $value)) {
                return null;
            }
        }

        return array_map(static fn ($v) => (float) $v, $vector);
    }

    private function cosineSimilarity(array $a, array $b): float
    {
        if (count($a) !== self::EMBEDDING_DIM || count($b) !== self::EMBEDDING_DIM) {
            return -1.0;
        }

        $dot = 0.0;
        $na = 0.0;
        $nb = 0.0;
        for ($i = 0; $i < self::EMBEDDING_DIM; $i++) {
            $av = (float) $a[$i];
            $bv = (float) $b[$i];
            $dot += $av * $bv;
            $na += $av * $av;
            $nb += $bv * $bv;
        }

        $den = sqrt($na) * sqrt($nb);
        if ($den < 1e-10) {
            return -1.0;
        }

        return $dot / $den;
    }

    private function vectorNorm(array $v): float
    {
        $sum = 0.0;
        foreach ($v as $value) {
            $value = (float) $value;
            $sum += $value * $value;
        }
        return sqrt($sum);
    }
}
