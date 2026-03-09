<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\FaceEmbedding;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FaceController extends Controller
{
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

        $rawBytes = base64_decode($data['embedding'], strict: true);

        if ($rawBytes === false || strlen($rawBytes) !== 2048) {
            return response()->json([
                'message' => 'Invalid embedding: expected 2048-byte base64 (512 × float32).',
            ], 422);
        }

        $device = $request->user();
        $tenantId = $device->tenant_id;

        // Verify user belongs to this tenant
        $user = User::withoutGlobalScopes()->find($data['user_id']);
        if (!$user || $user->tenant_id !== $tenantId) {
            return response()->json(['message' => 'User not found in tenant.'], 404);
        }

        FaceEmbedding::updateOrCreate(
            ['tenant_id' => $tenantId, 'user_id' => $data['user_id']],
            [
                'embedding'     => $rawBytes,
                'model_version' => $data['model_version'] ?? 'w600k_mbf',
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
        ]);

        $queryBytes = base64_decode($data['embedding'], strict: true);

        if ($queryBytes === false || strlen($queryBytes) !== 2048) {
            return response()->json([
                'message' => 'Invalid embedding: expected 2048-byte base64 (512 × float32).',
            ], 422);
        }

        // Unpack query embedding to float array
        $queryVec = array_values(unpack('f512', $queryBytes));

        $device   = $request->user();
        $tenantId = $device->tenant_id;

        $candidates = FaceEmbedding::where('tenant_id', $tenantId)
            ->select('user_id', 'embedding')
            ->get();

        if ($candidates->isEmpty()) {
            return response()->json(['matched' => false, 'user_id' => null, 'similarity' => 0]);
        }

        $bestSim    = -1.0;
        $bestUserId = null;

        foreach ($candidates as $candidate) {
            $raw = $candidate->getRawOriginal('embedding');
            $vec = array_values(unpack('f512', $raw));
            $sim = $this->dotProduct($queryVec, $vec);

            if ($sim > $bestSim) {
                $bestSim    = $sim;
                $bestUserId = $candidate->user_id;
            }
        }

        $threshold = 0.4;
        $matched   = $bestSim >= $threshold;

        return response()->json([
            'matched'    => $matched,
            'user_id'    => $matched ? $bestUserId : null,
            'similarity' => round($bestSim, 6),
        ]);
    }

    /**
     * Dot product of two equal-length float arrays.
     * For L2-normalized vectors this equals cosine similarity.
     *
     * @param float[] $a
     * @param float[] $b
     */
    private function dotProduct(array $a, array $b): float
    {
        $sum = 0.0;
        $len = count($a);
        for ($i = 0; $i < $len; $i++) {
            $sum += $a[$i] * $b[$i];
        }
        return $sum;
    }
}
