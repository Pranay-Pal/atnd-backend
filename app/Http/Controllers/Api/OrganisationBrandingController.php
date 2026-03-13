<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\TenantManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class OrganisationBrandingController extends Controller
{
    /**
     * GET /api/admin/branding
     * Display current organization branding info.
     */
    public function index(TenantManager $tenantManager): JsonResponse
    {
        $tenant = $tenantManager->get();
        return response()->json([
            'name'     => $tenant->name,
            'settings' => $this->formatSettings($tenant->settings),
        ]);
    }

    /**
     * POST /api/admin/branding
     * Update name, primary color, or logo.
     */
    public function update(Request $request, TenantManager $tenantManager): JsonResponse
    {
        $tenant = $tenantManager->get();

        $validated = $request->validate([
            'name'          => 'sometimes|string|max:255',
            'primary_color' => 'sometimes|string|regex:/^#([A-Fa-f0-9]{6})$/',
            'logo'          => 'sometimes|image|mimes:jpeg,png,jpg,svg|max:2048',
        ]);

        if (isset($validated['name'])) {
            $tenant->name = $validated['name'];
        }

        $settings = $tenant->settings ?? [];

        if (isset($validated['primary_color'])) {
            $settings['primary_color'] = $validated['primary_color'];
        }

        if ($request->hasFile('logo')) {
            // Delete old logo if exists
            if (isset($settings['logo_url'])) {
                $oldPath = str_replace(['url(\'/storage/\')', '/storage/'], '', $settings['logo_url']);
                // Also handle cases where logo_url might be absolute
                $oldPath = str_replace(url('storage/'), '', $oldPath);
                Storage::disk('public')->delete(ltrim($oldPath, '/'));
            }

            $path = $request->file('logo')->store('branding', 'public');
            $settings['logo_url'] = '/storage/' . $path;
        }

        $tenant->settings = $settings;
        $tenant->save();

        return response()->json([
            'message'  => 'Branding updated successfully.',
            'tenant'   => [
                'name'     => $tenant->name,
                'settings' => $this->formatSettings($tenant->settings),
            ],
        ]);
    }

    /**
     * GET /api/device/branding
     * Simple endpoint for kiosks to pull latest branding info.
     */
    public function showPublic(Request $request): JsonResponse
    {
        // This is called by devices with their token
        // ResolveTenant middleware already set the tenant manager if we were in a group,
        // but since this might be called outside the main sync, we handle it explicitly.
        
        $tenant = $request->user()->tenant;
        
        if (!$tenant) {
            return response()->json(['message' => 'Tenant context not found.'], 403);
        }

        return response()->json([
            'id'       => $tenant->id,
            'name'     => $tenant->name,
            'settings' => $this->formatSettings($tenant->settings),
        ]);
    }

    private function formatSettings(array|null $settings): array
    {
        $settings = $settings ?? [];
        if (isset($settings['logo_url'])) {
            $url = $settings['logo_url'];
            if (str_starts_with($url, '/storage/')) {
                $settings['logo_url'] = request()->getSchemeAndHttpHost() . $url;
            } elseif (preg_match('/^https?:\/\/[^\/]+(\/storage\/.*)$/', $url, $matches)) {
                $settings['logo_url'] = request()->getSchemeAndHttpHost() . $matches[1];
            }
        }
        return $settings;
    }
}
