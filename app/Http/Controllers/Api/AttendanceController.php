<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\User;
use App\Services\TenantManager;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class AttendanceController extends Controller
{
    public function __construct(private TenantManager $tenantManager) {}

    /**
     * POST /api/attendance/check-in
     * Body: { user_id: int, recorded_at?: ISO8601, similarity?: float }
     */
    public function checkIn(Request $request): JsonResponse
    {
        return $this->record($request, 'check_in');
    }

    /**
     * POST /api/attendance/check-out
     * Body: { user_id: int, recorded_at?: ISO8601, similarity?: float }
     */
    public function checkOut(Request $request): JsonResponse
    {
        return $this->record($request, 'check_out');
    }

    /**
     * POST /api/attendance/sync   (upload direction: device → server)
     *
     * Batch-submit offline/immediate attendance records from the device.
     * Body: { records: [{ local_id, user_id, type, recorded_at, similarity? }] }
     *
     * Returns:
     *   synced: [{ local_id, server_id }]   ← server_id lets the device store the DB id
     *   failed: [{ local_id, reason }]
     */
    public function sync(Request $request): JsonResponse
    {
        $data = $request->validate([
            'records'               => ['required', 'array', 'max:500'],
            'records.*.local_id'    => ['required', 'string'],
            'records.*.user_id'     => ['required', 'integer'],
            'records.*.type'        => ['required', 'in:check_in,check_out'],
            'records.*.recorded_at' => ['required', 'date'],
            'records.*.similarity'  => ['nullable', 'numeric', 'min:0', 'max:1'],
        ]);

        $device   = $request->user();
        $tenantId = $device->tenant_id;
        $hasLocalIdColumn = Schema::hasColumn('attendance_logs', 'local_id');

        $synced = [];
        $failed = [];

        foreach ($data['records'] as $rec) {
            try {
                $recordedAt = Carbon::parse($rec['recorded_at'])->utc();

                $user = User::withoutGlobalScopes()
                    ->where('id', $rec['user_id'])
                    ->where('tenant_id', $tenantId)
                    ->first();

                if (!$user) {
                    $failed[] = ['local_id' => $rec['local_id'], 'reason' => 'User not found.'];
                    continue;
                }

                // 1. Network idempotency (prevent duplicate sync on retries)
                $existingSync = AttendanceLog::where('tenant_id', $tenantId)
                    ->where('device_id', $device->id)
                    ->where(function ($q) use ($rec, $hasLocalIdColumn) {
                        if ($hasLocalIdColumn) {
                            $q->where('local_id', $rec['local_id'])
                                ->orWhere('metadata->local_id', $rec['local_id']);
                        } else {
                            $q->where('metadata->local_id', $rec['local_id']);
                        }
                    })
                    ->first();

                if ($existingSync) {
                    $synced[] = ['local_id' => $rec['local_id'], 'server_id' => $existingSync->id];
                    continue;
                }

                // 2. Business rule: only one check-in per user per UTC day
                if ($rec['type'] === 'check_in') {
                    $existing = AttendanceLog::where('user_id', $rec['user_id'])
                        ->where('tenant_id', $tenantId)
                        ->where('type', 'check_in')
                        ->whereDate('recorded_at', $recordedAt->toDateString())
                        ->first();
                    if ($existing) {
                        $synced[] = ['local_id' => $rec['local_id'], 'server_id' => $existing->id];
                        continue;
                    }
                }

                $payload = [
                    'tenant_id'   => $tenantId,
                    'user_id'     => $rec['user_id'],
                    'type'        => $rec['type'],
                    'recorded_at' => $recordedAt,
                    'device_id'   => $device->id,
                    'similarity'  => $rec['similarity'] ?? null,
                    'synced'      => true,
                    'metadata'    => ['local_id' => $rec['local_id']],
                ];
                if ($hasLocalIdColumn) {
                    $payload['local_id'] = $rec['local_id'];
                }
                $log = AttendanceLog::create($payload);

                $synced[] = ['local_id' => $rec['local_id'], 'server_id' => $log->id];
            } catch (\Throwable $e) {
                $failed[] = [
                    'local_id' => $rec['local_id'],
                    'reason' => 'Failed to save record.',
                ];
            }
        }

        return response()->json(['synced' => $synced, 'failed' => $failed]);
    }

    /**
     * GET /api/attendance/sync   (download direction: server → device)
     *
     * Returns attendance logs for the tenant that were created/updated after [since].
     * The device uses this to stay in sync with records from other terminals.
     *
     * Query params:
     *   since  (optional) — ISO 8601 datetime; only return records updated after this
     *   limit  (optional) — max records to return (default 500, max 1000)
     */
    public function download(Request $request): JsonResponse
    {
        $request->validate([
            'since'       => ['nullable', 'date'],
            'limit'       => ['nullable', 'integer', 'min:1', 'max:1000'],
            'filters'     => ['nullable', 'array'],
            'filters.*'   => ['nullable', 'array'],
            'filters.*.*' => ['integer'],
        ]);

        // Prepare query
        $query = AttendanceLog::with('user:id,name,member_uid')
            ->where('tenant_id', $this->tenantManager->id());

        if ($request->filled('since')) {
            $query->where('updated_at', '>=', $request->input('since'));
        }

        if ($request->filled('filters')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->withoutGlobalScopes()->filterByEntities($request->input('filters'));
            });
        }

        $logs = $query->limit($request->integer('limit', 500))->get();

        return response()->json(
            $logs->map(fn (AttendanceLog $log) => [
                'id'          => $log->id,
                'user_id'     => $log->user_id,
                'type'        => $log->type,
                'recorded_at' => $log->recorded_at?->toIso8601String(),
                'device_id'   => $log->device_id,
                'similarity'  => $log->similarity,
                'user'        => $log->user ? [
                    'id'          => $log->user->id,
                    'name'        => $log->user->name,
                    'member_uid' => $log->user->member_uid,
                ] : null,
            ])
        );
    }

    // ──────────────────────────────────────────────────────────────────────────

    private function record(Request $request, string $type): JsonResponse
    {
        $data = $request->validate([
            'user_id'     => ['required', 'integer', 'exists:users,id'],
            'recorded_at' => ['nullable', 'date'],
            'similarity'  => ['nullable', 'numeric', 'min:0', 'max:1'],
        ]);

        $device   = $request->user();
        $tenantId = $device->tenant_id;

        $user = User::withoutGlobalScopes()->find($data['user_id']);
        if (!$user || $user->tenant_id !== $tenantId) {
            return response()->json(['message' => 'User not found in tenant.'], 404);
        }

        $recordedAt = isset($data['recorded_at'])
            ? Carbon::parse($data['recorded_at'])->utc()
            : now()->utc();

        if ($type === 'check_in') {
            $existing = AttendanceLog::where('user_id', $data['user_id'])
                ->where('tenant_id', $tenantId)
                ->where('type', 'check_in')
                ->whereDate('recorded_at', $recordedAt->toDateString())
                ->first();
            
            if ($existing) {
                return response()->json($existing, 200);
            }
        }

        $log = AttendanceLog::create([
            'tenant_id'   => $tenantId,
            'user_id'     => $data['user_id'],
            'type'        => $type,
            'recorded_at' => $recordedAt,
            'device_id'   => $device->id,
            'similarity'  => $data['similarity'] ?? null,
            'synced'      => true,
        ]);

        return response()->json($log, 201);
    }
}
