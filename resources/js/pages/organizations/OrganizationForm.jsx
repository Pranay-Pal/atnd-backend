import React, { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import api from '../../utils/axios';

export default function OrganizationForm() {
    const navigate = useNavigate();
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);

    const [form, setForm] = useState({
        name: '',
        domain: '',
        industry: 'education',
        admin_name: '',
        admin_email: '',
        admin_password: '',
    });

    const handleChange = (e) => {
        const { name, value } = e.target;
        setForm(prev => ({ ...prev, [name]: value }));
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setLoading(true);
        setError(null);

        try {
            await api.post('/super-admin/tenants', form);
            navigate('/super-admin/tenants');
        } catch (err) {
            setError(err.response?.data?.message || 'Failed to create organization');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="max-w-2xl mx-auto space-y-6">
            <div className="flex items-center space-x-4 mb-6">
                <Link to="/super-admin/tenants" className="text-slate-400 hover:text-slate-600 transition-colors">
                    <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </Link>
                <div>
                    <h1 className="text-2xl font-bold text-slate-800">Add New Organization</h1>
                    <p className="text-sm text-slate-500 mt-1">Provision a new tenant and an administrator account.</p>
                </div>
            </div>

            <form onSubmit={handleSubmit} className="bg-white rounded-xl shadow-sm border border-slate-200 p-6 space-y-6">

                {error && (
                    <div className="bg-red-50 text-red-600 p-4 rounded-lg text-sm border border-red-100">
                        {error}
                    </div>
                )}

                <div className="space-y-4">
                    <h2 className="text-lg font-semibold text-slate-800 flex items-center">
                        <span className="bg-blue-100 text-blue-700 w-6 h-6 rounded-full flex items-center justify-center text-xs mr-2">1</span>
                        Organization Details
                    </h2>

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1">Company / School Name</label>
                            <input type="text" name="name" required value={form.name} onChange={handleChange}
                                className="w-full border border-slate-300 rounded-lg px-4 py-2.5 outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                placeholder="e.g. Acme Institute" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1">App Domain</label>
                            <input type="text" name="domain" required value={form.domain} onChange={handleChange}
                                className="w-full border border-slate-300 rounded-lg px-4 py-2.5 outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                placeholder="e.g. acme.genskytech.com" />
                        </div>
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">Industry Tier (Auto-seeds custom taxonomy)</label>
                        <select name="industry" value={form.industry} onChange={handleChange}
                            className="w-full border border-slate-300 rounded-lg px-4 py-2.5 outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white">
                            <option value="education">Education (Seeds: Class, Section)</option>
                            <option value="corporate">Corporate Office (Seeds: Department, Role)</option>
                            <option value="fitness">Gym / Fitness (Seeds: Membership Tier)</option>
                            <option value="other">Other / Custom</option>
                        </select>
                    </div>
                </div>

                <hr className="border-slate-100" />

                <div className="space-y-4">
                    <h2 className="text-lg font-semibold text-slate-800 flex items-center">
                        <span className="bg-blue-100 text-blue-700 w-6 h-6 rounded-full flex items-center justify-center text-xs mr-2">2</span>
                        Initial Administrator
                    </h2>

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1">Admin Full Name</label>
                            <input type="text" name="admin_name" required value={form.admin_name} onChange={handleChange}
                                className="w-full border border-slate-300 rounded-lg px-4 py-2.5 outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                placeholder="John Doe" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1">Admin Email Address</label>
                            <input type="email" name="admin_email" required value={form.admin_email} onChange={handleChange}
                                className="w-full border border-slate-300 rounded-lg px-4 py-2.5 outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                placeholder="admin@domain.com" />
                        </div>
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">Account Password (Min 8 Characters)</label>
                        <input type="password" name="admin_password" required minLength={8} value={form.admin_password} onChange={handleChange}
                            className="w-full border border-slate-300 rounded-lg px-4 py-2.5 outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                            placeholder="••••••••" />
                    </div>
                </div>

                <div className="pt-4 flex justify-end">
                    <button
                        type="submit"
                        disabled={loading}
                        className="bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white px-6 py-2.5 rounded-lg text-sm font-semibold transition-colors shadow-sm"
                    >
                        {loading ? 'Provisioning Tenant...' : 'Provision Organization'}
                    </button>
                </div>
            </form>
        </div>
    );
}
