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
            'per_page'       => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $query = User::with(['entities.type'])->orderBy('name');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('employee_id', 'like', "%{$search}%");
            });
        }

        if ($request->filled('entity_id')) {
            $query->whereHas('entities', fn ($q) =>
                $q->where('tenant_entities.id', $request->integer('entity_id'))
            );
        }

        if ($request->filled('entity_type_id')) {
            $query->whereHas('entities.type', fn ($q) =>
                $q->where('tenant_entity_types.id', $request->integer('entity_type_id'))
            );
        }

        $users = $query->paginate($request->integer('per_page', 25));

        return response()->json($users->through(fn ($user) => $this->formatUser($user)));
    }

    /**
     * POST /api/admin/users
     * Body: { name, email, employee_id?, profile_picture_url?, role?, password? }
     */
    public function store(Request $request): JsonResponse
    {
        $tenantId = $this->tenantManager->id();

        $data = $request->validate([
            'name'                => ['required', 'string', 'max:255'],
            'email'               => ['required', 'email', Rule::unique('users')->where('tenant_id', $tenantId)],
            'employee_id'         => ['nullable', 'string', 'max:100'],
            'profile_picture_url' => ['nullable', 'url', 'max:2048'],
            'role'                => ['nullable', 'in:admin,user'],
            'password'            => ['nullable', 'string', 'min:8'],
        ]);

        $user = User::withoutGlobalScopes()->create([
            'tenant_id'           => $tenantId,
            'name'                => $data['name'],
            'email'               => $data['email'],
            'employee_id'         => $data['employee_id'] ?? null,
            'profile_picture_url' => $data['profile_picture_url'] ?? null,
            'role'                => $data['role'] ?? 'user',
            'password'            => Hash::make($data['password'] ?? str()->random(16)),
        ]);

        return response()->json($this->formatUser($user->load('entities.type')), 201);
    }

    /**
     * GET /api/admin/users/{id}
     */
    public function show(int $id): JsonResponse
    {
        $user = User::with(['entities.type', 'faceEmbedding'])->findOrFail($id);

        return response()->json($this->formatUser($user));
    }

    /**
     * PUT /api/admin/users/{id}
     * Body: any subset of { name, email, employee_id, profile_picture_url, role, password }
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user     = User::findOrFail($id);
        $tenantId = $this->tenantManager->id();

        $data = $request->validate([
            'name'                => ['sometimes', 'string', 'max:255'],
            'email'               => ['sometimes', 'email', Rule::unique('users')->where('tenant_id', $tenantId)->ignore($user->id)],
            'employee_id'         => ['nullable', 'string', 'max:100'],
            'profile_picture_url' => ['nullable', 'url', 'max:2048'],
            'role'                => ['nullable', 'in:admin,user'],
            'password'            => ['nullable', 'string', 'min:8'],
        ]);

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $user->update($data);

        return response()->json($this->formatUser($user->load('entities.type')));
    }

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
        $user->entities()->sync($data['entity_ids']);

        return response()->json($this->formatUser($user->load('entities.type')));
    }

    private function formatUser(User $user): array
    {
        return [
            'id'                  => $user->id,
            'name'                => $user->name,
            'email'               => $user->email,
            'employee_id'         => $user->employee_id,
            'profile_picture_url' => $user->profile_picture_url,
            'role'                => $user->role,
            'has_face_enrolled'   => $user->relationLoaded('faceEmbedding')
                                        ? $user->faceEmbedding !== null
                                        : null,
            'entities'            => $user->relationLoaded('entities')
                ? $user->entities->map(fn ($e) => [
                    'id'    => $e->id,
                    'type'  => $e->type?->name,
                    'value' => $e->name,
                ])->values()
                : [],
            'created_at'          => $user->created_at?->toIso8601String(),
        ];
    }
}
