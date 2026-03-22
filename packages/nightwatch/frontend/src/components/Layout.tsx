import { useState } from 'react';
import { Outlet, useNavigate, useLocation } from 'react-router-dom';
import {
  Navbar,
  NavbarBrand,
  NavbarContent,
  NavbarItem,
  NavbarMenuToggle,
  NavbarMenu,
  NavbarMenuItem,
  Button,
  Tooltip,
  Divider,
} from '@nextui-org/react';
import { useModeStore } from '@/stores/mode';
import { useFiltersStore } from '@/stores/filters';
import type { EntryType } from '@/schemas/entry';
import ModeSwitcher from './ModeSwitcher';
import TimeRangePicker from './TimeRangePicker';

// ── Navigation items ──

interface NavItem {
  key: string;
  label: string;
  icon: string;
  path: string;
}

const DEV_NAV: NavItem[] = [
  { key: 'request', label: 'Requests', icon: '\u{1F310}', path: '/dev/requests' },
  { key: 'query', label: 'Queries', icon: '\u{1F5C4}', path: '/dev/queries' },
  { key: 'exception', label: 'Exceptions', icon: '\u{1F6A8}', path: '/dev/exceptions' },
  { key: 'event', label: 'Events', icon: '\u{26A1}', path: '/dev/events' },
  { key: 'cache', label: 'Cache', icon: '\u{1F4E6}', path: '/dev/cache' },
  { key: 'job', label: 'Jobs', icon: '\u{2699}', path: '/dev/jobs' },
  { key: 'mail', label: 'Mail', icon: '\u{2709}', path: '/dev/mail' },
  { key: 'log', label: 'Logs', icon: '\u{1F4DD}', path: '/dev/logs' },
];

const PROD_NAV: NavItem[] = [
  { key: 'overview', label: 'Overview', icon: '\u{1F4CA}', path: '/prod/overview' },
  { key: 'slow-requests', label: 'Slow Requests', icon: '\u{1F422}', path: '/prod/slow-requests' },
  { key: 'slow-queries', label: 'Slow Queries', icon: '\u{1F5C4}', path: '/prod/slow-queries' },
  { key: 'exceptions', label: 'Exceptions', icon: '\u{1F6A8}', path: '/prod/exceptions' },
];

export default function Layout() {
  const [menuOpen, setMenuOpen] = useState(false);
  const { mode, theme, toggleTheme } = useModeStore();
  const setEntryType = useFiltersStore((s) => s.setEntryType);
  const navigate = useNavigate();
  const location = useLocation();

  const navItems = mode === 'dev' ? DEV_NAV : PROD_NAV;

  const handleNav = (item: NavItem) => {
    if (mode === 'dev') {
      setEntryType(item.key as EntryType);
    }
    navigate(item.path);
    setMenuOpen(false);
  };

  const isActive = (item: NavItem) => location.pathname === item.path;

  return (
    <div className={`${theme} min-h-screen bg-background text-foreground`}>
      {/* Top Navbar */}
      <Navbar
        isBordered
        isMenuOpen={menuOpen}
        onMenuOpenChange={setMenuOpen}
        maxWidth="full"
        className="border-b border-divider"
      >
        <NavbarContent className="sm:hidden" justify="start">
          <NavbarMenuToggle aria-label={menuOpen ? 'Close menu' : 'Open menu'} />
        </NavbarContent>

        <NavbarContent justify="start">
          <NavbarBrand className="gap-2">
            <span className="text-lg font-bold bg-gradient-to-r from-primary to-secondary bg-clip-text text-transparent">
              Nightwatch
            </span>
            <span className="text-xs text-default-400 hidden sm:inline">LatticePHP</span>
          </NavbarBrand>
        </NavbarContent>

        <NavbarContent justify="center" className="hidden sm:flex">
          <NavbarItem>
            <TimeRangePicker />
          </NavbarItem>
        </NavbarContent>

        <NavbarContent justify="end" className="gap-2">
          <NavbarItem>
            <ModeSwitcher />
          </NavbarItem>
          <NavbarItem>
            <Tooltip content={`Switch to ${theme === 'dark' ? 'light' : 'dark'} mode`}>
              <Button
                isIconOnly
                size="sm"
                variant="light"
                onPress={toggleTheme}
                aria-label="Toggle theme"
              >
                {theme === 'dark' ? '\u{2600}' : '\u{1F319}'}
              </Button>
            </Tooltip>
          </NavbarItem>
        </NavbarContent>

        {/* Mobile menu */}
        <NavbarMenu>
          <NavbarMenuItem className="py-2">
            <TimeRangePicker />
          </NavbarMenuItem>
          <Divider className="my-2" />
          {navItems.map((item) => (
            <NavbarMenuItem key={item.key}>
              <Button
                fullWidth
                variant={isActive(item) ? 'flat' : 'light'}
                color={isActive(item) ? 'primary' : 'default'}
                onPress={() => handleNav(item)}
                className="justify-start"
              >
                <span className="mr-2">{item.icon}</span>
                {item.label}
              </Button>
            </NavbarMenuItem>
          ))}
        </NavbarMenu>
      </Navbar>

      <div className="flex min-h-[calc(100vh-65px)]">
        {/* Desktop Sidebar */}
        <aside className="hidden sm:flex flex-col w-56 border-r border-divider bg-content1 p-3 gap-1 shrink-0">
          <p className="text-xs text-default-400 uppercase tracking-wider px-3 py-2">
            {mode === 'dev' ? 'Entry Types' : 'Metrics'}
          </p>
          {navItems.map((item) => (
            <Button
              key={item.key}
              fullWidth
              variant={isActive(item) ? 'flat' : 'light'}
              color={isActive(item) ? 'primary' : 'default'}
              onPress={() => handleNav(item)}
              className="justify-start"
              size="sm"
            >
              <span className="mr-2">{item.icon}</span>
              {item.label}
            </Button>
          ))}
        </aside>

        {/* Main content */}
        <main className="flex-1 p-4 sm:p-6 overflow-auto">
          <Outlet />
        </main>
      </div>
    </div>
  );
}
