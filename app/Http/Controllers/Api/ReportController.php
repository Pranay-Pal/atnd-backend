<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ReportController extends Controller
{
    /**
     * GET /api/reports/attendance
     * Query params:
     *   user_id         (optional) — filter by user
     *   start_date      (optional) — ISO 8601 date, inclusive
     *   end_date        (optional) — ISO 8601 date, inclusive
     *   entity_id       (optional) — filter logs whose user belongs to this entity value
     *   entity_type_id  (optional) — filter logs whose user belongs to any value of this entity type
     *   format          (optional) — "json" (default) or "csv"
     *   per_page        (optional) — results per page for JSON (default 50)
     */
    public function attendance(Request $request): JsonResponse|Response
    {
        $request->validate([
            'user_id'        => ['nullable', 'integer'],
            'start_date'     => ['nullable', 'date'],
            'end_date'       => ['nullable', 'date', 'after_or_equal:start_date'],
            'entity_id'      => ['nullable', 'integer'],
            'entity_type_id' => ['nullable', 'integer'],
            'filters'        => ['nullable', 'array'],
            'filters.*'      => ['nullable', 'array'],
            'filters.*.*'    => ['integer'],
            'format'         => ['nullable', 'in:json,csv'],
            'per_page'       => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        // Prepare query
        $query = AttendanceLog::with('user:id,name,member_uid')
            ->where('tenant_id', $this->tenantManager->id());

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        if ($request->filled('start_date')) {
            $query->whereDate('recorded_at', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('recorded_at', '<=', $request->end_date);
        }

        if ($request->filled('entity_id')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->withoutGlobalScopes()->whereHas('entities', function ($q2) use ($request) {
                    $q2->where('tenant_entities.id', $request->integer('entity_id'));
                });
            });
        }

        if ($request->filled('entity_type_id')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->withoutGlobalScopes()->whereHas('entities.type', function ($q2) use ($request) {
                    $q2->where('tenant_entity_types.id', $request->integer('entity_type_id'));
                });
            });
        }

        if ($request->filled('filters')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->withoutGlobalScopes()->filterByEntities($request->input('filters'));
            });
        }

        // Add order by after all filters
        $query->orderBy('recorded_at', 'desc');

        if ($request->input('format') === 'csv') {
            return $this->exportCsv($query->get());
        }

        $logs = $query->paginate($request->integer('per_page', 50));

        return response()->json($logs);
    }

    private function exportCsv(\Illuminate\Support\Collection $logs): Response
    {
        $rows   = [];
        // CSV Header
        $rows[] = implode(',', ['id', 'user_id', 'member_uid', 'name', 'type', 'recorded_at', 'similarity', 'device_id']);

        foreach ($logs as $log) {
            $rows[] = implode(',', [
                $log->id,
                $log->user_id,
                $log->user?->member_uid ?? '',
                $log->user?->name ?? 'Unknown',
                $log->type,
                $log->recorded_at?->toIso8601String() ?? '',
                $log->similarity ?? '',
                $log->device_id ?? '',
            ]);
        }

        $csv = implode("\n", $rows);

        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="attendance_report.csv"',
        ]);
    }
}
