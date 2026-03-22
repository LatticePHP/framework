import { Link, Outlet, useLocation } from "react-router-dom";
import { ProjectSelector } from "./ProjectSelector";

interface NavItem {
  path: string;
  label: string;
  icon: string;
}

const navItems: NavItem[] = [
  { path: "/", label: "Projects", icon: "P" },
  { path: "/issues", label: "Issues", icon: "I" },
  { path: "/live", label: "Live Feed", icon: "L" },
  { path: "/stats", label: "Stats", icon: "S" },
];

export function Layout() {
  const location = useLocation();

  return (
    <div className="flex h-screen overflow-hidden bg-background">
      {/* Sidebar */}
      <aside className="w-64 flex-shrink-0 flex flex-col border-r border-default-200 bg-default-50 dark:bg-default-50/50">
        {/* Brand */}
        <div className="flex items-center gap-3 border-b border-default-200 px-5 py-4">
          <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-violet-500 to-fuchsia-500 text-white font-bold text-sm">
            P
          </div>
          <div>
            <h1 className="text-base font-bold text-foreground leading-tight">
              Prism
            </h1>
            <p className="text-[11px] text-default-400 leading-tight">
              Error Reporting
            </p>
          </div>
        </div>

        {/* Project selector */}
        <div className="px-3 py-3 border-b border-default-200">
          <ProjectSelector />
        </div>

        {/* Navigation */}
        <nav className="flex-1 overflow-y-auto px-3 py-3">
          <ul className="space-y-1">
            {navItems.map((item) => {
              const isActive =
                item.path === "/"
                  ? location.pathname === "/"
                  : location.pathname.startsWith(item.path);

              return (
                <li key={item.path}>
                  <Link
                    to={item.path}
                    className={`
                      flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium
                      transition-colors
                      ${
                        isActive
                          ? "bg-primary-100 dark:bg-primary-500/15 text-primary"
                          : "text-default-600 hover:bg-default-100 hover:text-foreground"
                      }
                    `}
                  >
                    <span
                      className={`
                        flex h-7 w-7 items-center justify-center rounded-md text-xs font-bold
                        ${
                          isActive
                            ? "bg-primary text-primary-foreground"
                            : "bg-default-200 dark:bg-default-100 text-default-500"
                        }
                      `}
                    >
                      {item.icon}
                    </span>
                    {item.label}

                    {/* Live indicator */}
                    {item.path === "/live" && (
                      <span className="ml-auto flex h-2 w-2">
                        <span className="animate-ping absolute inline-flex h-2 w-2 rounded-full bg-success-400 opacity-75" />
                        <span className="relative inline-flex rounded-full h-2 w-2 bg-success-500" />
                      </span>
                    )}
                  </Link>
                </li>
              );
            })}
          </ul>
        </nav>

        {/* Footer */}
        <div className="border-t border-default-200 px-5 py-3">
          <p className="text-[11px] text-default-400">
            Lattice Prism v1.0
          </p>
        </div>
      </aside>

      {/* Main content */}
      <main className="flex-1 overflow-y-auto">
        <Outlet />
      </main>
    </div>
  );
}
