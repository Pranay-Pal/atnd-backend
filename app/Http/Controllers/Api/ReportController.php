<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Services\TenantManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ReportController extends Controller
{
    public function __construct(private TenantManager $tenantManager) {}

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
        $query = AttendanceLog::with('user:id,name,member_uid,taxonomy_properties')
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
                // JSON payload: {"1": 5} -> Check if '5' exists as a value in the JSON object
                $q->withoutGlobalScopes()
                  ->whereRaw("JSON_SEARCH(taxonomy_properties, 'one', ?) IS NOT NULL", [(string) $request->integer('entity_id')]);
            });
        }

        if ($request->filled('entity_type_id')) {
            $query->whereHas('user', function ($q) use ($request) {
                // If the user has ANY value assigned to this Type
                // Ex: Is there ANY key "2" inside the JSON {"1": 5, "2": 12}? Yes.
                $q->withoutGlobalScopes()
                  ->whereRaw("JSON_EXTRACT(taxonomy_properties, '$.\"" . $request->integer('entity_type_id') . "\"') IS NOT NULL");
            });
        }

        if ($request->filled('filters')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->withoutGlobalScopes()->filterByEntities($request->input('filters'));
            });
        }

        // Add order by after all filters
        $query->orderBy('recorded_at', 'desc');
        
        $isCsv = $request->input('format') === 'csv';
        $logs = $isCsv ? $query->get() : $query->paginate($request->integer('per_page', 50));

        // Group-fetch taxonomy properties to prevent N+1
        $allEntityIds = [];
        $items = $isCsv ? $logs : $logs->items();
        foreach ($items as $log) {
            if ($log->user && !empty($log->user->taxonomy_properties)) {
                $allEntityIds = array_merge($allEntityIds, array_values($log->user->taxonomy_properties));
            }
        }
        $allEntityIds = array_unique($allEntityIds);

        $entitiesDict = [];
        if (!empty($allEntityIds)) {
            $fetched = \App\Models\TenantEntity::with('type')->whereIn('id', $allEntityIds)->get();
            foreach ($fetched as $e) {
                $entitiesDict[$e->id] = [
                    'id'    => $e->id,
                    'type'  => $e->type?->name,
                    'value' => $e->name,
                ];
            }
        }

        if ($isCsv) {
            return $this->exportCsv($logs, $entitiesDict);
        }

        // Format entities inside the embedded user relations
        $logs->getCollection()->transform(function ($log) use ($entitiesDict) {
            if ($log->user) {
                $userEntities = [];
                if (!empty($log->user->taxonomy_properties)) {
                    foreach (array_values($log->user->taxonomy_properties) as $eid) {
                        if (isset($entitiesDict[$eid])) {
                            $userEntities[] = $entitiesDict[$eid];
                        }
                    }
                }
                $log->user->entities = $userEntities;
            }
            return $log;
        });

        return response()->json($logs);
    }

    private function exportCsv(\Illuminate\Support\Collection $logs, array $entitiesDict): Response
    {
        $rows   = [];
        // CSV Header
        $rows[] = implode(',', ['id', 'user_id', 'member_uid', 'name', 'type', 'recorded_at', 'similarity', 'device_id', 'taxonomy']);

        foreach ($logs as $log) {
            $taxArray = [];
            if ($log->user && !empty($log->user->taxonomy_properties)) {
                foreach (array_values($log->user->taxonomy_properties) as $eid) {
                    if (isset($entitiesDict[$eid])) {
                        $taxArray[] = $entitiesDict[$eid]['type'] . ': ' . $entitiesDict[$eid]['value'];
                    }
                }
            }
            $taxStr = implode('; ', $taxArray);

            $rows[] = implode(',', [
                $log->id,
                $log->user_id,
                $this->escapeCsv($log->user?->member_uid ?? ''),
                $this->escapeCsv($log->user?->name ?? 'Unknown'),
                $log->type,
                $log->recorded_at?->toIso8601String() ?? '',
                $log->similarity ?? '',
                $log->device_id ?? '',
                $this->escapeCsv($taxStr),
            ]);
        }

        $csv = implode("\n", $rows);

        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="attendance_report.csv"',
        ]);
    }

    private function escapeCsv(string $value): string
    {
        if (str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n")) {
            return '"' . str_replace('"', '""', $value) . '"';
        }
        return $value;
    }
}
