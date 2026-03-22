'use client';

import * as React from 'react';
import { cn } from '@/lib/utils';

interface DropdownMenuProps {
  children: React.ReactNode;
}

interface DropdownMenuContextType {
  open: boolean;
  setOpen: (open: boolean) => void;
}

const DropdownMenuContext = React.createContext<DropdownMenuContextType>({
  open: false,
  setOpen: () => {},
});

function DropdownMenu({ children }: DropdownMenuProps) {
  const [open, setOpen] = React.useState(false);
  return (
    <DropdownMenuContext.Provider value={{ open, setOpen }}>
      <div className="relative inline-block text-left">{children}</div>
    </DropdownMenuContext.Provider>
  );
}

function DropdownMenuTrigger({ children, className, ...props }: React.ButtonHTMLAttributes<HTMLButtonElement>) {
  const { open, setOpen } = React.useContext(DropdownMenuContext);
  return (
    <button
      className={cn(className)}
      onClick={() => setOpen(!open)}
      {...props}
    >
      {children}
    </button>
  );
}

function DropdownMenuContent({ children, className, align = 'end' }: React.HTMLAttributes<HTMLDivElement> & { align?: 'start' | 'end' }) {
  const { open, setOpen } = React.useContext(DropdownMenuContext);
  const ref = React.useRef<HTMLDivElement>(null);

  React.useEffect(() => {
    function handleClick(e: MouseEvent) {
      if (ref.current && !ref.current.contains(e.target as Node)) {
        setOpen(false);
      }
    }
    if (open) document.addEventListener('mousedown', handleClick);
    return () => document.removeEventListener('mousedown', handleClick);
  }, [open, setOpen]);

  if (!open) return null;

  return (
    <div
      ref={ref}
      className={cn(
        'absolute z-50 mt-2 min-w-[180px] rounded-lg border border-slate-200 bg-white p-1 shadow-lg',
        align === 'end' ? 'right-0' : 'left-0',
        className,
      )}
    >
      {children}
    </div>
  );
}

function DropdownMenuItem({
  children,
  className,
  onClick,
  ...props
}: React.HTMLAttributes<HTMLDivElement>) {
  const { setOpen } = React.useContext(DropdownMenuContext);
  return (
    <div
      className={cn(
        'flex cursor-pointer items-center gap-2 rounded-md px-3 py-2 text-sm text-slate-700 hover:bg-slate-100',
        className,
      )}
      onClick={(e) => {
        onClick?.(e);
        setOpen(false);
      }}
      {...props}
    >
      {children}
    </div>
  );
}

function DropdownMenuSeparator({ className }: { className?: string }) {
  return <div className={cn('-mx-1 my-1 h-px bg-slate-200', className)} />;
}

function DropdownMenuLabel({ children, className }: React.HTMLAttributes<HTMLDivElement>) {
  return <div className={cn('px-3 py-2 text-xs font-medium text-slate-500', className)}>{children}</div>;
}

export {
  DropdownMenu,
  DropdownMenuTrigger,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuLabel,
};
