'use client';

import { useState, useEffect, useCallback } from 'react';
import { api } from '@/lib/api';
import type { User } from '@/lib/types';

export function useAuth() {
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const token = api.getToken();
    if (token) {
      // Token exists — try to fetch current user
      api.get<{ data: User }>('/auth/me')
        .then((res) => setUser(res.data))
        .catch(() => {
          // Token expired or invalid — auto-login with demo credentials
          autoLogin();
        })
        .finally(() => setLoading(false));
    } else {
      // No token — auto-login with demo credentials
      autoLogin().finally(() => setLoading(false));
    }
  }, []);

  const autoLogin = async () => {
    try {
      const res = await api.login('alice@example.com', 'password');
      api.setToken(res.access_token);
      setUser(res.user);
    } catch {
      // Backend not available — fall back to demo user
      setUser({
        id: 1,
        name: 'Sarah Chen',
        email: 'sarah@lattice.dev',
        role: 'admin',
      });
    }
  };

  const login = useCallback(async (email: string, password: string) => {
    const res = await api.login(email, password);
    api.setToken(res.access_token);
    setUser(res.user);
  }, []);

  const logout = useCallback(() => {
    api.setToken(null);
    setUser(null);
    if (typeof window !== 'undefined') {
      window.location.href = '/login';
    }
  }, []);

  return {
    user,
    isAuthenticated: !!user,
    login,
    logout,
    loading,
  };
}
