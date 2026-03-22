'use client';

import Link from 'next/link';
import { ChevronRight } from 'lucide-react';

interface BreadcrumbItem {
  label: string;
  href?: string;
}

interface BreadcrumbsProps {
  items: BreadcrumbItem[];
}

export function Breadcrumbs({ items }: BreadcrumbsProps) {
  return (
    <nav className="flex items-center gap-1 text-sm">
      {items.map((item, i) => (
        <div key={i} className="flex items-center gap-1">
          {i > 0 && <ChevronRight className="h-3.5 w-3.5 text-slate-400" />}
          {item.href ? (
            <Link href={item.href} className="text-slate-500 hover:text-slate-700">
              {item.label}
            </Link>
          ) : (
            <span className="font-medium text-slate-900">{item.label}</span>
          )}
        </div>
      ))}
    </nav>
  );
}
