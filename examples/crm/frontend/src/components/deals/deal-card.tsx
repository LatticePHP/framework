'use client';

import Link from 'next/link';
import { Card } from '@/components/ui/card';
import { Avatar } from '@/components/ui/avatar';
import { DollarSign, Calendar, Percent } from 'lucide-react';
import { formatCurrency, formatDate, contactName } from '@/lib/utils';
import type { Deal } from '@/lib/types';

interface DealCardProps {
  deal: Deal;
  stageColor: string;
}

export function DealCard({ deal, stageColor }: DealCardProps) {
  return (
    <Link href={`/deals/${deal.id}`}>
      <Card className="p-3 transition-all hover:shadow-md hover:-translate-y-0.5 cursor-pointer">
        <h4 className="text-sm font-semibold text-foreground leading-tight">{deal.title}</h4>

        {deal.contact && (
          <div className="mt-2 flex items-center gap-2">
            <Avatar fallback={contactName(deal.contact)} size="sm" className="h-5 w-5 text-[10px]" />
            <span className="text-xs text-muted-foreground truncate">{contactName(deal.contact)}</span>
          </div>
        )}

        <div className="mt-3 space-y-1.5">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-1">
              <DollarSign className="h-3.5 w-3.5 text-muted-foreground" />
              <span className="text-sm font-bold text-foreground">{formatCurrency(deal.value)}</span>
            </div>
            <div className="flex items-center gap-1">
              <Percent className="h-3 w-3 text-muted-foreground" />
              <span className="text-xs text-muted-foreground">{deal.probability}%</span>
            </div>
          </div>

          {deal.expected_close_date && (
            <div className="flex items-center gap-1">
              <Calendar className="h-3 w-3 text-muted-foreground" />
              <span className="text-xs text-muted-foreground">Close: {formatDate(deal.expected_close_date)}</span>
            </div>
          )}
        </div>

        {/* Probability bar */}
        <div className="mt-2 h-1 w-full overflow-hidden rounded-full bg-muted">
          <div
            className="h-full rounded-full bg-primary transition-all"
            style={{ width: `${deal.probability}%` }}
          />
        </div>
      </Card>
    </Link>
  );
}
