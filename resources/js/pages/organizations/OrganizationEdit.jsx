import React, { useState, useEffect } from 'react';
import { useNavigate, useParams, Link } from 'react-router-dom';
import api from '../../utils/axios';

export default function OrganizationEdit() {
    const { id } = useParams();
    const navigate = useNavigate();
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState(null);
    const [success, setSuccess] = useState(false);

    const [form, setForm] = useState({
        name: '',
        domain: '',
        industry: '',
        primary_color: '#1d4ed8',
        logo: null
    });

    const [preview, setPreview] = useState(null);
    const [currentLogo, setCurrentLogo] = useState(null);

    useEffect(() => {
        fetchOrg();
    }, [id]);

    const fetchOrg = async () => {
        try {
            const res = await api.get(`/super-admin/tenants/${id}`);
            const org = res.data;
            setForm({
                name: org.name || '',
                domain: org.domain || '',
                industry: org.industry || 'education',
                primary_color: org.settings?.primary_color || '#1d4ed8',
                logo: null
            });
            setCurrentLogo(org.settings?.logo_url);
            setLoading(false);
        } catch (err) {
            setError('Failed to fetch organization details');
            setLoading(false);
        }
    };

    const handleChange = (e) => {
        const { name, value, files } = e.target;
        if (name === 'logo') {
            const file = files[0];
            setForm(prev => ({ ...prev, logo: file }));
            setPreview(URL.createObjectURL(file));
        } else {
            setForm(prev => ({ ...prev, [name]: value }));
        }
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSaving(true);
        setError(null);
        setSuccess(false);

        const formData = new FormData();
        formData.append('name', form.name);
        formData.append('domain', form.domain);
        formData.append('industry', form.industry);
        formData.append('primary_color', form.primary_color);
        if (form.logo) {
            formData.append('logo', form.logo);
        }

        try {
            await api.post(`/super-admin/tenants/${id}`, formData, {
                headers: { 'Content-Type': 'multipart/form-data' }
            });
            setSuccess(true);
            setTimeout(() => setSuccess(false), 3000);
            fetchOrg(); // Refresh data
        } catch (err) {
            setError(err.response?.data?.message || 'Failed to update organization');
        } finally {
            setSaving(false);
        }
    };

    if (loading) return <div className="p-6">Loading organization details...</div>;

    return (
        <div className="max-w-4xl mx-auto py-6 space-y-6">
            <div className="flex items-center space-x-4 mb-6">
                <Link to="/super-admin/tenants" className="text-slate-400 hover:text-slate-600 transition-colors">
                    <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </Link>
                <div>
                    <h1 className="text-2xl font-bold text-slate-800">Edit Organization</h1>
                    <p className="text-sm text-slate-500 mt-1">Update tenant settings and branding.</p>
                </div>
            </div>

            <form onSubmit={handleSubmit} className="bg-white rounded-xl shadow-sm border border-slate-200 p-6 space-y-6">
                {error && (
                    <div className="bg-red-50 text-red-600 p-4 rounded-lg text-sm border border-red-100">
                        {error}
                    </div>
                )}
                {success && (
                    <div className="bg-emerald-50 text-emerald-600 p-4 rounded-lg text-sm border border-emerald-100">
                        Organization updated successfully!
                    </div>
                )}

                <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">Company / School Name</label>
                        <input type="text" name="name" required value={form.name} onChange={handleChange}
                            className="w-full border border-slate-300 rounded-lg px-4 py-2.5 outline-none focus:ring-2 focus:ring-blue-500 transition-all" />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">App Domain</label>
                        <input type="text" name="domain" required value={form.domain} onChange={handleChange}
                            className="w-full border border-slate-300 rounded-lg px-4 py-2.5 outline-none focus:ring-2 focus:ring-blue-500 transition-all" />
                    </div>
                </div>

                <div>
                    <label className="block text-sm font-medium text-slate-700 mb-1">Industry Tier</label>
                    <select name="industry" value={form.industry} onChange={handleChange}
                        className="w-full border border-slate-300 rounded-lg px-4 py-2.5 outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                        <option value="education">Education</option>
                        <option value="corporate">Corporate Office</option>
                        <option value="fitness">Gym / Fitness</option>
                        <option value="other">Other / Custom</option>
                    </select>
                </div>

                <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">Primary Brand Colour</label>
                        <div className="flex items-center space-x-3">
                            <input type="color" name="primary_color" value={form.primary_color} onChange={handleChange}
                                className="h-10 w-20 border border-slate-300 rounded cursor-pointer" />
                            <input type="text" name="primary_color" value={form.primary_color} onChange={handleChange}
                                className="w-32 border border-slate-300 rounded-lg px-4 py-2 text-sm uppercase" />
                        </div>
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">Organisation Logo</label>
                        <div className="mt-1 flex items-center space-x-4">
                            {(preview || currentLogo) && (
                                <img src={preview || currentLogo} alt="Logo Preview" className="h-12 w-auto bg-slate-100 rounded p-1" />
                            )}
                            <label className="cursor-pointer bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                                <span>Change Logo</span>
                                <input type="file" name="logo" className="sr-only" onChange={handleChange} accept="image/*" />
                            </label>
                        </div>
                    </div>
                </div>

                <div className="pt-4 flex justify-end">
                    <button
                        type="submit"
                        disabled={saving}
                        className="bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white px-8 py-2.5 rounded-lg text-sm font-semibold transition-colors shadow-sm"
                    >
                        {saving ? 'Saving Changes...' : 'Update Organization'}
                    </button>
                </div>
            </form>
        </div>
    );
}
