import React, { useState } from 'react';
import api from '../utils/axios';
import { useAuth } from '../context/AuthContext';

export default function Settings() {
    const { user } = useAuth();
    const [pwdData, setPwdData] = useState({ current_password: '', new_password: '', new_password_confirmation: '' });
    const [loading, setLoading] = useState(false);
    const [message, setMessage] = useState({ type: '', text: '' });

    const handlePwdChange = async (e) => {
        e.preventDefault();
        setMessage({ type: '', text: '' });
        setLoading(true);

        // Simple frontend validation
        if (pwdData.new_password !== pwdData.new_password_confirmation) {
            setMessage({ type: 'error', text: 'New passwords do not match.' });
            setLoading(false);
            return;
        }

        try {
            await api.post('/admin/password', pwdData);
            setMessage({ type: 'success', text: 'Password updated successfully.' });
            setPwdData({ current_password: '', new_password: '', new_password_confirmation: '' });
        } catch (err) {
            setMessage({
                type: 'error',
                text: err.response?.data?.message || 'Failed to update password. Check inputs.'
            });
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="space-y-6">
            <h1 className="text-2xl font-bold text-gray-800">Account Settings</h1>

            <div className="bg-white p-6 rounded shadow-sm max-w-lg border border-gray-100">
                <h2 className="text-lg font-semibold text-gray-700 mb-4">Change Password</h2>
                {message.text && (
                    <div className={`p-4 mb-5 rounded text-sm font-medium ${message.type === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200'}`}>
                        {message.text}
                    </div>
                )}
                <form onSubmit={handlePwdChange} className="space-y-5">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Current Password</label>
                        <input
                            type="password"
                            required
                            className="w-full border border-gray-300 rounded-md p-2.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-shadow"
                            value={pwdData.current_password}
                            onChange={e => setPwdData({ ...pwdData, current_password: e.target.value })}
                        />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                        <input
                            type="password"
                            required
                            minLength={8}
                            className="w-full border border-gray-300 rounded-md p-2.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-shadow"
                            value={pwdData.new_password}
                            onChange={e => setPwdData({ ...pwdData, new_password: e.target.value })}
                        />
                        <p className="text-xs text-gray-500 mt-1">Minimum 8 characters</p>
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
                        <input
                            type="password"
                            required
                            minLength={8}
                            className="w-full border border-gray-300 rounded-md p-2.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-shadow"
                            value={pwdData.new_password_confirmation}
                            onChange={e => setPwdData({ ...pwdData, new_password_confirmation: e.target.value })}
                        />
                    </div>
                    <button
                        type="submit"
                        disabled={loading}
                        className="w-full bg-blue-600 text-white font-medium rounded-md py-2.5 hover:bg-blue-700 focus:ring-4 focus:ring-blue-300 disabled:opacity-50 transition-colors mt-2"
                    >
                        {loading ? 'Updating...' : 'Update Password'}
                    </button>
                </form>
            </div>
        </div>
    );
}
