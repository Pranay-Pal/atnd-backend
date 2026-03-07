import React, { useState } from 'react';
import { Link, Outlet, useLocation, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';

const NAV = [
    {
        name: 'Organizations',
        path: '/super-admin/tenants',
        icon: (
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m3-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
        ),
    },
];

export default function SuperAdminLayout() {
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const location = useLocation();
    const navigate = useNavigate();
    const { user, logout } = useAuth();

    const handleLogout = async () => {
        await logout();
        navigate('/login', { replace: true });
    };

    const isActive = (path) => location.pathname.startsWith(path);

    const initials = user?.name
        ? user.name.split(' ').map((n) => n[0]).slice(0, 2).join('').toUpperCase()
        : 'S';

    return (
        <div className="h-screen flex overflow-hidden bg-gray-100">
            {/* Mobile overlay */}
            {sidebarOpen && (
                <div
                    className="fixed inset-0 z-20 bg-black/50 lg:hidden"
                    onClick={() => setSidebarOpen(false)}
                />
            )}

            {/* Sidebar */}
            <aside className={`fixed inset-y-0 left-0 z-30 w-64 bg-slate-900 shadow-xl flex flex-col transform transition-transform duration-300 lg:translate-x-0 lg:static lg:inset-0 ${sidebarOpen ? 'translate-x-0' : '-translate-x-full'}`}>
                {/* Brand */}
                <div className="flex items-center justify-center h-16 bg-slate-950 flex-shrink-0">
                    <span className="text-white text-xl font-bold tracking-wider">Genskytech Admin</span>
                </div>

                {/* Nav links */}
                <nav className="flex-1 mt-5 px-2 space-y-1 overflow-y-auto">
                    {NAV.map((item) => {
                        const active = isActive(item.path);
                        return (
                            <Link
                                key={item.name}
                                to={item.path}
                                onClick={() => setSidebarOpen(false)}
                                className={`group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors ${active
                                        ? 'bg-blue-600 text-white'
                                        : 'text-slate-300 hover:bg-slate-800 hover:text-white'
                                    }`}
                            >
                                <svg
                                    className={`w-5 h-5 mr-3 flex-shrink-0 ${active ? 'text-blue-200' : 'text-slate-400 group-hover:text-slate-300'}`}
                                    fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                >
                                    {item.icon}
                                </svg>
                                {item.name}
                            </Link>
                        );
                    })}
                </nav>

                {/* Sign out */}
                <div className="flex-shrink-0 p-4 border-t border-slate-800">
                    <button
                        onClick={handleLogout}
                        className="w-full flex items-center px-3 py-2.5 text-sm font-medium text-slate-400 hover:text-red-400 hover:bg-slate-800 rounded-lg transition-colors group"
                    >
                        <svg className="w-5 h-5 mr-3 text-slate-500 group-hover:text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                                d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                        Sign out
                    </button>
                </div>
            </aside>

            {/* Main content area */}
            <div className="flex-1 flex flex-col overflow-hidden">
                {/* Topbar */}
                <header className="flex items-center justify-between px-6 py-4 bg-white border-b border-gray-200 flex-shrink-0">
                    <button
                        onClick={() => setSidebarOpen(true)}
                        className="text-gray-500 focus:outline-none lg:hidden"
                    >
                        <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h11" />
                        </svg>
                    </button>

                    <span className="text-sm font-medium text-gray-400 hidden lg:block">
                        {NAV.find((n) => isActive(n.path))?.name ?? 'Organizations'}
                    </span>

                    {/* User pill */}
                    <div className="flex items-center gap-3">
                        <span className="text-sm text-gray-600 hidden sm:block">{user?.name ?? 'Platform Admin'}</span>
                        <div className="h-8 w-8 rounded-full bg-slate-800 flex items-center justify-center text-white text-xs font-bold">
                            {initials}
                        </div>
                    </div>
                </header>

                <main className="flex-1 overflow-x-hidden overflow-y-auto bg-gray-50 p-6">
                    <Outlet />
                </main>
            </div>
        </div>
    );
}
