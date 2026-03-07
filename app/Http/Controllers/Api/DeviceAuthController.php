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
            ->orderBy('name')
            ->get(['id', 'name', 'is_required']);

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
     * POST /api/auth/login
     * Body: { api_key: string }
     * Returns: { token: string, device: {...} }
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'api_key' => ['required', 'string'],
        ]);

        $device = Device::where('api_key', $request->api_key)->first();

        if (!$device) {
            return response()->json(['message' => 'Invalid API key.'], 401);
        }

        // Revoke all existing tokens for this device before issuing a new one
        $device->tokens()->delete();

        $token = $device->createToken('device-token')->plainTextToken;

        $device->updateQuietly(['last_seen_at' => now()]);

        return response()->json([
            'token'  => $token,
            'device' => [
                'id'        => $device->id,
                'name'      => $device->name,
                'tenant_id' => $device->tenant_id,
            ],
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
