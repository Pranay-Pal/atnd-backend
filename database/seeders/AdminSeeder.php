<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Admin;
use App\Models\Tenant;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Find or create a global 'system' tenant for the super admin
        // Note: We need a tenant for the user due to the foreign key, 
        // but the super admin doesn't really belong to a single tenant.
        // Let's create a 'System' tenant.
        $systemTenant = Tenant::firstOrCreate(
            ['domain' => 'genskytech.com'],
            ['name' => 'Genskytech System', 'industry' => 'software']
        );

        Admin::firstOrCreate(
            ['email' => 'admin'],
            [
                'name' => 'Super Admin',
                'password' => bcrypt('ADMIN'),
                'role' => 'admin',
                'tenant_id' => $systemTenant->id,
            ]
        );
    }
}
