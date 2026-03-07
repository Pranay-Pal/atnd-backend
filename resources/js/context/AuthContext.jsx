import React, { createContext, useContext, useState } from 'react';
import api from '../utils/axios';

export const AuthContext = createContext(null);

export function AuthProvider({ children }) {
    const [user, setUser] = useState(() => {
        try {
            const u = localStorage.getItem('admin_user');
            return u ? JSON.parse(u) : null;
        } catch {
            return null;
        }
    });

    const login = async (email, password) => {
        const { data } = await api.post('/admin/login', { email, password });
        localStorage.setItem('token', data.token);
        localStorage.setItem('admin_user', JSON.stringify(data.user));
        setUser(data.user);
        return data;
    };

    const logout = async () => {
        try {
            await api.post('/admin/logout');
        } catch {
            // token may already be invalid; proceed with local clear
        }
        localStorage.removeItem('token');
        localStorage.removeItem('admin_user');
        setUser(null);
    };

    return (
        <AuthContext.Provider value={{ user, login, logout, isAuthenticated: !!user }}>
            {children}
        </AuthContext.Provider>
    );
}

export const useAuth = () => useContext(AuthContext);
