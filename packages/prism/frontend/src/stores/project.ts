import { create } from "zustand";
import { persist } from "zustand/middleware";
import type { IssueLevel, IssueStatus } from "@/schemas/issue";

interface FilterState {
  status: IssueStatus | "";
  level: IssueLevel | "";
  environment: string;
  search: string;
  sort: string;
  dir: string;
}

interface ProjectStore {
  // Selected project
  selectedProjectId: string | undefined;
  setSelectedProjectId: (id: string | undefined) => void;

  // Filters
  filters: FilterState;
  setFilter: <K extends keyof FilterState>(
    key: K,
    value: FilterState[K],
  ) => void;
  resetFilters: () => void;

  // Pagination
  page: number;
  pageSize: number;
  setPage: (page: number) => void;
  setPageSize: (size: number) => void;

  // Bulk selection
  selectedIssueIds: Set<string>;
  toggleIssueSelection: (id: string) => void;
  selectAllIssues: (ids: string[]) => void;
  clearSelection: () => void;

  // Live feed
  liveFeedPaused: boolean;
  toggleLiveFeedPause: () => void;
  liveFeedSoundEnabled: boolean;
  toggleLiveFeedSound: () => void;
}

const defaultFilters: FilterState = {
  status: "",
  level: "",
  environment: "",
  search: "",
  sort: "last_seen",
  dir: "desc",
};

export const useProjectStore = create<ProjectStore>()(
  persist(
    (set) => ({
      selectedProjectId: undefined,
      setSelectedProjectId: (id) =>
        set({ selectedProjectId: id, page: 0, selectedIssueIds: new Set() }),

      filters: { ...defaultFilters },
      setFilter: (key, value) =>
        set((state) => ({
          filters: { ...state.filters, [key]: value },
          page: 0,
        })),
      resetFilters: () =>
        set({ filters: { ...defaultFilters }, page: 0 }),

      page: 0,
      pageSize: 25,
      setPage: (page) => set({ page }),
      setPageSize: (pageSize) => set({ pageSize, page: 0 }),

      selectedIssueIds: new Set(),
      toggleIssueSelection: (id) =>
        set((state) => {
          const next = new Set(state.selectedIssueIds);
          if (next.has(id)) {
            next.delete(id);
          } else {
            next.add(id);
          }
          return { selectedIssueIds: next };
        }),
      selectAllIssues: (ids) =>
        set({ selectedIssueIds: new Set(ids) }),
      clearSelection: () => set({ selectedIssueIds: new Set() }),

      liveFeedPaused: false,
      toggleLiveFeedPause: () =>
        set((state) => ({ liveFeedPaused: !state.liveFeedPaused })),
      liveFeedSoundEnabled: false,
      toggleLiveFeedSound: () =>
        set((state) => ({
          liveFeedSoundEnabled: !state.liveFeedSoundEnabled,
        })),
    }),
    {
      name: "prism-project-store",
      partialize: (state) => ({
        selectedProjectId: state.selectedProjectId,
        pageSize: state.pageSize,
        liveFeedSoundEnabled: state.liveFeedSoundEnabled,
      }),
    },
  ),
);
