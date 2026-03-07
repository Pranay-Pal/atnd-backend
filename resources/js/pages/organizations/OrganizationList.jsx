import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import api from '../../utils/axios';

export default function OrganizationList() {
    const [organizations, setOrganizations] = useState([]);
    const [loading, setLoading] = useState(true);

    const fetchOrgs = async () => {
        try {
            const res = await api.get('/super-admin/tenants');
            setOrganizations(res.data);
        } catch (err) {
            console.error(err);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchOrgs();
    }, []);

    const handleDelete = async (id) => {
        if (!window.confirm('Are you sure you want to delete this organization and all its data? This cannot be undone.')) return;
        try {
            await api.delete(`/super-admin/tenants/${id}`);
            fetchOrgs();
        } catch (err) {
            alert('Error deleting organization');
        }
    };

    return (
        <div className="max-w-7xl mx-auto space-y-6">
            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-slate-800">Organizations</h1>
                    <p className="text-slate-500 text-sm mt-1">Manage platform tenants, schools, and offices.</p>
                </div>
                <Link
                    to="/super-admin/tenants/new"
                    className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors"
                >
                    + Add Organization
                </Link>
            </div>

            <div className="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                {loading ? (
                    <div className="p-6 text-center text-slate-500">Loading organizations...</div>
                ) : organizations.length === 0 ? (
                    <div className="p-10 text-center">
                        <p className="text-slate-500 mb-4">No organizations have been created yet.</p>
                    </div>
                ) : (
                    <table className="w-full text-sm text-left">
                        <thead className="bg-slate-50 text-slate-500 uppercase font-medium text-xs">
                            <tr>
                                <th className="px-6 py-4">Name</th>
                                <th className="px-6 py-4">Domain</th>
                                <th className="px-6 py-4">Industry</th>
                                <th className="px-6 py-4">Users Count</th>
                                <th className="px-6 py-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {organizations.map((org) => (
                                <tr key={org.id} className="hover:bg-slate-50 transition-colors">
                                    <td className="px-6 py-4 font-medium text-slate-900">{org.name}</td>
                                    <td className="px-6 py-4 text-slate-500">{org.domain}</td>
                                    <td className="px-6 py-4">
                                        <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 capitalize">
                                            {org.industry}
                                        </span>
                                    </td>
                                    <td className="px-6 py-4 text-slate-500">{org.users_count}</td>
                                    <td className="px-6 py-4 text-right space-x-3">
                                        <button
                                            onClick={() => handleDelete(org.id)}
                                            className="text-red-500 hover:text-red-700 font-medium transition-colors"
                                        >
                                            Delete
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>
        </div>
    );
}
