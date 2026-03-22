import { create } from "zustand";
import type { EntryType, TimePeriod } from "./schemas";

// ── Mode store ──

type AppMode = "dev" | "prod";
type Theme = "dark" | "light";

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
  if (typeof window === "undefined") return "light";
  const stored = localStorage.getItem("nightwatch-theme");
  if (stored === "light" || stored === "dark") return stored;
  return window.matchMedia("(prefers-color-scheme: dark)").matches
    ? "dark"
    : "light";
};

export const useModeStore = create<ModeState>((set) => ({
  mode: "dev",
  enabled: true,
  theme: getInitialTheme(),
  loading: true,

  setMode: (mode) => set({ mode }),
  setEnabled: (enabled) => set({ enabled }),

  toggleTheme: () =>
    set((state) => {
      const next = state.theme === "dark" ? "light" : "dark";
      localStorage.setItem("nightwatch-theme", next);
      return { theme: next };
    }),

  setTheme: (theme) => {
    localStorage.setItem("nightwatch-theme", theme);
    set({ theme });
  },

  setLoading: (loading) => set({ loading }),
}));

// ── Filters store ──

interface FiltersState {
  entryType: EntryType;
  search: string;
  statusFilter: number | null;
  methodFilter: string | null;
  levelFilter: string | null;
  slowOnly: boolean;
  timeRange: TimePeriod;
  page: number;
  pageSize: number;

  setEntryType: (type: EntryType) => void;
  setSearch: (search: string) => void;
  setStatusFilter: (status: number | null) => void;
  setMethodFilter: (method: string | null) => void;
  setLevelFilter: (level: string | null) => void;
  setSlowOnly: (slow: boolean) => void;
  setTimeRange: (range: TimePeriod) => void;
  setPage: (page: number) => void;
  setPageSize: (size: number) => void;
  resetFilters: () => void;
}

const defaultFilters = {
  entryType: "request" as EntryType,
  search: "",
  statusFilter: null as number | null,
  methodFilter: null as string | null,
  levelFilter: null as string | null,
  slowOnly: false,
  timeRange: "1h" as TimePeriod,
  page: 0,
  pageSize: 50,
};

export const useFiltersStore = create<FiltersState>((set) => ({
  ...defaultFilters,

  setEntryType: (entryType) => set({ entryType, page: 0, search: "" }),
  setSearch: (search) => set({ search, page: 0 }),
  setStatusFilter: (statusFilter) => set({ statusFilter, page: 0 }),
  setMethodFilter: (methodFilter) => set({ methodFilter, page: 0 }),
  setLevelFilter: (levelFilter) => set({ levelFilter, page: 0 }),
  setSlowOnly: (slowOnly) => set({ slowOnly, page: 0 }),
  setTimeRange: (timeRange) => set({ timeRange, page: 0 }),
  setPage: (page) => set({ page }),
  setPageSize: (pageSize) => set({ pageSize, page: 0 }),
  resetFilters: () => set(defaultFilters),
}));
