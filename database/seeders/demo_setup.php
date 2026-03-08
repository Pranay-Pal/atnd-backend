<?php

$t = \App\Models\Tenant::first();
if (!$t) {
    $t = \App\Models\Tenant::create([
        'name' => 'Test School',
        'domain' => 'client.genskytech.com',
        'industry' => 'education'
    ]);
    
    // Auto-seed required types
    \App\Models\TenantEntityType::create(['tenant_id' => $t->id, 'name' => 'Class', 'is_required' => true]);
    \App\Models\TenantEntityType::create(['tenant_id' => $t->id, 'name' => 'Section', 'is_required' => true]);
}

$d = \App\Models\Device::firstOrCreate([
    'tenant_id' => $t->id,
    'api_key' => 'demokey-12345'
], [
    'name' => 'Main Building Entrance'
]);

echo "\n=======================\n";
echo "LOGIN CREDENTIALS\n";
echo "Server URL : http://<your-ip>:8000\n";
echo "Domain     : {$t->domain}\n";
echo "API Key    : {$d->api_key}\n";
echo "=======================\n";
