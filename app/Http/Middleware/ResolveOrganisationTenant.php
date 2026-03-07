<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guard for the React Admin Portal routes.
 * Ensures the authenticated actor is a User (not a Device) with role = 'admin',
 * then sets the TenantManager so global scopes filter by their tenant.
 */
class ResolveOrganisationTenant
{
    public function __construct(private TenantManager $tenantManager) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user instanceof User) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ($user->role !== 'organisation') {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $tenant = $user->tenant;

        if (!$tenant) {
            return response()->json(['message' => 'Tenant not found.'], 403);
        }

        $this->tenantManager->set($tenant);

        return $next($request);
    }
}
