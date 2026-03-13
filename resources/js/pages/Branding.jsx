import React, { useState, useEffect } from 'react';
import api from '../utils/axios';

export default function Branding() {
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [success, setSuccess] = useState(false);
    const [error, setError] = useState(null);

    const [form, setForm] = useState({
        name: '',
        primary_color: '#1d4ed8',
        logo: null
    });

    const [preview, setPreview] = useState(null);
    const [currentLogo, setCurrentLogo] = useState(null);

    useEffect(() => {
        fetchBranding();
    }, []);

    const fetchBranding = async () => {
        try {
            const response = await api.get('/admin/branding');
            const { name, settings } = response.data;
            setForm({
                name: name || '',
                primary_color: settings.primary_color || '#1d4ed8',
                logo: null
            });
            setCurrentLogo(settings.logo_url);
            setLoading(false);
        } catch (err) {
            setError('Failed to load branding settings');
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
        formData.append('primary_color', form.primary_color);
        if (form.logo) {
            formData.append('logo', form.logo);
        }

        try {
            const response = await api.post('/admin/branding', formData, {
                headers: { 'Content-Type': 'multipart/form-data' }
            });
            setSuccess(true);
            setCurrentLogo(response.data.tenant.settings.logo_url);
            setForm(prev => ({ ...prev, logo: null }));
            setPreview(null);
            
            // Trigger a quick flash of success
            setTimeout(() => setSuccess(false), 3000);
        } catch (err) {
            setError(err.response?.data?.message || 'Failed to update branding');
        } finally {
            setSaving(false);
        }
    };

    if (loading) return <div className="p-6">Loading branding settings...</div>;

    return (
        <div className="max-w-4xl mx-auto py-6">
            <div className="mb-8">
                <h1 className="text-2xl font-bold text-slate-800">Organisation Branding</h1>
                <p className="text-sm text-slate-500 mt-1">
                    Customise how your organisation appears on kiosk devices and the admin portal.
                </p>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                {/* Form Area */}
                <div className="lg:col-span-2">
                    <form onSubmit={handleSubmit} className="bg-white rounded-xl shadow-sm border border-slate-200 p-6 space-y-6">
                        {error && (
                            <div className="bg-red-50 text-red-600 p-4 rounded-lg text-sm border border-red-100">
                                {error}
                            </div>
                        )}
                        {success && (
                            <div className="bg-emerald-50 text-emerald-600 p-4 rounded-lg text-sm border border-emerald-100">
                                Branding updated successfully! Changes will reflect on kiosks after their next sync.
                            </div>
                        )}

                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1">Organisation Name</label>
                            <input
                                type="text"
                                name="name"
                                value={form.name}
                                onChange={handleChange}
                                required
                                className="w-full border border-slate-300 rounded-lg px-4 py-2.5 outline-none focus:ring-2 focus:ring-blue-500 transition-all"
                            />
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1">Primary Brand Colour</label>
                            <div className="flex items-center space-x-3">
                                <input
                                    type="color"
                                    name="primary_color"
                                    value={form.primary_color}
                                    onChange={handleChange}
                                    className="h-10 w-20 border border-slate-300 rounded cursor-pointer"
                                />
                                <input
                                    type="text"
                                    name="primary_color"
                                    value={form.primary_color}
                                    onChange={handleChange}
                                    className="w-32 border border-slate-300 rounded-lg px-4 py-2 text-sm uppercase"
                                />
                            </div>
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1">Organisation Logo</label>
                            <div className="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-slate-300 border-dashed rounded-lg hover:border-blue-400 transition-colors">
                                <div className="space-y-1 text-center">
                                    <svg className="mx-auto h-12 w-12 text-slate-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                                        <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
                                    </svg>
                                    <div className="flex text-sm text-slate-600">
                                        <label className="relative cursor-pointer bg-white rounded-md font-medium text-blue-600 hover:text-blue-500">
                                            <span>Upload a file</span>
                                            <input type="file" name="logo" className="sr-only" onChange={handleChange} accept="image/*" />
                                        </label>
                                        <p className="pl-1">or drag and drop</p>
                                    </div>
                                    <p className="text-xs text-slate-500">PNG, JPG, SVG up to 2MB</p>
                                </div>
                            </div>
                        </div>

                        <div className="pt-4 flex justify-end">
                            <button
                                type="submit"
                                disabled={saving}
                                className="bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white px-8 py-2.5 rounded-lg text-sm font-semibold transition-colors shadow-sm"
                            >
                                {saving ? 'Saving Changes...' : 'Save Branding'}
                            </button>
                        </div>
                    </form>
                </div>

                {/* Preview Area */}
                <div className="space-y-6">
                    <div className="bg-white rounded-xl shadow-sm border border-slate-200 p-6 overflow-hidden">
                        <h3 className="text-sm font-semibold text-slate-700 uppercase tracking-wider mb-4">Preview</h3>
                        
                        <div className="space-y-6">
                            {/* Kiosk Header Preview */}
                            <div>
                                <p className="text-xs text-slate-500 mb-2">Kiosk Top Bar</p>
                                <div className="bg-slate-900 p-4 rounded-lg flex items-center space-x-3">
                                    {(preview || currentLogo) ? (
                                        <img src={preview || currentLogo} alt="Logo" className="h-8 w-auto" />
                                    ) : (
                                        <div className="h-8 w-8 bg-slate-700 rounded flex items-center justify-center text-white text-[10px]">LOGO</div>
                                    )}
                                    <span className="text-white font-bold text-sm truncate">{form.name || 'Organisation'}</span>
                                </div>
                            </div>

                            {/* Theme Preview */}
                            <div>
                                <p className="text-xs text-slate-500 mb-2">Button Theme</p>
                                <button className="w-full py-2 rounded-lg text-white font-medium text-sm" style={{ backgroundColor: form.primary_color }}>
                                    Action Button
                                </button>
                            </div>
                        </div>
                    </div>

                    <div className="bg-blue-50 rounded-xl p-4 border border-blue-100 flex items-start space-x-3">
                        <svg className="h-5 w-5 text-blue-500 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <p className="text-xs text-blue-700 leading-relaxed">
                            Changes saved here are synced to all active kiosks. Ensure your logo has sufficient contrast against dark and light backgrounds.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    );
}
