<?php

namespace Database\Seeders;

use App\Models\Device;
use App\Models\Tenant;
use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AdminSeeder::class,
        ]);

        if (!app()->environment('production')) {
            // Sample tenant
            $tenant = Tenant::create([
                'name'     => 'Demo Company',
                'domain'   => 'demo.local',
                'industry' => 'corporate',
                'settings' => ['theme' => 'default'],
            ]);

            // Sample device (api_key printed to console for easy testing)
            $apiKey = Str::random(40);

            $device = Device::create([
                'tenant_id' => $tenant->id,
                'name'      => 'Front Door Terminal',
                'api_key'   => $apiKey,
            ]);

            // Sample organisation manager
            Admin::create([
                'tenant_id'   => $tenant->id,
                'name'        => 'Jane Doe',
                'email'       => 'jane@demo.local',
                'password'    => Hash::make('password'),
                'role'        => 'organisation',
            ]);

            $this->call([
                TaxonomySeeder::class,
            ]);

            $this->command->info("Development seed completed successfully!");
            $this->command->info("Tenant   : {$tenant->name} (id={$tenant->id})");
            $this->command->info("Device   : {$device->name} (id={$device->id})");
            $this->command->info("API Key  : {$apiKey}");
            $this->command->info("Use this API key to call POST /api/auth/login");
        } else {
            $this->command->info("Production environment detected. Only core Admin provisioned.");
        }
    }
}
