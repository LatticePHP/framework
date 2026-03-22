'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { cn } from '@/lib/utils';
import {
  LayoutDashboard,
  Users,
  Building2,
  Handshake,
  CalendarCheck,
  Settings,
  Hexagon,
} from 'lucide-react';

const navigation = [
  { name: 'Dashboard', href: '/', icon: LayoutDashboard },
  { name: 'Contacts', href: '/contacts', icon: Users },
  { name: 'Companies', href: '/companies', icon: Building2 },
  { name: 'Deals', href: '/deals', icon: Handshake },
  { name: 'Activities', href: '/activities', icon: CalendarCheck },
  { name: 'Settings', href: '/settings', icon: Settings },
];

export function Sidebar() {
  const pathname = usePathname();

  return (
    <aside className="fixed inset-y-0 left-0 z-40 flex w-64 flex-col border-r border-slate-200 bg-white">
      {/* Logo */}
      <div className="flex h-16 items-center gap-2.5 border-b border-slate-200 px-6">
        <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-600">
          <Hexagon className="h-5 w-5 text-white" />
        </div>
        <div>
          <span className="text-lg font-bold text-slate-900">Lattice</span>
          <span className="text-lg font-light text-indigo-600">CRM</span>
        </div>
      </div>

      {/* Navigation */}
      <nav className="flex-1 space-y-1 px-3 py-4">
        {navigation.map((item) => {
          const isActive = item.href === '/'
            ? pathname === '/'
            : pathname.startsWith(item.href);

          return (
            <Link
              key={item.name}
              href={item.href}
              className={cn(
                'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors',
                isActive
                  ? 'bg-indigo-50 text-indigo-700'
                  : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900',
              )}
            >
              <item.icon className={cn('h-5 w-5', isActive ? 'text-indigo-600' : 'text-slate-400')} />
              {item.name}
            </Link>
          );
        })}
      </nav>

      {/* Bottom section */}
      <div className="border-t border-slate-200 p-4">
        <div className="rounded-lg bg-gradient-to-r from-indigo-500 to-purple-600 p-4">
          <p className="text-sm font-medium text-white">Powered by</p>
          <p className="text-xs text-indigo-100">LatticePHP Framework</p>
        </div>
      </div>
    </aside>
  );
}
