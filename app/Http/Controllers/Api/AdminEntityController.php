<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TenantEntity;
use App\Models\TenantEntityType;
use App\Services\TenantManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminEntityController extends Controller
{
    public function __construct(private TenantManager $tenantManager) {}

    // ──────────────────────────────────────────────
    // Entity Types  (e.g. "Class", "Department")
    // ──────────────────────────────────────────────

    /**
     * GET /api/admin/entity-types
     * Query Params: with_entities (boolean)
     */
    public function indexTypes(Request $request): JsonResponse
    {
        $query = TenantEntityType::where('tenant_id', $this->tenantManager->id())
            ->withCount('entities')
            ->orderBy('name');

        if ($request->boolean('with_entities')) {
            $query->with('entities');
        }

        return response()->json($query->get());
    }

    /**
     * POST /api/admin/entity-types
     * Body: { name: string, is_required?: bool }
     */
    public function storeType(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:100'],
            'is_required' => ['nullable', 'boolean'],
        ]);

        $type = TenantEntityType::create([
            'tenant_id'   => $this->tenantManager->id(),
            'name'        => $data['name'],
            'is_required' => $data['is_required'] ?? false,
        ]);

        return response()->json($type, 201);
    }

    /**
     * DELETE /api/admin/entity-types/{typeId}
     * Cascades to entities and entity_user assignments.
     */
    public function destroyType(int $typeId): JsonResponse
    {
        TenantEntityType::where('tenant_id', $this->tenantManager->id())
            ->findOrFail($typeId)
            ->delete();

        return response()->json(null, 204);
    }

    // ──────────────────────────────────────────────
    // Entity Values  (e.g. "Grade 10", "Engineering")
    // ──────────────────────────────────────────────

    /**
     * GET /api/admin/entity-types/{typeId}/entities
     */
    public function indexEntities(int $typeId): JsonResponse
    {
        $type = TenantEntityType::where('tenant_id', $this->tenantManager->id())
            ->findOrFail($typeId);

        $entities = $type->entities()
            ->orderBy('name')
            ->get();

        // Inject the users_count manually by querying the native JSON taxonomy mappings
        foreach ($entities as $entity) {
            $entity->users_count = \App\Models\User::whereRaw(
                "JSON_SEARCH(taxonomy_properties, 'one', ?) IS NOT NULL", 
                [(string) $entity->id]
            )->count();
        }

        return response()->json($entities);
    }

    /**
     * POST /api/admin/entity-types/{typeId}/entities
     * Body: { name: string }
     */
    public function storeEntity(Request $request, int $typeId): JsonResponse
    {
        $type = TenantEntityType::where('tenant_id', $this->tenantManager->id())
            ->findOrFail($typeId);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
        ]);

        $entity = $type->entities()->create(['name' => $data['name']]);

        return response()->json($entity, 201);
    }

    /**
     * DELETE /api/admin/entities/{id}
     * Cascades to entity_user assignments.
     */
    public function destroyEntity(int $id): JsonResponse
    {
        // Verify entity belongs to this tenant via its type
        TenantEntity::whereHas('type', fn ($q) =>
            $q->where('tenant_id', $this->tenantManager->id())
        )->findOrFail($id)->delete();

        return response()->json(null, 204);
    }
}
