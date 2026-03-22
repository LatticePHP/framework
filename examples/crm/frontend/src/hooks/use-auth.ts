'use client';

import { useState, useEffect, useCallback } from 'react';
import { api } from '@/lib/api';
import { DEMO_USER } from '@/lib/auth';
import type { User } from '@/lib/types';

// Demo mode — when no backend is available, use mock data
const DEMO_MODE = true;

export function useAuth() {
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    // Check for existing token or demo mode
    if (DEMO_MODE) {
      setUser(DEMO_USER);
      setLoading(false);
      return;
    }

    const token = api.getToken();
    if (token) {
      setUser(DEMO_USER); // Would normally validate token
      setLoading(false);
    } else {
      setLoading(false);
    }
  }, []);

  const login = useCallback(async (email: string, password: string) => {
    if (DEMO_MODE) {
      setUser(DEMO_USER);
      return;
    }
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
