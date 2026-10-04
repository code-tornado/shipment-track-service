import { createContext, useCallback, useContext, useEffect, useState, type ReactNode } from 'react';
import { api, token } from './api';
import type { User } from './types';

interface AuthState {
    user: User | null;
    loading: boolean;
    login: (email: string, password: string) => Promise<void>;
    loginWithToken: (value: string) => Promise<void>;
    logout: () => Promise<void>;
}

const AuthContext = createContext<AuthState | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
    const [user, setUser] = useState<User | null>(null);
    const [loading, setLoading] = useState(true);

    const loadUser = useCallback(async () => {
        if (!token.get()) {
            setUser(null);
            setLoading(false);
            return;
        }
        try {
            const { user } = await api<{ user: User }>('/auth/me');
            setUser(user);
        } catch {
            setUser(null);
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        void loadUser();
        const onUnauthenticated = () => setUser(null);
        window.addEventListener('sts:unauthenticated', onUnauthenticated);
        return () => window.removeEventListener('sts:unauthenticated', onUnauthenticated);
    }, [loadUser]);

    const login = async (email: string, password: string) => {
        const result = await api<{ token: string; user: User }>('/auth/token', {
            method: 'POST',
            body: { email, password, device_name: 'web-ui' },
        });
        token.set(result.token);
        setUser(result.user);
    };

    const loginWithToken = async (value: string) => {
        token.set(value.trim());
        try {
            const { user } = await api<{ user: User }>('/auth/me');
            setUser(user);
        } catch (e) {
            token.set(null);
            throw e;
        }
    };

    const logout = async () => {
        try {
            await api('/auth/token', { method: 'DELETE' });
        } catch {
            /* token may already be gone */
        }
        token.set(null);
        setUser(null);
    };

    return <AuthContext.Provider value={{ user, loading, login, loginWithToken, logout }}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthState {
    const ctx = useContext(AuthContext);
    if (!ctx) throw new Error('useAuth outside AuthProvider');
    return ctx;
}
