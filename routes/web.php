<?php

use Illuminate\Support\Facades\Route;

Route::get('/setup', function () {
    try {
        // 1. Verify database connection
        \DB::connection()->getPdo();

        // 2. Run outstanding migrations automatically
        \Artisan::call('migrate', ['--force' => true]);
        
        // 3. Provision Super Admin ONLY if it doesn't already exist
        $adminProvisoned = false;
        if (!\App\Models\User::where('role', 'admin')->exists()) {
            \Artisan::call('db:seed', ['--class' => 'Database\Seeders\AdminSeeder', '--force' => true]);
            $adminProvisoned = true;
        }

        return response()->json([
            'success' => true,
            'message' => 'Database initialization completed successfully.',
            'migrations_output' => \Artisan::output(),
            'admin_provisioned' => $adminProvisoned,
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error'   => $e->getMessage()
        ], 500);
    }
});

Route::get('/{any?}', function () {
    return view('admin');
})->where('any', '.*');
