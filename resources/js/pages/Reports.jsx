import React, { useEffect, useState, useCallback } from 'react';
import api from '../utils/axios';

export default function Reports() {
    const today = new Date().toISOString().split('T')[0];

    // Filters
    const [startDate, setStartDate]     = useState(today);
    const [endDate, setEndDate]         = useState(today);
    const [entityTypes, setEntityTypes] = useState([]);
    const [entityValues, setEntityValues] = useState([]);
    const [filterTypeId, setFilterTypeId]   = useState('');
    const [filterEntityId, setFilterEntityId] = useState('');
    const [userId, setUserId]           = useState('');

    // Data
    const [logs, setLogs]       = useState([]);
    const [meta, setMeta]       = useState({ total: 0, last_page: 1, current_page: 1 });
    const [loading, setLoading] = useState(false);
    const [page, setPage]       = useState(1);
    const [exporting, setExporting] = useState(false);

    // Load entity types once
    useEffect(() => {
        api.get('/admin/entity-types').then((r) => setEntityTypes(r.data)).catch(() => {});
    }, []);

    // Load entity values when type changes
    useEffect(() => {
        setFilterEntityId('');
        setEntityValues([]);
        if (!filterTypeId) return;
        api.get(`/admin/entity-types/${filterTypeId}/entities`)
            .then((r) => setEntityValues(r.data))
            .catch(() => {});
    }, [filterTypeId]);

    const buildParams = useCallback(() => {
        const p = { page, per_page: 20 };
        if (startDate)       p.start_date      = startDate;
        if (endDate)         p.end_date        = endDate;
        if (filterEntityId)  p.entity_id       = filterEntityId;
        else if (filterTypeId) p.entity_type_id = filterTypeId;
        if (userId)          p.user_id         = userId;
        return p;
    }, [page, startDate, endDate, filterEntityId, filterTypeId, userId]);

    const fetchLogs = useCallback(() => {
        setLoading(true);
        api.get('/admin/reports/attendance', { params: buildParams() })
            .then((r) => {
                setLogs(r.data.data ?? []);
                setMeta({ total: r.data.total, last_page: r.data.last_page, current_page: r.data.current_page });
            })
            .catch(() => {})
            .finally(() => setLoading(false));
    }, [buildParams]);

    useEffect(() => { fetchLogs(); }, [fetchLogs]);

    const handleExportCsv = async () => {
        setExporting(true);
        try {
            const params = { ...buildParams(), format: 'csv' };
            const res = await api.get('/admin/reports/attendance', {
                params,
                responseType: 'blob',
            });
            const url  = URL.createObjectURL(res.data);
            const link = document.createElement('a');
            link.href  = url;
            link.download = `attendance_${startDate}_${endDate}.csv`;
            link.click();
            URL.revokeObjectURL(url);
        } catch {
            // ignore
        } finally {
            setExporting(false);
        }
    };

    const formatDt = (iso) => iso
        ? new Date(iso).toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' })
        : '—';

    return (
        <div className="max-w-7xl mx-auto space-y-6">
            {/* Header */}
            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-gray-800">Attendance Reports</h1>
                    <p className="text-sm text-gray-500 mt-1">{meta.total} records match filters</p>
                </div>
                <button
                    onClick={handleExportCsv}
                    disabled={exporting || loading}
                    className="flex items-center gap-2 border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 text-sm font-medium px-4 py-2.5 rounded-lg transition-colors disabled:opacity-50"
                >
                    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                            d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    {exporting ? 'Exporting…' : 'Export CSV'}
                </button>
            </div>

            {/* Filters */}
            <div className="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                <div className="flex flex-wrap gap-3 items-end">
                    <div>
                        <label className="block text-xs font-medium text-gray-500 mb-1">From</label>
                        <input type="date" value={startDate} max={endDate}
                            onChange={(e) => { setStartDate(e.target.value); setPage(1); }}
                            className="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                    </div>
                    <div>
                        <label className="block text-xs font-medium text-gray-500 mb-1">To</label>
                        <input type="date" value={endDate} min={startDate}
                            onChange={(e) => { setEndDate(e.target.value); setPage(1); }}
                            className="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                    </div>
                    <div>
                        <label className="block text-xs font-medium text-gray-500 mb-1">Entity Type</label>
                        <select value={filterTypeId} onChange={(e) => { setFilterTypeId(e.target.value); setPage(1); }}
                            className="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">All Types</option>
                            {entityTypes.map((t) => <option key={t.id} value={t.id}>{t.name}</option>)}
                        </select>
                    </div>
                    {entityValues.length > 0 && (
                        <div>
                            <label className="block text-xs font-medium text-gray-500 mb-1">
                                {entityTypes.find((t) => String(t.id) === filterTypeId)?.name ?? 'Value'}
                            </label>
                            <select value={filterEntityId} onChange={(e) => { setFilterEntityId(e.target.value); setPage(1); }}
                                className="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">All</option>
                                {entityValues.map((v) => <option key={v.id} value={v.id}>{v.name}</option>)}
                            </select>
                        </div>
                    )}
                    <div>
                        <label className="block text-xs font-medium text-gray-500 mb-1">User ID</label>
                        <input type="number" placeholder="Any" value={userId}
                            onChange={(e) => { setUserId(e.target.value); setPage(1); }}
                            className="w-28 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                    </div>
                    {(filterTypeId || filterEntityId || userId) && (
                        <button
                            onClick={() => { setFilterTypeId(''); setFilterEntityId(''); setUserId(''); setPage(1); }}
                            className="text-xs text-gray-400 hover:text-gray-600 underline self-end pb-2.5"
                        >
                            Clear filters
                        </button>
                    )}
                </div>
            </div>

            {/* Table */}
            <div className="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                {loading ? (
                    <div className="p-6 space-y-3">
                        {[1,2,3,4,5].map((i) => <div key={i} className="h-11 bg-gray-100 animate-pulse rounded" />)}
                    </div>
                ) : logs.length === 0 ? (
                    <p className="text-sm text-gray-400 text-center py-16">No records for the selected filters.</p>
                ) : (
                    <table className="w-full text-sm">
                        <thead className="bg-gray-50 border-b border-gray-100">
                            <tr>
                                <th className="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                <th className="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Event</th>
                                <th className="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                                <th className="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Confidence</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50">
                            {logs.map((log) => (
                                <tr key={log.id} className="hover:bg-gray-50 transition-colors">
                                    <td className="px-6 py-3 font-medium text-gray-900">
                                        {log.user?.name ?? `#${log.user_id}`}
                                        {log.user?.employee_id && (
                                            <span className="ml-2 text-xs text-gray-400">({log.user.employee_id})</span>
                                        )}
                                    </td>
                                    <td className="px-6 py-3">
                                        <span className={`inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium ${
                                            log.type === 'check_in'
                                                ? 'bg-emerald-100 text-emerald-700'
                                                : 'bg-red-100 text-red-700'
                                        }`}>
                                            {log.type === 'check_in' ? 'Check In' : 'Check Out'}
                                        </span>
                                    </td>
                                    <td className="px-6 py-3 text-gray-600">{formatDt(log.recorded_at)}</td>
                                    <td className="px-6 py-3 text-gray-500">
                                        {log.similarity != null
                                            ? <>{Math.round(log.similarity * 100)}%</>
                                            : <span className="text-gray-300">—</span>
                                        }
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}

                {/* Pagination */}
                {meta.last_page > 1 && (
                    <div className="flex items-center justify-between px-6 py-3 border-t border-gray-100">
                        <p className="text-xs text-gray-500">Page {meta.current_page} of {meta.last_page}</p>
                        <div className="flex gap-2">
                            <button disabled={page <= 1} onClick={() => setPage((p) => p - 1)}
                                className="px-3 py-1 text-xs border border-gray-300 rounded hover:bg-gray-50 disabled:opacity-40">
                                ← Prev
                            </button>
                            <button disabled={page >= meta.last_page} onClick={() => setPage((p) => p + 1)}
                                className="px-3 py-1 text-xs border border-gray-300 rounded hover:bg-gray-50 disabled:opacity-40">
                                Next →
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}
