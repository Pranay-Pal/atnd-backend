import React, { useEffect, useState } from 'react';
import api from '../utils/axios';
import Modal from '../components/Modal';

export default function EntityTypes() {
    const [types, setTypes]             = useState([]);
    const [selectedType, setSelectedType] = useState(null);
    const [entities, setEntities]       = useState([]);
    const [loadingTypes, setLoadingTypes] = useState(true);
    const [loadingEntities, setLoadingEntities] = useState(false);

    // Modals
    const [modal, setModal]           = useState(null); // 'type' | 'entity' | 'deleteType' | 'deleteEntity'
    const [targetItem, setTargetItem] = useState(null);
    const [newName, setNewName]       = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [error, setError]           = useState('');

    const fetchTypes = () => {
        setLoadingTypes(true);
        api.get('/admin/entity-types')
            .then((r) => setTypes(r.data))
            .catch(() => {})
            .finally(() => setLoadingTypes(false));
    };

    const fetchEntities = (type) => {
        setLoadingEntities(true);
        api.get(`/admin/entity-types/${type.id}/entities`)
            .then((r) => setEntities(r.data))
            .catch(() => {})
            .finally(() => setLoadingEntities(false));
    };

    useEffect(() => { fetchTypes(); }, []);

    const selectType = (type) => {
        setSelectedType(type);
        fetchEntities(type);
    };

    const closeModal = () => { setModal(null); setTargetItem(null); setNewName(''); setError(''); };

    // ── Create entity type
    const handleCreateType = async (e) => {
        e.preventDefault();
        setError('');
        setSubmitting(true);
        try {
            await api.post('/admin/entity-types', { name: newName });
            closeModal();
            fetchTypes();
        } catch (err) {
            setError(err.response?.data?.message ?? 'Error creating type.');
        } finally {
            setSubmitting(false);
        }
    };

    // ── Delete entity type
    const handleDeleteType = async () => {
        setSubmitting(true);
        try {
            await api.delete(`/admin/entity-types/${targetItem.id}`);
            if (selectedType?.id === targetItem.id) { setSelectedType(null); setEntities([]); }
            closeModal();
            fetchTypes();
        } catch {
            setError('Error deleting type.');
        } finally {
            setSubmitting(false);
        }
    };

    // ── Create entity value
    const handleCreateEntity = async (e) => {
        e.preventDefault();
        setError('');
        setSubmitting(true);
        try {
            await api.post(`/admin/entity-types/${selectedType.id}/entities`, { name: newName });
            closeModal();
            fetchEntities(selectedType);
        } catch (err) {
            setError(err.response?.data?.message ?? 'Error creating value.');
        } finally {
            setSubmitting(false);
        }
    };

    // ── Delete entity value
    const handleDeleteEntity = async () => {
        setSubmitting(true);
        try {
            await api.delete(`/admin/entities/${targetItem.id}`);
            closeModal();
            fetchEntities(selectedType);
        } catch {
            setError('Error deleting value.');
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <div className="max-w-5xl mx-auto space-y-6">
            <div>
                <h1 className="text-2xl font-bold text-gray-800">Taxonomy</h1>
                <p className="text-sm text-gray-500 mt-1">
                    Entity types (e.g. "Class", "Department") and their values (e.g. "Grade 10", "Engineering").
                </p>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                {/* ── Left: Entity Types ── */}
                <div className="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                    <div className="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                        <h2 className="text-sm font-semibold text-gray-700">Entity Types</h2>
                        <button
                            onClick={() => { setNewName(''); setError(''); setModal('type'); }}
                            className="text-xs bg-blue-600 hover:bg-blue-700 text-white font-medium px-3 py-1.5 rounded-lg"
                        >
                            + New Type
                        </button>
                    </div>

                    {loadingTypes ? (
                        <div className="p-4 space-y-2">
                            {[1,2,3].map((i) => <div key={i} className="h-10 bg-gray-100 animate-pulse rounded" />)}
                        </div>
                    ) : types.length === 0 ? (
                        <p className="text-xs text-gray-400 text-center py-10">No entity types yet.</p>
                    ) : (
                        <ul className="divide-y divide-gray-50">
                            {types.map((t) => (
                                <li
                                    key={t.id}
                                    onClick={() => selectType(t)}
                                    className={`flex items-center justify-between px-5 py-3 cursor-pointer transition-colors ${
                                        selectedType?.id === t.id
                                            ? 'bg-blue-50 border-l-2 border-blue-500'
                                            : 'hover:bg-gray-50 border-l-2 border-transparent'
                                    }`}
                                >
                                    <div>
                                        <p className="text-sm font-medium text-gray-800">{t.name}</p>
                                        <p className="text-xs text-gray-400">{t.entities_count} value{t.entities_count !== 1 ? 's' : ''}</p>
                                    </div>
                                    <button
                                        onClick={(e) => { e.stopPropagation(); setTargetItem(t); setError(''); setModal('deleteType'); }}
                                        className="text-gray-300 hover:text-red-500 transition-colors p-1"
                                    >
                                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>

                {/* ── Right: Entity Values ── */}
                <div className="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                    <div className="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                        <h2 className="text-sm font-semibold text-gray-700">
                            {selectedType ? `"${selectedType.name}" values` : 'Select a type →'}
                        </h2>
                        {selectedType && (
                            <button
                                onClick={() => { setNewName(''); setError(''); setModal('entity'); }}
                                className="text-xs bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-3 py-1.5 rounded-lg"
                            >
                                + Add Value
                            </button>
                        )}
                    </div>

                    {!selectedType ? (
                        <p className="text-xs text-gray-400 text-center py-10">
                            Click an entity type on the left to manage its values.
                        </p>
                    ) : loadingEntities ? (
                        <div className="p-4 space-y-2">
                            {[1,2,3].map((i) => <div key={i} className="h-8 bg-gray-100 animate-pulse rounded" />)}
                        </div>
                    ) : entities.length === 0 ? (
                        <p className="text-xs text-gray-400 text-center py-10">No values yet. Add the first one.</p>
                    ) : (
                        <ul className="divide-y divide-gray-50">
                            {entities.map((e) => (
                                <li key={e.id} className="flex items-center justify-between px-5 py-3 hover:bg-gray-50">
                                    <div>
                                        <p className="text-sm text-gray-800">{e.name}</p>
                                        {e.users_count != null && (
                                            <p className="text-xs text-gray-400">{e.users_count} user{e.users_count !== 1 ? 's' : ''}</p>
                                        )}
                                    </div>
                                    <button
                                        onClick={() => { setTargetItem(e); setError(''); setModal('deleteEntity'); }}
                                        className="text-gray-300 hover:text-red-500 transition-colors p-1"
                                    >
                                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </div>

            {/* ── Modals ── */}

            {/* Create type */}
            <Modal open={modal === 'type'} onClose={closeModal} title="New Entity Type" maxWidth="max-w-sm">
                <form onSubmit={handleCreateType} className="space-y-4">
                    <div>
                        <label className="block text-xs font-medium text-gray-600 mb-1">Type Name</label>
                        <input autoFocus required value={newName} onChange={(e) => setNewName(e.target.value)}
                            placeholder='e.g. "Class", "Department"'
                            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                    </div>
                    {error && <p className="text-xs text-red-600">{error}</p>}
                    <div className="flex justify-end gap-3">
                        <button type="button" onClick={closeModal}
                            className="px-4 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" disabled={submitting}
                            className="px-4 py-2 text-sm bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white rounded-lg font-medium">
                            {submitting ? 'Creating…' : 'Create'}
                        </button>
                    </div>
                </form>
            </Modal>

            {/* Create entity value */}
            <Modal open={modal === 'entity'} onClose={closeModal} title={`Add value to "${selectedType?.name}"`} maxWidth="max-w-sm">
                <form onSubmit={handleCreateEntity} className="space-y-4">
                    <div>
                        <label className="block text-xs font-medium text-gray-600 mb-1">Value Name</label>
                        <input autoFocus required value={newName} onChange={(e) => setNewName(e.target.value)}
                            placeholder='e.g. "Grade 10", "Engineering"'
                            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                    </div>
                    {error && <p className="text-xs text-red-600">{error}</p>}
                    <div className="flex justify-end gap-3">
                        <button type="button" onClick={closeModal}
                            className="px-4 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" disabled={submitting}
                            className="px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 disabled:bg-indigo-400 text-white rounded-lg font-medium">
                            {submitting ? 'Adding…' : 'Add Value'}
                        </button>
                    </div>
                </form>
            </Modal>

            {/* Delete type confirm */}
            <Modal open={modal === 'deleteType'} onClose={closeModal} title="Delete Entity Type" maxWidth="max-w-sm">
                <p className="text-sm text-gray-600">
                    Delete <strong>{targetItem?.name}</strong>? All its values and user assignments will also be removed.
                </p>
                {error && <p className="mt-2 text-xs text-red-600">{error}</p>}
                <div className="flex justify-end gap-3 mt-6">
                    <button onClick={closeModal}
                        className="px-4 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
                    <button onClick={handleDeleteType} disabled={submitting}
                        className="px-4 py-2 text-sm bg-red-600 hover:bg-red-700 disabled:bg-red-400 text-white rounded-lg font-medium">
                        {submitting ? 'Deleting…' : 'Delete'}
                    </button>
                </div>
            </Modal>

            {/* Delete entity value confirm */}
            <Modal open={modal === 'deleteEntity'} onClose={closeModal} title="Delete Value" maxWidth="max-w-sm">
                <p className="text-sm text-gray-600">
                    Delete value <strong>{targetItem?.name}</strong>? Users assigned here will lose this tag.
                </p>
                {error && <p className="mt-2 text-xs text-red-600">{error}</p>}
                <div className="flex justify-end gap-3 mt-6">
                    <button onClick={closeModal}
                        className="px-4 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
                    <button onClick={handleDeleteEntity} disabled={submitting}
                        className="px-4 py-2 text-sm bg-red-600 hover:bg-red-700 disabled:bg-red-400 text-white rounded-lg font-medium">
                        {submitting ? 'Deleting…' : 'Delete'}
                    </button>
                </div>
            </Modal>
        </div>
    );
}
