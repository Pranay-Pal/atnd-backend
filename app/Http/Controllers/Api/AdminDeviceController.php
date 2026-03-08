<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Services\TenantManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminDeviceController extends Controller
{
    public function __construct(private TenantManager $tenantManager) {}

    /**
     * GET /api/admin/devices
     */
    public function index(): JsonResponse
    {
        $devices = Device::where('tenant_id', $this->tenantManager->id())
            ->orderBy('name')
            ->get();

        return response()->json($devices);
    }

    /**
     * POST /api/admin/devices
     * Body: { name: string }
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $apiKey = 'sk_' . Str::random(40);

        $device = Device::create([
            'tenant_id' => $this->tenantManager->id(),
            'name'      => $data['name'],
            'api_key'   => $apiKey,
        ]);

        // Make the api_key visible in this single response so the client can show it
        $device->makeVisible(['api_key']);

        return response()->json($device, 201);
    }

    /**
     * DELETE /api/admin/devices/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $device = Device::where('tenant_id', $this->tenantManager->id())
            ->findOrFail($id);
            
        // Revoke active sessions before deleting
        $device->tokens()->delete();
        $device->delete();

        return response()->json(null, 204);
    }
}
