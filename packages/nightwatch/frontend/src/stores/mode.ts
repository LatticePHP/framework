import { create } from 'zustand';

type AppMode = 'dev' | 'prod';
type Theme = 'dark' | 'light';

interface ModeState {
  mode: AppMode;
  enabled: boolean;
  theme: Theme;
  loading: boolean;
  setMode: (mode: AppMode) => void;
  setEnabled: (enabled: boolean) => void;
  toggleTheme: () => void;
  setTheme: (theme: Theme) => void;
  setLoading: (loading: boolean) => void;
}

const getInitialTheme = (): Theme => {
  if (typeof window === 'undefined') return 'dark';
  const stored = localStorage.getItem('nightwatch-theme');
  if (stored === 'light' || stored === 'dark') return stored;
  return window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
};

export const useModeStore = create<ModeState>((set) => ({
  mode: 'dev',
  enabled: true,
  theme: getInitialTheme(),
  loading: true,

  setMode: (mode) => set({ mode }),
  setEnabled: (enabled) => set({ enabled }),

  toggleTheme: () =>
    set((state) => {
      const next = state.theme === 'dark' ? 'light' : 'dark';
      localStorage.setItem('nightwatch-theme', next);
      return { theme: next };
    }),

  setTheme: (theme) => {
    localStorage.setItem('nightwatch-theme', theme);
    set({ theme });
  },

  setLoading: (loading) => set({ loading }),
}));
