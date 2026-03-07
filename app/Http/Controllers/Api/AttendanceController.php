<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
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

        $synced = [];
        $failed = [];

        foreach ($data['records'] as $rec) {
            $user = User::withoutGlobalScopes()
                ->where('id', $rec['user_id'])
                ->where('tenant_id', $tenantId)
                ->first();

            if (!$user) {
                $failed[] = ['local_id' => $rec['local_id'], 'reason' => 'User not found.'];
                continue;
            }

            $log = AttendanceLog::create([
                'tenant_id'   => $tenantId,
                'user_id'     => $rec['user_id'],
                'type'        => $rec['type'],
                'recorded_at' => $rec['recorded_at'],
                'device_id'   => $device->id,
                'similarity'  => $rec['similarity'] ?? null,
                'synced'      => true,
                'metadata'    => ['local_id' => $rec['local_id']],
            ]);

            // Return both local_id (to match device record) and server_id (DB primary key)
            $synced[] = ['local_id' => $rec['local_id'], 'server_id' => $log->id];
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
            'since' => ['nullable', 'date'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);

        $query = AttendanceLog::with('user:id,name,employee_id')
            ->orderBy('recorded_at', 'asc');

        if ($request->filled('since')) {
            $query->where('updated_at', '>=', $request->input('since'));
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
                    'employee_id' => $log->user->employee_id,
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

        $log = AttendanceLog::create([
            'tenant_id'   => $tenantId,
            'user_id'     => $data['user_id'],
            'type'        => $type,
            'recorded_at' => $data['recorded_at'] ?? now(),
            'device_id'   => $device->id,
            'similarity'  => $data['similarity'] ?? null,
            'synced'      => true,
        ]);

        return response()->json($log, 201);
    }
}
