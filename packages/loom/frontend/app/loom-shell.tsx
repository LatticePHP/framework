"use client";

import * as React from "react";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from "@/components/ui/tooltip";
import {
  Workflow,
  LayoutDashboard,
  Clock,
  AlertTriangle,
  Users,
  PanelLeftClose,
  PanelLeft,
  Moon,
  Sun,
} from "lucide-react";
import { AutoRefresh } from "@/components/auto-refresh";

const navItems = [
  { href: "/", label: "Dashboard", icon: LayoutDashboard },
  { href: "/jobs/recent", label: "Recent Jobs", icon: Clock },
  { href: "/jobs/failed", label: "Failed Jobs", icon: AlertTriangle },
  { href: "/workers", label: "Workers", icon: Users },
];

export function LoomShell({ children }: { children: React.ReactNode }) {
  const pathname = usePathname();
  const [collapsed, setCollapsed] = React.useState(false);
  const [dark, setDark] = React.useState(false);

  React.useEffect(() => {
    const stored = localStorage.getItem("loom-theme");
    if (stored === "dark") {
      setDark(true);
      document.documentElement.classList.add("dark");
    }
  }, []);

  const toggleTheme = () => {
    setDark((prev) => {
      const next = !prev;
      document.documentElement.classList.toggle("dark", next);
      localStorage.setItem("loom-theme", next ? "dark" : "light");
      return next;
    });
  };

  return (
    <TooltipProvider delayDuration={0}>
      <div className="flex h-screen bg-background text-foreground">
        {/* Sidebar */}
        <aside
          className={cn(
            "flex flex-col border-r bg-card transition-all duration-200",
            collapsed ? "w-16" : "w-60"
          )}
        >
          {/* Brand */}
          <div className="flex h-14 items-center border-b px-4">
            {!collapsed && (
              <div className="flex items-center gap-2">
                <Workflow className="h-5 w-5 text-primary" />
                <span className="text-lg font-bold tracking-tight">Loom</span>
              </div>
            )}
            {collapsed && (
              <Tooltip>
                <TooltipTrigger asChild>
                  <div className="mx-auto">
                    <Workflow className="h-5 w-5 text-primary" />
                  </div>
                </TooltipTrigger>
                <TooltipContent side="right">Loom</TooltipContent>
              </Tooltip>
            )}
            <Button
              variant="ghost"
              size="icon"
              onClick={() => setCollapsed((c) => !c)}
              className={cn("h-8 w-8", collapsed ? "mx-auto" : "ml-auto")}
              aria-label="Toggle sidebar"
            >
              {collapsed ? (
                <PanelLeft className="h-4 w-4" />
              ) : (
                <PanelLeftClose className="h-4 w-4" />
              )}
            </Button>
          </div>

          {/* Nav */}
          <nav className="flex-1 space-y-1 p-2">
            {navItems.map((item) => {
              const active =
                item.href === "/"
                  ? pathname === "/"
                  : pathname.startsWith(item.href);

              const link = (
                <Link
                  key={item.href}
                  href={item.href}
                  className={cn(
                    "flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition-colors",
                    active
                      ? "bg-primary/10 font-medium text-primary"
                      : "text-muted-foreground hover:bg-muted hover:text-foreground"
                  )}
                >
                  <item.icon className="h-4 w-4 shrink-0" />
                  {!collapsed && <span>{item.label}</span>}
                </Link>
              );

              if (collapsed) {
                return (
                  <Tooltip key={item.href}>
                    <TooltipTrigger asChild>{link}</TooltipTrigger>
                    <TooltipContent side="right">{item.label}</TooltipContent>
                  </Tooltip>
                );
              }
              return <React.Fragment key={item.href}>{link}</React.Fragment>;
            })}
          </nav>

          {/* Footer */}
          <div className="border-t p-2">
            <Button
              variant="ghost"
              size="sm"
              onClick={toggleTheme}
              className="w-full justify-start gap-2"
            >
              {dark ? (
                <Sun className="h-4 w-4" />
              ) : (
                <Moon className="h-4 w-4" />
              )}
              {!collapsed && (dark ? "Light Mode" : "Dark Mode")}
            </Button>
          </div>
        </aside>

        {/* Main area */}
        <div className="flex flex-1 flex-col min-w-0">
          <header className="flex h-14 items-center justify-between border-b bg-card px-6">
            <h1 className="text-lg font-semibold">Queue Monitor</h1>
            <AutoRefresh />
          </header>
          <main className="flex-1 overflow-auto p-6">{children}</main>
        </div>
      </div>
    </TooltipProvider>
  );
}
