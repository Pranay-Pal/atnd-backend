<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\DB;

echo "--- Strict JSON Filter Test ---\n";

$firstMapped = User::withoutGlobalScopes()->whereNotNull('taxonomy_properties')->first();
if (!$firstMapped) {
    die("No mapped users to test.\n");
}

$payload = $firstMapped->taxonomy_properties;
echo "Found User #{$firstMapped->id}. Their JSON is: " . json_encode($payload) . "\n";

// Grab the first value in their JSON map
$keys = array_keys($payload);
$typeId = $keys[0];
$entityId = $payload[$typeId];

echo "Will now test searching the database for ANY user who has Entity Value: {$entityId}\n";

$query = User::withoutGlobalScopes()
    ->whereRaw("JSON_SEARCH(taxonomy_properties, 'one', ?) IS NOT NULL", [(string) $entityId]);

echo "Executing SQL: " . $query->toSql() . " with bindings: " . json_encode($query->getBindings()) . "\n";

$results = $query->get();
echo "Found {$results->count()} matching users!\n";

if ($results->count() > 0) {
    echo "SUCCESS: The JSON search engine works perfectly without Pivot Tables!\n";
} else {
    echo "ERROR: The JSON search query failed to find the user we literally just pulled it from.\n";
}
