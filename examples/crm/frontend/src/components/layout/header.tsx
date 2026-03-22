'use client';

import { Bell, Search, LogOut, User, ChevronDown } from 'lucide-react';
import { Avatar } from '@/components/ui/avatar';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
  DropdownMenuLabel,
} from '@/components/ui/dropdown-menu';
import { useAuth } from '@/hooks/use-auth';

export function Header() {
  const { user, logout } = useAuth();

  return (
    <header className="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-slate-200 bg-white/80 px-6 backdrop-blur-sm">
      {/* Search */}
      <div className="flex flex-1 items-center">
        <div className="relative w-full max-w-md">
          <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
          <input
            type="text"
            placeholder="Search contacts, deals, companies..."
            className="h-9 w-full rounded-lg border border-slate-200 bg-slate-50 pl-10 pr-4 text-sm text-slate-900 placeholder:text-slate-400 focus:border-indigo-300 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
          />
          <kbd className="absolute right-3 top-1/2 hidden -translate-y-1/2 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-400 sm:inline-block">
            /
          </kbd>
        </div>
      </div>

      {/* Right section */}
      <div className="flex items-center gap-3">
        {/* Notifications */}
        <button className="relative rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
          <Bell className="h-5 w-5" />
          <span className="absolute right-1 top-1 h-2 w-2 rounded-full bg-rose-500" />
        </button>

        {/* User menu */}
        <DropdownMenu>
          <DropdownMenuTrigger className="flex items-center gap-2 rounded-lg p-1.5 hover:bg-slate-100">
            <Avatar fallback={user?.name || 'U'} size="sm" />
            <div className="hidden text-left sm:block">
              <p className="text-sm font-medium text-slate-900">{user?.name || 'User'}</p>
              <p className="text-xs text-slate-500">{user?.role || 'Admin'}</p>
            </div>
            <ChevronDown className="h-4 w-4 text-slate-400" />
          </DropdownMenuTrigger>
          <DropdownMenuContent>
            <DropdownMenuLabel>My Account</DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuItem>
              <User className="h-4 w-4" />
              Profile
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem onClick={logout}>
              <LogOut className="h-4 w-4" />
              Sign out
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </div>
    </header>
  );
}
