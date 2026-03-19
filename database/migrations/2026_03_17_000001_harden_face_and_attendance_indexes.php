<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Deduplicate any existing face rows before enforcing uniqueness.
        $duplicates = DB::table('face_embeddings')
            ->select('tenant_id', 'user_id', DB::raw('MAX(id) as keep_id'), DB::raw('COUNT(*) as cnt'))
            ->groupBy('tenant_id', 'user_id')
            ->having('cnt', '>', 1)
            ->get();

        foreach ($duplicates as $dup) {
            DB::table('face_embeddings')
                ->where('tenant_id', $dup->tenant_id)
                ->where('user_id', $dup->user_id)
                ->where('id', '!=', $dup->keep_id)
                ->delete();
        }

        Schema::table('face_embeddings', function (Blueprint $table) {
            $table->unique(
                ['tenant_id', 'user_id'],
                'face_embeddings_tenant_user_unique'
            );
        });

        $hasLocalIdColumn = Schema::hasColumn('attendance_logs', 'local_id');
        Schema::table('attendance_logs', function (Blueprint $table) use ($hasLocalIdColumn) {
            if (!$hasLocalIdColumn) {
                $table->string('local_id')->nullable()->after('device_id');
            }
        });

        // Backfill new local_id column from metadata.local_id when present.
        DB::table('attendance_logs')
            ->whereNull('local_id')
            ->orderBy('id')
            ->select('id', 'metadata')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    $meta = is_array($row->metadata)
                        ? $row->metadata
                        : json_decode((string) $row->metadata, true);
                    $localId = is_array($meta) ? ($meta['local_id'] ?? null) : null;
                    if (is_string($localId) && $localId !== '') {
                        DB::table('attendance_logs')
                            ->where('id', $row->id)
                            ->update(['local_id' => $localId]);
                    }
                }
            });

        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->unique(
                ['tenant_id', 'device_id', 'local_id'],
                'attendance_logs_tenant_device_local_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('face_embeddings', function (Blueprint $table) {
            $table->dropUnique('face_embeddings_tenant_user_unique');
        });

        $hasLocalIdColumn = Schema::hasColumn('attendance_logs', 'local_id');
        Schema::table('attendance_logs', function (Blueprint $table) use ($hasLocalIdColumn) {
            $table->dropUnique('attendance_logs_tenant_device_local_unique');
            if ($hasLocalIdColumn) {
                $table->dropColumn('local_id');
            }
        });
    }
};
