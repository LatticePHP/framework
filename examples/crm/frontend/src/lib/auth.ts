'use client';

import { createContext, useContext } from 'react';
import type { User } from './types';

export interface AuthContextType {
  user: User | null;
  isAuthenticated: boolean;
  login: (email: string, password: string) => Promise<void>;
  logout: () => void;
  loading: boolean;
}

export const AuthContext = createContext<AuthContextType>({
  user: null,
  isAuthenticated: false,
  login: async () => {},
  logout: () => {},
  loading: true,
});

export function useAuthContext() {
  return useContext(AuthContext);
}

// Demo user for development when backend is not available
export const DEMO_USER: User = {
  id: 1,
  name: 'Sarah Chen',
  email: 'sarah@lattice.dev',
  role: 'admin',
};
