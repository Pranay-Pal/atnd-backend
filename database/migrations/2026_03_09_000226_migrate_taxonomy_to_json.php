<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add the JSON column to the users table
        Schema::table('users', function (Blueprint $table) {
            $table->json('taxonomy_properties')->nullable()->after('member_uid');
        });

        // 2. Data Migration: Read from pivot, build JSON, save to user
        // We do this chunked to avoid memory exhaustion on large datasets
        DB::table('users')->orderBy('id')->chunk(500, function ($users) {
            foreach ($users as $user) {
                // Fetch all raw pivot assignments for this specific user
                $pivotRows = DB::table('entity_user')
                    ->join('tenant_entities', 'entity_user.tenant_entity_id', '=', 'tenant_entities.id')
                    ->where('entity_user.user_id', $user->id)
                    ->select('tenant_entities.tenant_entity_type_id', 'tenant_entities.id as entity_id')
                    ->get();

                if ($pivotRows->isNotEmpty()) {
                    $jsonMap = [];
                    foreach ($pivotRows as $row) {
                        // "TypeID" => "ValueID"
                        // Since array keys must be unique, if they somehow had two Classes, the last one silently wins. Perfect.
                        $jsonMap[(string) $row->tenant_entity_type_id] = $row->entity_id;
                    }

                    // Save the built JSON map back to the user
                    DB::table('users')
                        ->where('id', $user->id)
                        ->update(['taxonomy_properties' => json_encode($jsonMap)]);
                }
            }
        });

        // 3. Drop the pivot table entirely! We are now using JSON.
        Schema::dropIfExists('entity_user');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Re-create the pivot table
        Schema::create('entity_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('tenant_entity_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            // Prevent assigning the EXACT same tag twice
            $table->unique(['user_id', 'tenant_entity_id']);
        });

        // Reverse data migration
        DB::table('users')->whereNotNull('taxonomy_properties')->orderBy('id')->chunk(500, function ($users) {
            $inserts = [];
            foreach ($users as $user) {
                $map = json_decode($user->taxonomy_properties, true);
                if (is_array($map)) {
                    foreach ($map as $typeId => $entityId) {
                        $inserts[] = [
                            'user_id' => $user->id,
                            'tenant_entity_id' => $entityId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }
            }
            // Insert back into the pivot table
            if (!empty($inserts)) {
                foreach (array_chunk($inserts, 100) as $chunk) {
                    DB::table('entity_user')->insert($chunk);
                }
            }
        });

        // Drop the JSON column
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('taxonomy_properties');
        });
    }
};
