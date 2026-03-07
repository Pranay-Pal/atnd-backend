<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tenant_entities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_entity_type_id')->constrained('tenant_entity_types')->cascadeOnDelete();
            $table->string('name'); // e.g. "Grade 10", "Engineering"
            $table->timestamps();
            
            // You shouldn't have two "Grade 10" entities under the "Class" type
            $table->unique(['tenant_entity_type_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_entities');
    }
};
