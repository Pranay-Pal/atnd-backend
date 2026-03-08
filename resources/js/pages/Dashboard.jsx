import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import api from '../utils/axios';

function StatCard({ label, value, icon, color, loading }) {
    const colors = {
        blue: { bg: 'bg-blue-100', icon: 'text-blue-600' },
        indigo: { bg: 'bg-indigo-100', icon: 'text-indigo-600' },
        emerald: { bg: 'bg-emerald-100', icon: 'text-emerald-600' },
    };
    const c = colors[color] ?? colors.blue;

    return (
        <div className="bg-white rounded-xl shadow-sm p-6 border border-gray-100 flex items-center space-x-4">
            <div className={`${c.bg} rounded-full p-3 flex-shrink-0`}>
                <svg className={`w-7 h-7 ${c.icon}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    {icon}
                </svg>
            </div>
            <div>
                <p className="text-sm font-medium text-gray-500">{label}</p>
                {loading
                    ? <div className="h-7 w-16 bg-gray-200 animate-pulse rounded mt-1" />
                    : <p className="text-2xl font-bold text-gray-900">{value ?? '—'}</p>
                }
            </div>
        </div>
    );
}

export default function Dashboard() {
    const today = new Date().toISOString().split('T')[0];

    const [stats, setStats] = useState({ users: null, todayLogs: null, entityTypes: null });
    const [recentLogs, setRecentLogs] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        Promise.all([
            api.get('/admin/users', { params: { per_page: 1 } }),
            api.get('/admin/reports/attendance', { params: { start_date: today, end_date: today, per_page: 5 } }),
            api.get('/admin/entity-types'),
        ]).then(([usersRes, logsRes, typesRes]) => {
            setStats({
                users: usersRes.data.total,
                todayLogs: logsRes.data.total,
                entityTypes: typesRes.data.length,
            });
            setRecentLogs(logsRes.data.data ?? []);
        }).catch(() => {
            // values stay null on error
        }).finally(() => setLoading(false));
    }, []);

    const formatTime = (iso) => iso
        ? new Date(iso).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
        : '—';

    return (
        <div className="max-w-7xl mx-auto space-y-6">
            {/* Page header */}
            <div className="bg-white rounded-xl shadow-sm p-6 border border-gray-100 flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-gray-800">Dashboard</h1>
                    <p className="text-gray-500 text-sm mt-1">Overview for your organisation.</p>
                </div>
                <div className="bg-blue-50 text-blue-700 px-4 py-2 rounded-lg font-medium text-sm">
                    {new Date().toLocaleDateString(undefined, { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}
                </div>
            </div>

            {/* Stat cards */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <StatCard
                    label="Total Users"
                    value={stats.users}
                    loading={loading}
                    color="blue"
                    icon={<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />}
                />
                <StatCard
                    label="Today's Attendance"
                    value={stats.todayLogs}
                    loading={loading}
                    color="emerald"
                    icon={<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />}
                />
                <StatCard
                    label="Entity Types"
                    value={stats.entityTypes}
                    loading={loading}
                    color="indigo"
                    icon={<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-5 5a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 10V5a2 2 0 012-2z" />}
                />
            </div>

            {/* Recent attendance */}
            <div className="bg-white rounded-xl shadow-sm border border-gray-100">
                <div className="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                    <h2 className="text-base font-semibold text-gray-800">Today's Recent Activity</h2>
                    <Link to="/reports" className="text-sm text-blue-600 hover:text-blue-700 font-medium">
                        View all →
                    </Link>
                </div>

                {loading ? (
                    <div className="p-6 space-y-3">
                        {[1, 2, 3].map((i) => (
                            <div key={i} className="h-10 bg-gray-100 animate-pulse rounded" />
                        ))}
                    </div>
                ) : recentLogs.length === 0 ? (
                    <p className="text-sm text-gray-400 text-center py-10">No attendance recorded today.</p>
                ) : (
                    <table className="w-full text-sm">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                <th className="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Event</th>
                                <th className="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50">
                            {recentLogs.map((log) => (
                                <tr key={log.id} className="hover:bg-gray-50 transition-colors">
                                    <td className="px-6 py-3 font-medium text-gray-900">
                                        {log.user?.name ?? `User #${log.user_id}`}
                                        {log.user?.member_uid && (
                                            <span className="ml-2 text-xs text-gray-400">#{log.user.member_uid}</span>
                                        )}
                                    </td>
                                    <td className="px-6 py-3">
                                        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${log.type === 'check_in'
                                                ? 'bg-emerald-100 text-emerald-700'
                                                : 'bg-red-100 text-red-700'
                                            }`}>
                                            {log.type === 'check_in' ? 'Check In' : 'Check Out'}
                                        </span>
                                    </td>
                                    <td className="px-6 py-3 text-gray-500">
                                        {formatTime(log.recorded_at)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>

            {/* Quick nav */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                {[
                    { to: '/admin/users', label: 'Manage Users', desc: 'Add, edit and assign groups' },
                    { to: '/admin/reports', label: 'Attendance Reports', desc: 'Filter by date, group or person' },
                    { to: '/admin/entity-types', label: 'Taxonomy', desc: 'Manage entity types and values' },
                ].map(({ to, label, desc }) => (
                    <Link
                        key={to}
                        to={to}
                        className="bg-white rounded-xl border border-gray-100 shadow-sm p-5 hover:shadow-md hover:border-blue-200 transition-all group"
                    >
                        <p className="text-sm font-semibold text-blue-600 group-hover:text-blue-700">{label}</p>
                        <p className="text-xs text-gray-500 mt-1">{desc}</p>
                    </Link>
                ))}
            </div>
        </div>
    );
}
