import { create } from "zustand";

interface PrismState {
  selectedProject: string | null;
  setSelectedProject: (projectId: string | null) => void;
}

export const usePrismStore = create<PrismState>((set) => ({
  selectedProject: null,
  setSelectedProject: (projectId) => set({ selectedProject: projectId }),
}));
