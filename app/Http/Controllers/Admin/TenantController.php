<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantEntityType;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TenantController extends Controller
{
    /**
     * Display a listing of the tenants.
     */
    public function index()
    {
        $tenants = Tenant::withCount('users')->latest()->get();
        $tenants->transform(function ($t) {
            $t->settings = $this->formatSettings($t->settings);
            return $t;
        });
        return response()->json($tenants);
    }

    /**
     * Store a newly created tenant and its default admin.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'domain' => 'required|string|max:255|unique:tenants,domain',
            'industry' => 'required|string|max:50',
            // Default admin fields
            'admin_name' => 'required|string|max:255',
            'admin_email' => 'required|email|unique:admins,email',
            'admin_password' => 'required|string|min:8',
        ]);

        // 1. Create Tenant
        $tenant = Tenant::create([
            'name' => $validated['name'],
            'domain' => $validated['domain'],
            'industry' => $validated['industry'],
            'settings' => ['primary_color' => '#1d4ed8'], // default setting
        ]);

        // 2. Create the Organization Admin for this tenant
        // 2. Create the Organization Admin for this tenant
        Admin::create([
            'tenant_id' => $tenant->id,
            'name' => $validated['admin_name'],
            'email' => $validated['admin_email'],
            'password' => Hash::make($validated['admin_password']),
            'role' => 'organisation',
        ]);

        // 3. Auto-seed taxonomies based on industry
        $defaultTypes = [];
        if ($validated['industry'] === 'education') {
            $defaultTypes = ['Class', 'Section'];
        } elseif ($validated['industry'] === 'fitness') {
            $defaultTypes = ['Membership Tier'];
        } elseif ($validated['industry'] === 'corporate') {
            $defaultTypes = ['Department', 'Role'];
        }

        foreach ($defaultTypes as $type) {
            TenantEntityType::create([
                'tenant_id' => $tenant->id,
                'name' => $type,
                'is_required' => true,
            ]);
        }

        return response()->json([
            'message' => 'Organization created successfully',
            'tenant' => $tenant
        ], 201);
    }

    /**
     * Display the specified tenant.
     */
    public function show(string $id)
    {
        $tenant = Tenant::with('entityTypes')->findOrFail($id);
        $tenant->settings = $this->formatSettings($tenant->settings);
        return response()->json($tenant);
    }

    /**
     * Update the specified tenant.
     */
    public function update(Request $request, string $id)
    {
        $tenant = Tenant::findOrFail($id);

        $validated = $request->validate([
            'name'          => 'sometimes|string|max:255',
            'domain'        => 'sometimes|string|max:255|unique:tenants,domain,' . $id,
            'industry'      => 'sometimes|string|max:50',
            'primary_color' => 'sometimes|string|regex:/^#([A-Fa-f0-9]{6})$/',
            'logo'          => 'sometimes|image|mimes:jpeg,png,jpg,svg|max:2048',
        ]);

        if (isset($validated['name']))   $tenant->name = $validated['name'];
        if (isset($validated['domain'])) $tenant->domain = $validated['domain'];
        if (isset($validated['industry'])) $tenant->industry = $validated['industry'];

        $settings = $tenant->settings ?? [];

        if (isset($validated['primary_color'])) {
            $settings['primary_color'] = $validated['primary_color'];
        }

        if ($request->hasFile('logo')) {
            if (isset($settings['logo_url'])) {
                $oldPath = str_replace(url('/api/branding-image?path='), '', $settings['logo_url']);
                $oldPath = str_replace('/api/branding-image?path=', '', $oldPath);
                \Illuminate\Support\Facades\Storage::disk('public')->delete(urldecode(ltrim($oldPath, '/')));
            }
            $path = $request->file('logo')->store('branding', 'public');
            $settings['logo_url'] = '/api/branding-image?path=' . urlencode($path);
        }

        $tenant->settings = $settings;
        $tenant->save();

        $tenant->settings = $this->formatSettings($tenant->settings);

        return response()->json([
            'message' => 'Organization updated successfully',
            'tenant' => $tenant
        ]);
    }

    /**
     * Remove the specified tenant from storage.
     */
    public function destroy(string $id)
    {
        $tenant = Tenant::findOrFail($id);
        $tenant->delete();
        return response()->json(['message' => 'Organization deleted successfully']);
    }

    private function formatSettings(array|null $settings): array
    {
        $settings = $settings ?? [];
        if (isset($settings['logo_url'])) {
            $url = $settings['logo_url'];
            if (str_starts_with($url, '/api/branding-image?path=')) {
                $settings['logo_url'] = request()->getSchemeAndHttpHost() . $url;
            } elseif (preg_match('/^https?:\/\/[^\/]+(\/api\/branding-image\?path=.*)$/', $url, $matches)) {
                $settings['logo_url'] = request()->getSchemeAndHttpHost() . $matches[1];
            }
        }
        return $settings;
    }
}
