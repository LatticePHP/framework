import { create } from 'zustand';
import { persist } from 'zustand/middleware';

type Period = '5m' | '1h' | '6h' | '24h' | '7d';
type RefreshInterval = 0 | 5000 | 15000 | 30000 | 60000;

interface FiltersState {
  // Queue filter
  selectedQueue: string | null;
  setSelectedQueue: (queue: string | null) => void;

  // Job status filter
  jobStatusFilter: string | null;
  setJobStatusFilter: (status: string | null) => void;

  // Search term
  searchTerm: string;
  setSearchTerm: (term: string) => void;

  // Period selector for metrics/dashboard
  period: Period;
  setPeriod: (period: Period) => void;

  // Auto-refresh interval (ms; 0 = off)
  refreshInterval: RefreshInterval;
  setRefreshInterval: (interval: RefreshInterval) => void;

  // Theme
  theme: 'light' | 'dark';
  toggleTheme: () => void;

  // Sidebar collapsed
  sidebarCollapsed: boolean;
  toggleSidebar: () => void;
}

export const useFiltersStore = create<FiltersState>()(
  persist(
    (set) => ({
      selectedQueue: null,
      setSelectedQueue: (queue) => set({ selectedQueue: queue }),

      jobStatusFilter: null,
      setJobStatusFilter: (status) => set({ jobStatusFilter: status }),

      searchTerm: '',
      setSearchTerm: (term) => set({ searchTerm: term }),

      period: '1h',
      setPeriod: (period) => set({ period }),

      refreshInterval: 5000,
      setRefreshInterval: (interval) => set({ refreshInterval: interval }),

      theme: 'light',
      toggleTheme: () =>
        set((state) => ({
          theme: state.theme === 'light' ? 'dark' : 'light',
        })),

      sidebarCollapsed: false,
      toggleSidebar: () =>
        set((state) => ({ sidebarCollapsed: !state.sidebarCollapsed })),
    }),
    {
      name: 'loom-filters',
      partialize: (state) => ({
        theme: state.theme,
        refreshInterval: state.refreshInterval,
        sidebarCollapsed: state.sidebarCollapsed,
        period: state.period,
      }),
    },
  ),
);
