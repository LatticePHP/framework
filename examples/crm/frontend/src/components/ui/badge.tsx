import * as React from 'react';
import { cva, type VariantProps } from 'class-variance-authority';
import { cn } from '@/lib/utils';

const badgeVariants = cva(
  'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium transition-colors',
  {
    variants: {
      variant: {
        default: 'bg-accent text-primary ring-1 ring-inset ring-primary/20',
        secondary: 'bg-muted text-foreground ring-1 ring-inset ring-muted-foreground/20',
        success: 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20',
        warning: 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-600/20',
        danger: 'bg-destructive/10 text-destructive ring-1 ring-inset ring-destructive/20',
        info: 'bg-accent text-primary ring-1 ring-inset ring-primary/20',
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
