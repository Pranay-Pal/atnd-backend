<?php

namespace App\Http\Middleware;

use App\Models\Device;
use App\Services\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function __construct(private TenantManager $tenantManager) {}

    public function handle(Request $request, Closure $next): Response
    {
        $device = $request->user();

        if (!$device instanceof Device) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Eagerly load tenant if not already loaded
        $tenant = $device->tenant;

        if (!$tenant) {
            return response()->json(['message' => 'Tenant not found.'], 403);
        }

        $this->tenantManager->set($tenant);

        // Touch device activity timestamp (no model events, cheap update)
        $device->updateQuietly(['last_seen_at' => now()]);

        // Make device and tenant available to controllers
        $request->merge(['_device' => $device, '_tenant' => $tenant]);

        return $next($request);
    }
}
