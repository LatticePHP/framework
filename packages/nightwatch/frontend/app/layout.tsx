"use client";

import "./globals.css";
import { useEffect } from "react";
import Link from "next/link";
import { usePathname } from "next/navigation";
import {
  Eye,
  Globe,
  Database,
  AlertTriangle,
  Zap,
  Package,
  Cog,
  Mail,
  FileText,
  BarChart3,
  Turtle,
  Sun,
  Moon,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { Separator } from "@/components/ui/separator";
import ModeSwitcher from "@/components/mode-switcher";
import TimeRangePicker from "@/components/time-range-picker";
import QueryProvider from "@/components/query-provider";
import { useModeStore } from "@/lib/store";
import { cn } from "@/lib/utils";

interface NavItem {
  key: string;
  label: string;
  icon: React.ReactNode;
  href: string;
}

const DEV_NAV: NavItem[] = [
  { key: "requests", label: "Requests", icon: <Globe className="h-4 w-4" />, href: "/nightwatch/dev/requests" },
  { key: "queries", label: "Queries", icon: <Database className="h-4 w-4" />, href: "/nightwatch/dev/queries" },
  { key: "exceptions", label: "Exceptions", icon: <AlertTriangle className="h-4 w-4" />, href: "/nightwatch/dev/exceptions" },
  { key: "events", label: "Events", icon: <Zap className="h-4 w-4" />, href: "/nightwatch/dev/events" },
  { key: "cache", label: "Cache", icon: <Package className="h-4 w-4" />, href: "/nightwatch/dev/cache" },
  { key: "jobs", label: "Jobs", icon: <Cog className="h-4 w-4" />, href: "/nightwatch/dev/jobs" },
  { key: "mail", label: "Mail", icon: <Mail className="h-4 w-4" />, href: "/nightwatch/dev/mail" },
  { key: "logs", label: "Logs", icon: <FileText className="h-4 w-4" />, href: "/nightwatch/dev/logs" },
];

const PROD_NAV: NavItem[] = [
  { key: "overview", label: "Overview", icon: <BarChart3 className="h-4 w-4" />, href: "/nightwatch/prod/overview" },
  { key: "slow-requests", label: "Slow Requests", icon: <Turtle className="h-4 w-4" />, href: "/nightwatch/prod/slow-requests" },
  { key: "slow-queries", label: "Slow Queries", icon: <Database className="h-4 w-4" />, href: "/nightwatch/prod/slow-queries" },
  { key: "exceptions", label: "Exceptions", icon: <AlertTriangle className="h-4 w-4" />, href: "/nightwatch/prod/exceptions" },
];

function DashboardLayout({ children }: { children: React.ReactNode }) {
  const { mode, theme, toggleTheme } = useModeStore();
  const pathname = usePathname();

  const navItems = mode === "dev" ? DEV_NAV : PROD_NAV;

  useEffect(() => {
    document.documentElement.classList.toggle("dark", theme === "dark");
  }, [theme]);

  return (
    <div className="min-h-screen bg-background text-foreground">
      {/* Top Navbar */}
      <header className="sticky top-0 z-40 w-full border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
        <div className="flex h-14 items-center px-4 gap-4">
          <Link href="/nightwatch/" className="flex items-center gap-2 font-bold">
            <Eye className="h-5 w-5 text-primary" />
            <span className="bg-gradient-to-r from-foreground to-foreground/70 bg-clip-text text-transparent">
              Nightwatch
            </span>
            <span className="text-xs text-muted-foreground hidden sm:inline">
              LatticePHP
            </span>
          </Link>

          <div className="flex-1" />

          <div className="hidden sm:block">
            <TimeRangePicker />
          </div>

          <ModeSwitcher />

          <Button
            variant="ghost"
            size="icon"
            onClick={toggleTheme}
            aria-label="Toggle theme"
            className="h-9 w-9"
          >
            {theme === "dark" ? (
              <Sun className="h-4 w-4" />
            ) : (
              <Moon className="h-4 w-4" />
            )}
          </Button>
        </div>
      </header>

      <div className="flex min-h-[calc(100vh-3.5rem)]">
        {/* Sidebar */}
        <aside className="hidden sm:flex flex-col w-56 border-r bg-sidebar p-3 gap-1 shrink-0">
          <p className="text-xs text-sidebar-foreground/60 uppercase tracking-wider px-3 py-2">
            {mode === "dev" ? "Entry Types" : "Metrics"}
          </p>
          {navItems.map((item) => {
            const isActive = pathname === item.href || pathname === item.href + "/";
            return (
              <Link key={item.key} href={item.href}>
                <div
                  className={cn(
                    "flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition-colors",
                    isActive
                      ? "bg-sidebar-accent text-sidebar-accent-foreground"
                      : "text-sidebar-foreground/70 hover:bg-sidebar-accent/50 hover:text-sidebar-foreground",
                  )}
                >
                  {item.icon}
                  {item.label}
                </div>
              </Link>
            );
          })}
        </aside>

        {/* Mobile nav */}
        <div className="sm:hidden border-b px-4 py-2 overflow-x-auto">
          <div className="flex items-center gap-1">
            <TimeRangePicker />
            <Separator orientation="vertical" className="mx-2 h-6" />
            {navItems.map((item) => {
              const isActive = pathname === item.href || pathname === item.href + "/";
              return (
                <Link key={item.key} href={item.href}>
                  <Button
                    variant={isActive ? "default" : "ghost"}
                    size="sm"
                    className="h-7 gap-1 text-xs whitespace-nowrap"
                  >
                    {item.icon}
                    {item.label}
                  </Button>
                </Link>
              );
            })}
          </div>
        </div>

        {/* Main content */}
        <main className="flex-1 p-4 sm:p-6 overflow-auto">{children}</main>
      </div>
    </div>
  );
}

export default function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <html lang="en" suppressHydrationWarning>
      <body>
        <QueryProvider>
          <DashboardLayout>{children}</DashboardLayout>
        </QueryProvider>
      </body>
    </html>
  );
}
