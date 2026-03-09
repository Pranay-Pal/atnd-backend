<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\Api\AdminUserController;
use Illuminate\Http\Request;

$request = Request::create('/api/admin/users', 'GET');

// Ensure tenant scope maps
class MockTenantManager extends \App\Services\TenantManager {
    public function id(): int { return 4; } // Tenant ID 4
}
$app->instance(\App\Services\TenantManager::class, new MockTenantManager());

$controller = app(AdminUserController::class);
$response = $controller->index($request);
$data = $response->getData(true);

if (empty($data['data'])) {
    die("No users returned!\n");
}

$firstUser = $data['data'][0];
echo "Testing User: {$firstUser['name']}\n";
echo "Their `entities` array is:\n";
print_r($firstUser['entities']);
echo "\nTest Complete.\n";
