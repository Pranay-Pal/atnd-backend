<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\TenantEntity;
use App\Models\TenantEntityType;
use App\Models\User;
use Illuminate\Console\Command;

class TestTaxonomyFilters extends Command
{
    protected $signature = 'test:taxonomy-filters';
    protected $description = 'Verifies the FilterableByEntities advanced AND/OR queries.';

    public function handle()
    {
        // 1. Setup Tenant
        $tenant = Tenant::firstOrCreate(
            ['domain' => 'test.org'], 
            ['name' => 'Test School', 'industry' => 'education']
        );

        // 2. Setup Types & Entities
        $typeClass = TenantEntityType::firstOrCreate(['tenant_id' => $tenant->id, 'name' => 'Class']);
        $typeSec = TenantEntityType::firstOrCreate(['tenant_id' => $tenant->id, 'name' => 'Section']);

        $class10 = TenantEntity::firstOrCreate(['tenant_entity_type_id' => $typeClass->id, 'name' => '10']);
        $class9  = TenantEntity::firstOrCreate(['tenant_entity_type_id' => $typeClass->id, 'name' => '9']);
        $secA    = TenantEntity::firstOrCreate(['tenant_entity_type_id' => $typeSec->id, 'name' => 'A']);
        $secB    = TenantEntity::firstOrCreate(['tenant_entity_type_id' => $typeSec->id, 'name' => 'B']);

        // 3. Create test users
        $user1 = User::withoutGlobalScopes()->updateOrCreate(
            ['name' => 'John (10-A)'],
            ['tenant_id' => $tenant->id]
        );
        $user1->entities()->sync([$class10->id, $secA->id]);

        $user2 = User::withoutGlobalScopes()->updateOrCreate(
            ['name' => 'Jane (10-B)'],
            ['tenant_id' => $tenant->id]
        );
        $user2->entities()->sync([$class10->id, $secB->id]);

        $user3 = User::withoutGlobalScopes()->updateOrCreate(
            ['name' => 'Bob (9-A)'],
            ['tenant_id' => $tenant->id]
        );
        $user3->entities()->sync([$class9->id, $secA->id]);


        // --- Test 1: Empty Array (Entire School) ---
        $count = User::withoutGlobalScopes()->where('tenant_id', $tenant->id)->filterByEntities([])->count();
        $this->info("Test 1 (Empty Filters): Expected 3, Got {$count}");

        // --- Test 2: Single AND Group (Class 10 AND Sec A) ---
        $filters = [
            [$class10->id, $secA->id] 
        ];
        $count = User::withoutGlobalScopes()->where('tenant_id', $tenant->id)->filterByEntities($filters)->count();
        $names = User::withoutGlobalScopes()->where('tenant_id', $tenant->id)->filterByEntities($filters)->pluck('name')->implode(', ');
        $this->info("Test 2 (Class 10 AND Sec A): Expected 1 (John), Got {$count} ({$names})");

        // --- Test 3: Multiple OR Groups ([Class 10 AND Sec B] OR [Class 9 AND Sec A]) ---
        $filters = [
            [$class10->id, $secB->id], 
            [$class9->id, $secA->id]
        ];
        $count = User::withoutGlobalScopes()->where('tenant_id', $tenant->id)->filterByEntities($filters)->count();
        $names = User::withoutGlobalScopes()->where('tenant_id', $tenant->id)->filterByEntities($filters)->pluck('name')->implode(', ');
        $this->info("Test 3 (10B OR 9A): Expected 2 (Jane, Bob), Got {$count} ({$names})");

        // --- Test 4: Single ID (All of Sec A, regardless of class) ---
        $filters = [
            [$secA->id]
        ];
        $count = User::withoutGlobalScopes()->where('tenant_id', $tenant->id)->filterByEntities($filters)->count();
        $names = User::withoutGlobalScopes()->where('tenant_id', $tenant->id)->filterByEntities($filters)->pluck('name')->implode(', ');
        $this->info("Test 4 (All Sec A): Expected 2 (John, Bob), Got {$count} ({$names})");
    }
}
