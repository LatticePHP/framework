import { NavLink, Outlet } from 'react-router-dom';
import { Button, Chip } from '@nextui-org/react';
import { useFiltersStore } from '@/stores/filters';
import { useDashboardStats } from '@/api/stats';
import AutoRefresh from './AutoRefresh';

const navItems = [
  { to: '/', label: 'Dashboard', end: true },
  { to: '/jobs/recent', label: 'Recent Jobs' },
  { to: '/jobs/failed', label: 'Failed Jobs' },
  { to: '/workers', label: 'Workers' },
];

export default function Layout() {
  const theme = useFiltersStore((s) => s.theme);
  const toggleTheme = useFiltersStore((s) => s.toggleTheme);
  const sidebarCollapsed = useFiltersStore((s) => s.sidebarCollapsed);
  const toggleSidebar = useFiltersStore((s) => s.toggleSidebar);
  const { data: stats } = useDashboardStats();

  return (
    <div className={`${theme} bg-background text-foreground min-h-screen flex`}>
      {/* Sidebar */}
      <aside
        className={`bg-content1 border-r border-divider flex flex-col transition-all duration-200 ${
          sidebarCollapsed ? 'w-16' : 'w-60'
        }`}
      >
        {/* Brand */}
        <div className="h-16 flex items-center px-4 border-b border-divider">
          {!sidebarCollapsed && (
            <span className="text-lg font-bold tracking-tight">Loom</span>
          )}
          <Button
            isIconOnly
            size="sm"
            variant="light"
            onPress={toggleSidebar}
            className={sidebarCollapsed ? 'mx-auto' : 'ml-auto'}
            aria-label="Toggle sidebar"
          >
            {sidebarCollapsed ? '\u00BB' : '\u00AB'}
          </Button>
        </div>

        {/* Nav links */}
        <nav className="flex-1 py-4 space-y-1 px-2">
          {navItems.map((item) => (
            <NavLink
              key={item.to}
              to={item.to}
              end={item.to === '/'}
              className={({ isActive }) =>
                `flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-colors ${
                  isActive
                    ? 'bg-primary/10 text-primary font-medium'
                    : 'text-default-600 hover:bg-default-100'
                }`
              }
            >
              {!sidebarCollapsed && <span>{item.label}</span>}
            </NavLink>
          ))}
        </nav>

        {/* Theme toggle */}
        <div className="p-4 border-t border-divider">
          <Button
            size="sm"
            variant="flat"
            onPress={toggleTheme}
            className="w-full"
          >
            {sidebarCollapsed ? (theme === 'light' ? 'D' : 'L') : (theme === 'light' ? 'Dark Mode' : 'Light Mode')}
          </Button>
        </div>
      </aside>

      {/* Main area */}
      <div className="flex-1 flex flex-col min-w-0">
        {/* Header */}
        <header className="h-16 border-b border-divider bg-content1 flex items-center justify-between px-6">
          <div className="flex items-center gap-4">
            <h1 className="text-lg font-semibold">Queue Monitor</h1>
            {stats && (
              <div className="hidden md:flex items-center gap-3">
                <Chip size="sm" variant="flat" color="success">
                  {stats.throughput_per_minute} jobs/min
                </Chip>
                <Chip size="sm" variant="flat" color="warning">
                  {stats.queue_sizes
                    ? Object.values(stats.queue_sizes).reduce((a, b) => a + b, 0)
                    : 0}{' '}
                  pending
                </Chip>
                {stats.failed_last_hour > 0 && (
                  <Chip size="sm" variant="flat" color="danger">
                    {stats.failed_last_hour} failed
                  </Chip>
                )}
                <Chip size="sm" variant="flat" color="primary">
                  {stats.active_workers} workers
                </Chip>
              </div>
            )}
          </div>
          <AutoRefresh />
        </header>

        {/* Page content */}
        <main className="flex-1 p-6 overflow-auto">
          <Outlet />
        </main>
      </div>
    </div>
  );
}
