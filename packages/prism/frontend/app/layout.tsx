import type { Metadata } from "next";
import type { ReactNode } from "react";
import Link from "next/link";
import { Gem, AlertCircle, Radio, BarChart3 } from "lucide-react";
import ProjectSelector from "@/components/project-selector";
import "./globals.css";

export const metadata: Metadata = {
  title: "Prism - Error Reporting",
  description: "Self-hosted error reporting dashboard for LatticePHP",
};

const NAV_ITEMS = [
  { href: "/issues", label: "Issues", icon: AlertCircle },
  { href: "/live", label: "Live Feed", icon: Radio },
  { href: "/stats", label: "Stats", icon: BarChart3 },
];

export default function DashboardLayout({ children }: { children: ReactNode }) {
  return (
    <html lang="en" className="dark">
      <body className="min-h-screen bg-background text-foreground antialiased">
        <div className="flex min-h-screen">
          {/* Sidebar */}
          <aside className="hidden md:flex flex-col w-60 border-r bg-card shrink-0">
            {/* Sidebar header: brand + project selector */}
            <div className="p-4 border-b space-y-3">
              <Link href="/" className="flex items-center gap-2 group">
                <Gem className="h-5 w-5 text-primary transition-transform group-hover:rotate-12" />
                <span className="text-lg font-bold bg-gradient-to-r from-primary to-purple-400 bg-clip-text text-transparent">
                  Prism
                </span>
                <span className="text-xs text-muted-foreground">LatticePHP</span>
              </Link>
              <ProjectSelector />
            </div>

            {/* Navigation */}
            <nav className="flex-1 p-3 space-y-1">
              {NAV_ITEMS.map((item) => (
                <Link
                  key={item.href}
                  href={item.href}
                  className="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium text-muted-foreground hover:bg-accent hover:text-accent-foreground transition-colors"
                >
                  <item.icon className="h-4 w-4" />
                  {item.label}
                </Link>
              ))}
            </nav>

            {/* Footer */}
            <div className="p-4 border-t text-xs text-muted-foreground">
              Prism v1.0.0
            </div>
          </aside>

          {/* Mobile header */}
          <div className="flex flex-col flex-1">
            <header className="md:hidden flex items-center gap-3 p-4 border-b bg-card">
              <Gem className="h-5 w-5 text-primary" />
              <span className="font-bold text-lg">Prism</span>
              <div className="flex-1" />
              <nav className="flex gap-2">
                {NAV_ITEMS.map((item) => (
                  <Link
                    key={item.href}
                    href={item.href}
                    className="flex items-center gap-1 rounded-md px-2 py-1.5 text-xs font-medium text-muted-foreground hover:bg-accent hover:text-accent-foreground transition-colors"
                  >
                    <item.icon className="h-3.5 w-3.5" />
                    {item.label}
                  </Link>
                ))}
              </nav>
            </header>

            {/* Main content */}
            <main className="flex-1 p-4 md:p-6 overflow-auto">
              {children}
            </main>
          </div>
        </div>
      </body>
    </html>
  );
}
