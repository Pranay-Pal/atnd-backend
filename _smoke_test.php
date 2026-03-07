<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$d = App\Models\Device::first();
echo "Device: {$d->name} (tenant_id={$d->tenant_id})\n";

$token = $d->createToken('smoke-test')->plainTextToken;
echo "Token created: " . substr($token, 0, 25) . "...\n";

$found = Laravel\Sanctum\PersonalAccessToken::findToken($token)?->tokenable;
echo "Resolved model: " . get_class($found) . " id={$found->id}\n";
echo "Tenant: " . $found->tenant->name . "\n";
echo "All OK!\n";
