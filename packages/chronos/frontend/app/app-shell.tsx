"use client";

import { Timer, Workflow, BarChart3 } from "lucide-react";
import { DashboardLayout, type NavItem } from "@/components/dashboard/dashboard-layout";

const navItems: NavItem[] = [
  {
    title: "Workflows",
    href: "/workflows",
    icon: Workflow,
  },
  {
    title: "Statistics",
    href: "/stats",
    icon: BarChart3,
  },
];

export function AppShell({ children }: { children: React.ReactNode }) {
  return (
    <DashboardLayout appName="Chronos" appIcon={Timer} navItems={navItems}>
      {children}
    </DashboardLayout>
  );
}
