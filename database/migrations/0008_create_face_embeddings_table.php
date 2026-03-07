<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('face_embeddings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // 512 x float32 = 2048 bytes, L2-normalized, stored as binary BLOB
            $table->binary('embedding');
            $table->string('model_version')->default('w600k_mbf');
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('face_embeddings');
    }
};
