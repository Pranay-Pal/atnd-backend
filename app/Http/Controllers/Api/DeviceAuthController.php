<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\Tenant;
use App\Models\TenantEntityType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class DeviceAuthController extends Controller
{
    /**
     * POST /api/device/register
     * Body: { domain: string, api_key: string }
     * Returns: { token, device, tenant (with settings), entity_types }
     * This is the workflow Phase-1 provisioning endpoint.
     */
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'domain'  => ['required', 'string'],
            'api_key' => ['required', 'string'],
        ]);

        $tenant = Tenant::where('domain', $request->domain)->first();

        if (!$tenant) {
            return response()->json(['message' => 'Tenant not found.'], 404);
        }

        $device = Device::where('tenant_id', $tenant->id)
            ->where('api_key', $request->api_key)
            ->first();

        if (!$device) {
            return response()->json(['message' => 'Invalid API key.'], 401);
        }

        $device->tokens()->delete();
        $token = $device->createToken('device-token')->plainTextToken;
        $device->updateQuietly(['last_seen_at' => now()]);

        $entityTypes = TenantEntityType::where('tenant_id', $tenant->id)
            ->with(['entities:id,tenant_entity_type_id,name'])
            ->orderBy('name')
            ->get()
            ->map(function ($type) {
                return [
                    'id'          => $type->id,
                    'name'        => $type->name,
                    'is_required' => $type->is_required,
                    'entities'    => $type->entities->map(fn ($e) => [
                        'id'   => $e->id,
                        'name' => $e->name,
                    ]),
                ];
            });

        return response()->json([
            'token'  => $token,
            'device' => [
                'id'        => $device->id,
                'name'      => $device->name,
                'tenant_id' => $device->tenant_id,
            ],
            'tenant' => [
                'id'       => $tenant->id,
                'name'     => $tenant->name,
                'settings' => $tenant->settings ?? (object) [],
            ],
            'entity_types' => $entityTypes,
        ]);
    }

    /**
     * POST /api/auth/logout
     * Revokes the current Bearer token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }
}
