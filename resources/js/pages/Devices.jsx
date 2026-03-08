import React, { useState, useEffect } from 'react';
import api from '../utils/axios';

export default function Devices() {
    const [devices, setDevices] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    const [isCreating, setIsCreating] = useState(false);
    const [deviceName, setDeviceName] = useState('');
    const [newDeviceKey, setNewDeviceKey] = useState(null); // stores the newly generated api_key

    useEffect(() => {
        fetchDevices();
    }, []);

    const fetchDevices = async () => {
        try {
            setLoading(true);
            const { data } = await api.get('/admin/devices');
            if (Array.isArray(data)) {
                setDevices(data);
            } else if (data && Array.isArray(data.data)) {
                setDevices(data.data);
            } else {
                setDevices([]);
            }
        } catch (err) {
            setError(err.response?.data?.message || 'Failed to load devices');
        } finally {
            setLoading(false);
        }
    };

    const handleCreate = async (e) => {
        e.preventDefault();
        try {
            setError(null);
            const { data } = await api.post('/admin/devices', { name: deviceName });
            setNewDeviceKey(data.api_key); // Show the key immediately
            setDeviceName('');
            setIsCreating(false);
            fetchDevices();
        } catch (err) {
            setError(err.response?.data?.message || 'Failed to create device');
        }
    };

    const handleDelete = async (id) => {
        if (!confirm('Are you sure you want to delete this device? It will log out immediately.')) return;
        try {
            await api.delete(`/admin/devices/${id}`);
            fetchDevices();
        } catch (err) {
            setError(err.response?.data?.message || 'Failed to delete device');
        }
    };

    return (
        <div className="space-y-6">
            <div className="flex justify-between items-center pb-4 border-b border-gray-200">
                <h1 className="text-2xl font-bold text-gray-800">Device Management</h1>
                <button
                    onClick={() => setIsCreating(true)}
                    className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
                >
                    Add Device
                </button>
            </div>

            {error && (
                <div className="p-4 bg-red-100 text-red-700 rounded mb-4">
                    {error}
                </div>
            )}

            {newDeviceKey && (
                <div className="p-6 bg-yellow-50 border border-yellow-200 text-yellow-800 rounded mb-6">
                    <h3 className="font-bold text-lg mb-2 text-yellow-900">Device Registered Successfully!</h3>
                    <p className="mb-4">
                        Please save the API Key below and enter it into the Flutter Device app.
                        <strong> You will not be able to view this API key again.</strong>
                    </p>
                    <div className="bg-white p-3 border border-yellow-300 rounded font-mono text-center text-xl select-all">
                        {newDeviceKey}
                    </div>
                    <button
                        onClick={() => setNewDeviceKey(null)}
                        className="mt-4 px-4 py-1.5 text-sm bg-yellow-200 hover:bg-yellow-300 text-yellow-900 rounded"
                    >
                        I have copied the key
                    </button>
                </div>
            )}

            {isCreating && (
                <div className="mb-6 p-4 border border-gray-200 rounded text-gray-700 bg-gray-50">
                    <h2 className="text-lg font-bold mb-4">Register New Device</h2>
                    <form onSubmit={handleCreate} className="flex gap-4">
                        <input
                            type="text"
                            value={deviceName}
                            onChange={(e) => setDeviceName(e.target.value)}
                            placeholder="e.g. Front Desk Tablet"
                            className="bg-white flex-1 p-2 border border-gray-300 rounded focus:ring-blue-500"
                            required
                        />
                        <button type="submit" className="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                            Generate API Key
                        </button>
                        <button
                            type="button"
                            onClick={() => setIsCreating(false)}
                            className="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300"
                        >
                            Cancel
                        </button>
                    </form>
                </div>
            )}

            <div className="bg-white shadow rounded-lg overflow-hidden">
                <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-gray-50">
                        <tr>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Device Name</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Seen</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                            <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody className="bg-white divide-y divide-gray-200">
                        {loading && (
                            <tr>
                                <td colSpan="4" className="px-6 py-8 text-center text-gray-500">Loading devices...</td>
                            </tr>
                        )}
                        {!loading && devices.length === 0 && (
                            <tr>
                                <td colSpan="4" className="px-6 py-8 text-center text-gray-500">No active devices found.</td>
                            </tr>
                        )}
                        {devices.map((device) => (
                            <tr key={device.id}>
                                <td className="px-6 py-4 whitespace-nowrap font-medium text-gray-900">{device.name}</td>
                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {device.last_seen_at ? new Date(device.last_seen_at).toLocaleString() : 'Never logged in'}
                                </td>
                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {new Date(device.created_at).toLocaleDateString()}
                                </td>
                                <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <button
                                        onClick={() => handleDelete(device.id)}
                                        className="text-red-600 hover:text-red-900"
                                    >
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
