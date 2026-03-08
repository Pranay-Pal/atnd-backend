import React, { useEffect, useState, useCallback } from 'react';
import api from '../utils/axios';
import Modal from '../components/Modal';

const EMPTY_FORM = {
    name: '', member_uid: '',
};

function EntityBadge({ entities }) {
    if (!entities?.length) return <span className="text-gray-300 text-xs">—</span>;
    return (
        <div className="flex flex-wrap gap-1">
            {entities.map((e) => (
                <span
                    key={e.id}
                    className="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-blue-50 text-blue-700 font-medium"
                    title={e.type}
                >
                    {e.value}
                </span>
            ))}
        </div>
    );
}

function FaceIcon({ enrolled }) {
    return enrolled === true ? (
        <span className="inline-flex items-center gap-1 text-xs text-emerald-600 font-medium">
            <svg className="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414L8.414 15 3.293 9.879a1 1 0 111.414-1.414L8.414 12.172l6.879-6.879a1 1 0 011.414 0z" clipRule="evenodd" />
            </svg>
            Enrolled
        </span>
    ) : (
        <span className="text-xs text-gray-400">Not enrolled</span>
    );
}

export default function Users() {
    const [users, setUsers] = useState([]);
    const [meta, setMeta] = useState({ total: 0, last_page: 1, current_page: 1 });
    const [loading, setLoading] = useState(true);

    // Filters
    const [search, setSearch] = useState('');
    const [entityTypes, setEntityTypes] = useState([]);
    const [entityValues, setEntityValues] = useState([]);
    const [filterTypeId, setFilterTypeId] = useState('');
    const [filterEntityId, setFilterEntityId] = useState('');
    const [page, setPage] = useState(1);

    // Modals
    const [modal, setModal] = useState(null); // 'create' | 'edit' | 'delete' | 'entities'
    const [selected, setSelected] = useState(null);
    const [form, setForm] = useState(EMPTY_FORM);
    const [entityChecked, setEntityChecked] = useState([]);
    const [allEntitiesForSync, setAllEntitiesForSync] = useState([]);
    const [submitting, setSubmitting] = useState(false);
    const [formError, setFormError] = useState('');

    // Load entity types once for filter bar, with eager-loaded values
    useEffect(() => {
        api.get('/admin/entity-types', { params: { with_entities: true } })
            .then((r) => setEntityTypes(r.data)).catch(() => { });
    }, []);

    // When filter type changes, derive its values synchronously
    useEffect(() => {
        setFilterEntityId('');
        setEntityValues([]);
        if (!filterTypeId) return;
        const type = entityTypes.find((t) => String(t.id) === String(filterTypeId));
        if (type) setEntityValues(type.entities || []);
    }, [filterTypeId, entityTypes]);

    const fetchUsers = useCallback(() => {
        setLoading(true);
        const params = { page, per_page: 15 };
        if (search) params.search = search;
        if (filterEntityId) params.entity_id = filterEntityId;
        else if (filterTypeId) params.entity_type_id = filterTypeId;

        api.get('/admin/users', { params })
            .then((r) => {
                setUsers(r.data.data ?? []);
                setMeta({ total: r.data.total, last_page: r.data.last_page, current_page: r.data.current_page });
            })
            .catch(() => { })
            .finally(() => setLoading(false));
    }, [page, search, filterEntityId, filterTypeId]);

    useEffect(() => { fetchUsers(); }, [fetchUsers]);

    // ── Helpers ──────────────────────────────────────────
    const openCreate = () => { setForm(EMPTY_FORM); setFormError(''); setModal('create'); };

    const openEdit = (user) => {
        setSelected(user);
        setForm({
            name: user.name,
            member_uid: user.member_uid ?? '',
        });
        setFormError('');
        setModal('edit');
    };

    const openDelete = (user) => { setSelected(user); setModal('delete'); };

    const openEntities = async (user) => {
        setSelected(user);
        setFormError('');
        // Load the entire nested taxonomy tree in 1 request
        try {
            const typesRes = await api.get('/admin/entity-types', { params: { with_entities: true } });
            const allValues = typesRes.data.map((t) => ({
                type: t.name, typeId: t.id, entities: t.entities || [],
            }));
            setAllEntitiesForSync(allValues);
            setEntityChecked((user.entities ?? []).map((e) => e.id));
            setModal('entities');
        } catch {
            setFormError('Failed to load taxonomy tree.');
        }
    };

    const closeModal = () => { setModal(null); setSelected(null); };

    const handleFormChange = (e) => setForm({ ...form, [e.target.name]: e.target.value });

    const handleCreate = async (e) => {
        e.preventDefault();
        setFormError('');
        setSubmitting(true);
        try {
            const body = { ...form };
            if (!body.member_uid) delete body.member_uid;
            await api.post('/admin/users', body);
            closeModal();
            fetchUsers();
        } catch (err) {
            const msgs = err.response?.data?.errors;
            setFormError(msgs ? Object.values(msgs).flat().join(' ') : (err.response?.data?.message ?? 'Error'));
        } finally {
            setSubmitting(false);
        }
    };

    const handleUpdate = async (e) => {
        e.preventDefault();
        setFormError('');
        setSubmitting(true);
        try {
            const body = { ...form };
            if (!body.member_uid) delete body.member_uid;
            await api.put(`/admin/users/${selected.id}`, body);
            closeModal();
            fetchUsers();
        } catch (err) {
            const msgs = err.response?.data?.errors;
            setFormError(msgs ? Object.values(msgs).flat().join(' ') : (err.response?.data?.message ?? 'Error'));
        } finally {
            setSubmitting(false);
        }
    };

    const handleDelete = async () => {
        setSubmitting(true);
        try {
            await api.delete(`/admin/users/${selected.id}`);
            closeModal();
            fetchUsers();
        } catch {
            // ignore
        } finally {
            setSubmitting(false);
        }
    };

    const toggleEntity = (id) => {
        setEntityChecked((prev) =>
            prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]
        );
    };

    const handleSyncEntities = async () => {
        setSubmitting(true);
        try {
            await api.post(`/admin/users/${selected.id}/entities`, { entity_ids: entityChecked });
            closeModal();
            fetchUsers();
        } catch (err) {
            setFormError(err.response?.data?.message ?? 'Error saving groups.');
        } finally {
            setSubmitting(false);
        }
    };

    // ── Form shared markup ──────────────────────────────
    const UserForm = ({ onSubmit, submitLabel }) => (
        <form onSubmit={onSubmit} className="space-y-4">
            <div className="grid grid-cols-2 gap-4">
                <div className="col-span-2">
                    <label className="block text-xs font-medium text-gray-600 mb-1">Full Name *</label>
                    <input name="name" required value={form.name} onChange={handleFormChange}
                        className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                </div>
                <div className="col-span-2">
                    <label className="block text-xs font-medium text-gray-600 mb-1">Member UID</label>
                    <input name="member_uid" value={form.member_uid} onChange={handleFormChange}
                        className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                </div>
            </div>
            {formError && (
                <p className="text-xs text-red-600 bg-red-50 border border-red-200 rounded px-3 py-2">{formError}</p>
            )}
            <div className="flex justify-end gap-3 pt-2">
                <button type="button" onClick={closeModal}
                    className="px-4 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit" disabled={submitting}
                    className="px-4 py-2 text-sm bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white rounded-lg font-medium">
                    {submitting ? 'Saving…' : submitLabel}
                </button>
            </div>
        </form>
    );

    return (
        <div className="max-w-7xl mx-auto space-y-6">
            {/* Header */}
            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-gray-800">Users</h1>
                    <p className="text-sm text-gray-500 mt-1">{meta.total} total</p>
                </div>
                <button onClick={openCreate}
                    className="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors">
                    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                    </svg>
                    Add User
                </button>
            </div>

            {/* Filters */}
            <div className="bg-white rounded-xl border border-gray-100 shadow-sm p-4 flex flex-wrap gap-3">
                <input
                    type="search"
                    placeholder="Search name, email, ID…"
                    value={search}
                    onChange={(e) => { setSearch(e.target.value); setPage(1); }}
                    className="flex-1 min-w-48 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
                <select
                    value={filterTypeId}
                    onChange={(e) => { setFilterTypeId(e.target.value); setPage(1); }}
                    className="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                    <option value="">All Types</option>
                    {entityTypes.map((t) => <option key={t.id} value={t.id}>{t.name}</option>)}
                </select>
                {entityValues.length > 0 && (
                    <select
                        value={filterEntityId}
                        onChange={(e) => { setFilterEntityId(e.target.value); setPage(1); }}
                        className="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                        <option value="">All {entityTypes.find((t) => String(t.id) === filterTypeId)?.name}</option>
                        {entityValues.map((v) => <option key={v.id} value={v.id}>{v.name}</option>)}
                    </select>
                )}
            </div>

            {/* Table */}
            <div className="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                {loading ? (
                    <div className="p-6 space-y-3">
                        {[1, 2, 3, 4, 5].map((i) => <div key={i} className="h-12 bg-gray-100 animate-pulse rounded" />)}
                    </div>
                ) : users.length === 0 ? (
                    <p className="text-sm text-gray-400 text-center py-16">No users found.</p>
                ) : (
                    <table className="w-full text-sm">
                        <thead className="bg-gray-50 border-b border-gray-100">
                            <tr>
                                <th className="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th className="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Groups</th>
                                <th className="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Face</th>
                                <th className="px-6 py-3" />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50">
                            {users.map((user) => (
                                <tr key={user.id} className="hover:bg-gray-50 transition-colors">
                                    <td className="px-6 py-4">
                                        <div className="flex items-center gap-3">
                                            <div className="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 font-semibold text-xs shrink-0">
                                                {user.name.charAt(0).toUpperCase()}
                                            </div>
                                            <div>
                                                <p className="font-medium text-gray-900">{user.name}</p>
                                                {user.member_uid && <p className="text-xs text-gray-400">UID: {user.member_uid}</p>}
                                            </div>
                                        </div>
                                    </td>
                                    <td className="px-6 py-4"><EntityBadge entities={user.entities} /></td>
                                    <td className="px-6 py-4"><FaceIcon enrolled={user.has_face_enrolled} /></td>
                                    <td className="px-6 py-4">
                                        <div className="flex items-center justify-end gap-2">
                                            <button onClick={() => openEntities(user)}
                                                className="text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                                                Groups
                                            </button>
                                            <button onClick={() => openEdit(user)}
                                                className="text-xs text-blue-600 hover:text-blue-800 font-medium">
                                                Edit
                                            </button>
                                            <button onClick={() => openDelete(user)}
                                                className="text-xs text-red-500 hover:text-red-700 font-medium">
                                                Delete
                                            </button>
                                        </div>
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

            {/* Create modal */}
            <Modal open={modal === 'create'} onClose={closeModal} title="Add User">
                <UserForm onSubmit={handleCreate} submitLabel="Create User" />
            </Modal>

            {/* Edit modal */}
            <Modal open={modal === 'edit'} onClose={closeModal} title={`Edit — ${selected?.name}`}>
                <UserForm onSubmit={handleUpdate} submitLabel="Save Changes" />
            </Modal>

            {/* Delete confirm */}
            <Modal open={modal === 'delete'} onClose={closeModal} title="Delete User" maxWidth="max-w-sm">
                <p className="text-sm text-gray-600">
                    Are you sure you want to delete <strong>{selected?.name}</strong>? This cannot be undone.
                </p>
                {formError && <p className="mt-3 text-xs text-red-600">{formError}</p>}
                <div className="flex justify-end gap-3 mt-6">
                    <button onClick={closeModal}
                        className="px-4 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">
                        Cancel
                    </button>
                    <button onClick={handleDelete} disabled={submitting}
                        className="px-4 py-2 text-sm bg-red-600 hover:bg-red-700 disabled:bg-red-400 text-white rounded-lg font-medium">
                        {submitting ? 'Deleting…' : 'Delete'}
                    </button>
                </div>
            </Modal>

            {/* Entity sync modal */}
            <Modal open={modal === 'entities'} onClose={closeModal} title={`Assign Groups — ${selected?.name}`} maxWidth="max-w-md">
                <div className="space-y-5">
                    {allEntitiesForSync.map(({ type, entities }) => (
                        <div key={type}>
                            <p className="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">{type}</p>
                            <div className="space-y-1.5">
                                {entities.map((e) => (
                                    <label key={e.id} className="flex items-center gap-3 cursor-pointer group">
                                        <input
                                            type="checkbox"
                                            checked={entityChecked.includes(e.id)}
                                            onChange={() => toggleEntity(e.id)}
                                            className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                        />
                                        <span className="text-sm text-gray-700 group-hover:text-gray-900">{e.name}</span>
                                    </label>
                                ))}
                                {entities.length === 0 && (
                                    <p className="text-xs text-gray-400 italic">No values defined yet.</p>
                                )}
                            </div>
                        </div>
                    ))}
                    {allEntitiesForSync.length === 0 && (
                        <p className="text-sm text-gray-400 text-center py-4">No entity types configured.</p>
                    )}
                </div>
                {formError && <p className="mt-3 text-xs text-red-600">{formError}</p>}
                <div className="flex justify-end gap-3 mt-6">
                    <button onClick={closeModal}
                        className="px-4 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">
                        Cancel
                    </button>
                    <button onClick={handleSyncEntities} disabled={submitting}
                        className="px-4 py-2 text-sm bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white rounded-lg font-medium">
                        {submitting ? 'Saving…' : 'Save Groups'}
                    </button>
                </div>
            </Modal>
        </div>
    );
}
