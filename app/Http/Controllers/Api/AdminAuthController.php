<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminAuthController extends Controller
{
    /**
     * POST /api/admin/login
     * Body: { email?: string, password: string }
     * - If email is provided: standard email+password login for organisation/platform admins
     * - If email is omitted: master admin password-only login (first admin user)
     * Returns: { token, user }
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => ['nullable', 'email'],
            'password' => ['required', 'string'],
        ]);

        if ($request->filled('email')) {
            $user = Admin::withoutGlobalScopes()
                ->where('email', $request->email)
                ->whereIn('role', ['admin', 'organisation'])
                ->first();
        } else {
            // Master admin: password-only login to the first admin user
            $user = Admin::withoutGlobalScopes()
                ->where('role', 'admin')
                ->orderBy('id')
                ->first();
        }

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        // Revoke existing admin tokens before issuing a fresh one
        $user->tokens()->where('name', 'admin-token')->delete();

        $token = $user->createToken('admin-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'        => $user->id,
                'name'      => $user->name,
                'email'     => $user->email,
                'role'      => $user->role,
                'tenant_id' => $user->tenant_id,
            ],
        ]);
    }

    /**
     * POST /api/admin/logout
     * Revokes the current Bearer token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }
}
