<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Tenant;
use App\Models\TenantEntityType;
use App\Models\TenantEntity;
use App\Models\User;

class TaxonomySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Create a School Tenant
        $school = Tenant::firstOrCreate(
            ['domain' => 'acmeschool.genskytech.com'],
            ['name' => 'Acme School', 'industry' => 'education', 'settings' => ['primary_color' => '#1d4ed8']]
        );

        // Create School Taxonomies
        $classType = TenantEntityType::firstOrCreate(['tenant_id' => $school->id, 'name' => 'Class'], ['is_required' => true]);
        $sectionType = TenantEntityType::firstOrCreate(['tenant_id' => $school->id, 'name' => 'Section'], ['is_required' => true]);

        // Create School Entities
        $grade10 = TenantEntity::firstOrCreate(['tenant_entity_type_id' => $classType->id, 'name' => 'Grade 10']);
        $sectionA = TenantEntity::firstOrCreate(['tenant_entity_type_id' => $sectionType->id, 'name' => 'Section A']);

        // Create a Student & Attach to Taxonomy
        $student = User::firstOrCreate(
            ['email' => 'jane@acmeschool.org'],
            ['tenant_id' => $school->id, 'name' => 'Jane Student', 'password' => bcrypt('password'), 'role' => 'organisation']
        );
        $student->entities()->syncWithoutDetaching([$grade10->id, $sectionA->id]);


        // 2. Create a Gym Tenant
        $gym = Tenant::firstOrCreate(
            ['domain' => 'ironworks.genskytech.com'],
            ['name' => 'IronWorks Gym', 'industry' => 'fitness', 'settings' => ['primary_color' => '#dc2626']]
        );

        // Create Gym Taxonomies
        $membershipType = TenantEntityType::firstOrCreate(['tenant_id' => $gym->id, 'name' => 'Membership Tier'], ['is_required' => true]);

        // Create Gym Entities
        $premiumTier = TenantEntity::firstOrCreate(['tenant_entity_type_id' => $membershipType->id, 'name' => 'Premium Member']);

        // Create a Gym Member & Attach to Taxonomy
        $member = User::firstOrCreate(
            ['email' => 'jack@gmail.com'],
            ['tenant_id' => $gym->id, 'name' => 'Jack Lifter', 'password' => bcrypt('password'), 'role' => 'organisation']
        );
        $member->entities()->syncWithoutDetaching([$premiumTier->id]);
    }
}
