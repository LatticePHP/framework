import * as React from 'react';
import { cva, type VariantProps } from 'class-variance-authority';
import { cn } from '@/lib/utils';

const badgeVariants = cva(
  'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium transition-colors',
  {
    variants: {
      variant: {
        default: 'bg-indigo-50 text-indigo-700 ring-1 ring-inset ring-indigo-600/20',
        secondary: 'bg-slate-100 text-slate-700 ring-1 ring-inset ring-slate-500/20',
        success: 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20',
        warning: 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-600/20',
        danger: 'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-600/20',
        info: 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-600/20',
        purple: 'bg-purple-50 text-purple-700 ring-1 ring-inset ring-purple-600/20',
      },
    },
    defaultVariants: {
      variant: 'default',
    },
  },
);

export interface BadgeProps
  extends React.HTMLAttributes<HTMLDivElement>,
    VariantProps<typeof badgeVariants> {}

function Badge({ className, variant, ...props }: BadgeProps) {
  return <div className={cn(badgeVariants({ variant }), className)} {...props} />;
}

export { Badge, badgeVariants };
