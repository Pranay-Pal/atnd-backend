<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TenantManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    public function __construct(private TenantManager $tenantManager) {}

    /**
     * GET /api/admin/users
     * Query params: search, entity_id, entity_type_id, per_page
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search'         => ['nullable', 'string', 'max:100'],
            'entity_id'      => ['nullable', 'integer'],
            'entity_type_id' => ['nullable', 'integer'],
            'filters'        => ['nullable', 'array'],
            'filters.*'      => ['nullable', 'array'],
            'filters.*.*'    => ['integer'],
            'per_page'       => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);
        $query = User::with(['faceEmbedding'])->orderBy('name');
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('member_uid', 'like', "%{$search}%");
            });
        }

        if ($request->filled('entity_id')) {
            $query->whereRaw("JSON_SEARCH(taxonomy_properties, 'one', ?) IS NOT NULL", [(string) $request->integer('entity_id')]);
        }

        if ($request->filled('entity_type_id')) {
            // Check if there is ANY key stored in the taxonomy properties matching this Type ID
            $query->whereRaw("JSON_EXTRACT(taxonomy_properties, '$.\"" . $request->integer('entity_type_id') . "\"') IS NOT NULL");
        }

        if ($request->filled('filters')) {
            $query->filterByEntities($request->input('filters'));
        }

        $users = $query->paginate($request->integer('per_page', 25));

        return response()->json($users->through(fn ($user) => $this->formatUser($user)));
    }

    /**
     * POST /api/admin/users
     * Body: { name, member_uid? }
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'                => ['required', 'string', 'max:255'],
            'member_uid'          => ['nullable', 'string', 'max:100', Rule::unique('users')->where('tenant_id', $this->tenantManager->id())],
            'entity_ids'          => ['nullable', 'array'],
            'entity_ids.*'        => ['integer', 'exists:tenant_entities,id'],
        ]);

        $user = User::create([
            'tenant_id'           => $this->tenantManager->id(),
            'name'                => $data['name'],
            'member_uid'          => $data['member_uid'] ?? null,
        ]);

        if (!empty($data['entity_ids'])) {
            $jsonMap = [];
            $entities = \App\Models\TenantEntity::whereIn('id', $data['entity_ids'])->get();
            foreach ($entities as $entity) {
                // If the user checked two options from the same Type (e.g. Class 10 AND Class 11), 
                // the array key naturally overwrites standardizing the 1-to-1 rule.
                $jsonMap[(string) $entity->tenant_entity_type_id] = $entity->id;
            }
            $user->taxonomy_properties = $jsonMap;
            $user->save();
        }
        return response()->json($this->formatUser($user), 201);    }

    /**
     * GET /api/admin/users/{id}
     */
    public function show(int $id): JsonResponse
    {        $user = User::with(['faceEmbedding'])->findOrFail($id);
        return response()->json($this->formatUser($user));
    }

    /**
     * PUT /api/admin/users/{id}
     * Body: any subset of { name, member_uid }
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user     = User::findOrFail($id);
        $tenantId = $this->tenantManager->id();

        $data = $request->validate([
            'name'                => ['sometimes', 'string', 'max:255'],
            'member_uid'          => ['nullable', 'string', 'max:100', Rule::unique('users')->where('tenant_id', $tenantId)->ignore($user->id)],
        ]);

        $user->update($data);
        return response()->json($this->formatUser($user));    }

    /**
     * DELETE /api/admin/users/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        User::findOrFail($id)->delete();

        return response()->json(null, 204);
    }

    /**
     * POST /api/admin/users/{id}/entities
     * Body: { entity_ids: [int, ...] }
     * Replaces all current entity assignments for this user.
     */
    public function syncEntities(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'entity_ids'   => ['required', 'array'],
            'entity_ids.*' => ['integer', 'exists:tenant_entities,id'],
        ]);

        $user = User::findOrFail($id);
        
        $jsonMap = [];
        $entities = \App\Models\TenantEntity::whereIn('id', $data['entity_ids'])->get();
        foreach ($entities as $entity) {
            $jsonMap[(string) $entity->tenant_entity_type_id] = $entity->id;
        }

        $user->taxonomy_properties = empty($jsonMap) ? null : $jsonMap;
        $user->save();

        return response()->json($this->formatUser($user));
    }

    private function formatUser(User $user): array
    {
        return [
            'id'                  => $user->id,
            'name'                => $user->name,
            'member_uid'          => $user->member_uid,
            'has_face_enrolled'   => $user->relationLoaded('faceEmbedding')
                                        ? $user->faceEmbedding !== null
                                        : null,
            // Format entities out of the JSON properties 
            // The JSON is built like: {"1": 5, "2": 12}
            'entities'            => $this->hydrateTaxonomies($user),
            'created_at'          => $user->created_at?->toIso8601String(),
        ];
    }

    private function hydrateTaxonomies(User $user): array
    {
        $props = $user->taxonomy_properties;
        if (empty($props)) {
            return [];
        }

        // We need the type and value names for the frontend presentation.
        // E.g. {"1": 5} -> [{"id": 5, "type": "Class", "value": "10"}]
        $entityIds = array_values($props);
        $entities = \App\Models\TenantEntity::with('type')->whereIn('id', $entityIds)->get();

        return $entities->map(fn ($e) => [
            'id'    => $e->id,
            'type'  => $e->type?->name,
            'value' => $e->name,
        ])->values()->toArray();
    }
}
