import { create } from 'zustand';
import type { EntryType } from '@/schemas/entry';
import type { TimePeriod } from '@/schemas/metrics';

interface FiltersState {
  // Dev mode filters
  entryType: EntryType;
  search: string;
  statusFilter: number | null;
  methodFilter: string | null;
  levelFilter: string | null;
  slowOnly: boolean;

  // Shared filters
  timeRange: TimePeriod;
  page: number;
  pageSize: number;

  // Actions
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
  entryType: 'request' as EntryType,
  search: '',
  statusFilter: null,
  methodFilter: null,
  levelFilter: null,
  slowOnly: false,
  timeRange: '1h' as TimePeriod,
  page: 0,
  pageSize: 50,
};

export const useFiltersStore = create<FiltersState>((set) => ({
  ...defaultFilters,

  setEntryType: (entryType) => set({ entryType, page: 0, search: '' }),
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
