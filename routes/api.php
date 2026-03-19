<?php

use App\Http\Controllers\Api\AdminAuthController;
use App\Http\Controllers\Api\AdminDeviceController;
use App\Http\Controllers\Api\AdminEntityController;
use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\DeviceAuthController;
use App\Http\Controllers\Api\FaceController;
use App\Http\Controllers\Api\OrganisationBrandingController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Admin\TenantController;
use App\Http\Middleware\RequireSuperAdmin;
use App\Http\Middleware\ResolveOrganisationTenant;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Support\Facades\Route;

// ── Public endpoints ──────────────────────────────────────────────────────────

Route::get('/ping', fn () => response()->json(['status' => 'ok']));

// Image serving route to bypass Hostinger CDN /storage/ blocking
Route::get('/branding-image', function (Illuminate\Http\Request $request) {
    $path = (string) $request->query('path', '');
    if ($path === '') {
        abort(404);
    }

    $decoded = ltrim(str_replace("\0", '', urldecode($path)), '/\\');
    $baseDir = realpath(storage_path('app/public'));
    if ($baseDir === false) {
        abort(404);
    }

    $fullPath = realpath($baseDir . DIRECTORY_SEPARATOR . $decoded);
    $isInsideStorage = $fullPath !== false
        && str_starts_with($fullPath, $baseDir . DIRECTORY_SEPARATOR);
    if (!$isInsideStorage || !is_file($fullPath)) {
        abort(404);
    }

    $mimeType = \Illuminate\Support\Facades\File::mimeType($fullPath);
    return response()->file($fullPath, [
        'Content-Type' => $mimeType,
        'Cache-Control' => 'public, max-age=86400'
    ]);
});

// Phase 1: tablet provisioning — domain + api_key → token + branding + entity_types
Route::post('/device/register', [DeviceAuthController::class, 'register']);

// Admin portal login — email + password → admin Bearer token
Route::post('/admin/login', [AdminAuthController::class, 'login']);

// ── Authenticated device endpoints (Sanctum Device token) ────────────────────

Route::middleware(['auth:sanctum', ResolveTenant::class])->group(function () {

    // Auth
    Route::post('/auth/logout', [DeviceAuthController::class, 'logout']);

    // Face recognition
    // GET  /face/embeddings?updated_after={ISO8601}  — incremental sync
    Route::get('/face/users',       [FaceController::class, 'users']);
    Route::get('/face/embeddings',  [FaceController::class, 'index']);
    Route::post('/face/enroll',     [FaceController::class, 'enroll']);
    Route::post('/face/match',      [FaceController::class, 'match']);

    // Attendance — POST /sync uploads records, GET /sync downloads (bidirectional)
    Route::post('/attendance/check-in',  [AttendanceController::class, 'checkIn']);
    Route::post('/attendance/check-out', [AttendanceController::class, 'checkOut']);
    Route::post('/attendance/sync',      [AttendanceController::class, 'sync']);
    Route::get('/attendance/sync',       [AttendanceController::class, 'download']);

    // Reports (also accessible from device)
    Route::get('/reports/attendance', [ReportController::class, 'attendance']);

    // Branding sync for Device
    Route::get('/device/branding', [OrganisationBrandingController::class, 'showPublic']);
});

// ── Authenticated Super Admin endpoints (Sanctum User token, role = admin) ──────────

Route::middleware(['auth:sanctum', RequireSuperAdmin::class])->prefix('super-admin')->group(function () {
    Route::get('/tenants',                  [TenantController::class, 'index']);
    Route::post('/tenants',                 [TenantController::class, 'store']);
    Route::get('/tenants/{id}',             [TenantController::class, 'show']);
    Route::post('/tenants/{id}',            [TenantController::class, 'update']); // Using POST for file uploads
    Route::delete('/tenants/{id}',          [TenantController::class, 'destroy']);
});

// ── Authenticated admin endpoints (Sanctum User token, role = organisation) ──────────

Route::middleware(['auth:sanctum', ResolveOrganisationTenant::class])->prefix('admin')->group(function () {

    // Auth
    Route::post('/logout', [AdminAuthController::class, 'logout']);
    Route::post('/password', [AdminAuthController::class, 'changePassword']);

    // User management
    Route::get('/users',                    [AdminUserController::class, 'index']);
    Route::post('/users',                   [AdminUserController::class, 'store']);
    Route::get('/users/{id}',               [AdminUserController::class, 'show']);
    Route::put('/users/{id}',               [AdminUserController::class, 'update']);
    Route::delete('/users/{id}',            [AdminUserController::class, 'destroy']);
    Route::post('/users/{id}/entities',     [AdminUserController::class, 'syncEntities']);

    // Taxonomy management
    Route::get('/entity-types',                                  [AdminEntityController::class, 'indexTypes']);
    Route::post('/entity-types',                                 [AdminEntityController::class, 'storeType']);
    Route::delete('/entity-types/{typeId}',                      [AdminEntityController::class, 'destroyType']);
    Route::get('/entity-types/{typeId}/entities',                [AdminEntityController::class, 'indexEntities']);
    Route::post('/entity-types/{typeId}/entities',               [AdminEntityController::class, 'storeEntity']);
    Route::delete('/entities/{id}',                              [AdminEntityController::class, 'destroyEntity']);

    // Device management
    Route::get('/devices',                  [AdminDeviceController::class, 'index']);
    Route::post('/devices',                 [AdminDeviceController::class, 'store']);
    Route::delete('/devices/{id}',          [AdminDeviceController::class, 'destroy']);

    // Branding management
    Route::get('/branding',                 [OrganisationBrandingController::class, 'index']);
    Route::post('/branding',                [OrganisationBrandingController::class, 'update']); // Use POST to allow file uploads over PUT proxy if needed, or stick to standard. Laravel handles multipart on POST better.

    // Reports (admin-scoped)
    Route::get('/reports/attendance', [ReportController::class, 'attendance']);
});
