import { create } from "zustand";
import { persist } from "zustand/middleware";

export type Period = "5m" | "1h" | "6h" | "24h" | "7d";
export type RefreshInterval = 0 | 5000 | 15000 | 30000 | 60000;

interface LoomStore {
  // Queue filter
  selectedQueue: string | null;
  setSelectedQueue: (queue: string | null) => void;

  // Search term
  searchTerm: string;
  setSearchTerm: (term: string) => void;

  // Period selector for metrics/dashboard
  period: Period;
  setPeriod: (period: Period) => void;

  // Auto-refresh interval (ms; 0 = off)
  refreshInterval: RefreshInterval;
  setRefreshInterval: (interval: RefreshInterval) => void;
}

export const useLoomStore = create<LoomStore>()(
  persist(
    (set) => ({
      selectedQueue: null,
      setSelectedQueue: (queue) => set({ selectedQueue: queue }),

      searchTerm: "",
      setSearchTerm: (term) => set({ searchTerm: term }),

      period: "1h",
      setPeriod: (period) => set({ period }),

      refreshInterval: 5000,
      setRefreshInterval: (interval) => set({ refreshInterval: interval }),
    }),
    {
      name: "loom-store",
      partialize: (state) => ({
        refreshInterval: state.refreshInterval,
        period: state.period,
      }),
    }
  )
);
