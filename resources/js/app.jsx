import './bootstrap';

import React from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';

import { AuthProvider, useAuth } from './context/AuthContext';
import ProtectedRoute from './components/ProtectedRoute';

import AdminLayout from './layouts/AdminLayout';
import Login from './pages/Login';
import Dashboard from './pages/Dashboard';
import Users from './pages/Users';
import Reports from './pages/Reports';
import EntityTypes from './pages/EntityTypes';

import SuperAdminLayout from './layouts/SuperAdminLayout';
import OrganizationList from './pages/organizations/OrganizationList';
import OrganizationForm from './pages/organizations/OrganizationForm';

function App() {
    return (
        <BrowserRouter>
            <AuthProvider>
                <Routes>
                    {/* Public */}
                    <Route path="/login" element={<Login />} />

                    {/* ROOT REDIRECT */}
                    <Route
                        path="/"
                        element={
                            <ProtectedRoute>
                                <RoleRedirector />
                            </ProtectedRoute>
                        }
                    />

                    {/* SUPER ADMIN ROUTES */}
                    <Route
                        path="/super-admin"
                        element={
                            <ProtectedRoute allowedRoles={['admin']}>
                                <SuperAdminLayout />
                            </ProtectedRoute>
                        }
                    >
                        {/* We reuse the Dashboard and just change UI based on layout */}
                        <Route index element={<Navigate to="tenants" replace />} />
                        <Route path="tenants" element={<OrganizationList />} />
                        <Route path="tenants/new" element={<OrganizationForm />} />
                    </Route>

                    {/* ORGANIZATION ADMIN ROUTES */}
                    <Route
                        path="/admin"
                        element={
                            <ProtectedRoute allowedRoles={['organisation']}>
                                <AdminLayout />
                            </ProtectedRoute>
                        }
                    >
                        <Route index element={<Dashboard />} />
                        <Route path="users" element={<Users />} />
                        <Route path="reports" element={<Reports />} />
                        <Route path="entity-types" element={<EntityTypes />} />
                    </Route>

                    {/* Catch-all */}
                    <Route path="*" element={<Navigate to="/" replace />} />
                </Routes>
            </AuthProvider>
        </BrowserRouter>
    );
}

function RoleRedirector() {
    const { user } = useAuth() || { user: null };
    if (!user) return <Navigate to="/login" replace />;
    if (user.role === 'admin') return <Navigate to="/super-admin" replace />;
    return <Navigate to="/admin" replace />;
}

if (document.getElementById('app')) {
    createRoot(document.getElementById('app')).render(<App />);
}
