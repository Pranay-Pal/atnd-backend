<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantEntityType;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TenantController extends Controller
{
    /**
     * Display a listing of the tenants.
     */
    public function index()
    {
        $tenants = Tenant::withCount('users')->latest()->get();
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
        return response()->json($tenant);
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
}
