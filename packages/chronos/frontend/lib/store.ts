import { create } from "zustand";

export interface StoreState {
  // Workflow list filters
  statusFilter: string[];
  typeFilter: string;
  search: string;
  dateFrom: string;
  dateTo: string;
  sort: string;
  order: string;
  page: number;
  perPage: number;

  // UI state
  sidebarCollapsed: boolean;

  // Actions
  setStatusFilter: (statuses: string[]) => void;
  setTypeFilter: (type: string) => void;
  setSearch: (search: string) => void;
  setDateFrom: (from: string) => void;
  setDateTo: (to: string) => void;
  setSort: (sort: string) => void;
  setOrder: (order: string) => void;
  setPage: (page: number) => void;
  setPerPage: (perPage: number) => void;
  resetFilters: () => void;
  toggleSidebar: () => void;
}

export const useStore = create<StoreState>((set) => ({
  statusFilter: [],
  typeFilter: "",
  search: "",
  dateFrom: "",
  dateTo: "",
  sort: "started_at",
  order: "desc",
  page: 1,
  perPage: 20,
  sidebarCollapsed: false,

  setStatusFilter: (statuses) => set({ statusFilter: statuses, page: 1 }),
  setTypeFilter: (type) => set({ typeFilter: type, page: 1 }),
  setSearch: (search) => set({ search, page: 1 }),
  setDateFrom: (dateFrom) => set({ dateFrom, page: 1 }),
  setDateTo: (dateTo) => set({ dateTo, page: 1 }),
  setSort: (sort) => set({ sort }),
  setOrder: (order) => set({ order }),
  setPage: (page) => set({ page }),
  setPerPage: (perPage) => set({ perPage, page: 1 }),
  resetFilters: () =>
    set({
      statusFilter: [],
      typeFilter: "",
      search: "",
      dateFrom: "",
      dateTo: "",
      sort: "started_at",
      order: "desc",
      page: 1,
      perPage: 20,
    }),
  toggleSidebar: () =>
    set((state) => ({ sidebarCollapsed: !state.sidebarCollapsed })),
}));
