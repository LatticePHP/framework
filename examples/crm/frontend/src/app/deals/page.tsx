'use client';

import Link from 'next/link';
import { Plus, LayoutGrid, List } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { Breadcrumbs } from '@/components/layout/breadcrumbs';
import { PipelineBoard } from '@/components/deals/pipeline-board';
import { useDeals } from '@/hooks/use-deals';
import { formatCurrency } from '@/lib/utils';
import { Table, TableHeader, TableBody, TableRow, TableHead, TableCell } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { contactName, formatDate } from '@/lib/utils';

const stageVariants: Record<string, 'secondary' | 'info' | 'default' | 'warning' | 'success' | 'danger'> = {
  lead: 'secondary',
  qualified: 'info',
  proposal: 'default',
  negotiation: 'warning',
  closed_won: 'success',
  closed_lost: 'danger',
};

export default function DealsPage() {
  const { data, loading } = useDeals();
  const [view, setView] = useState<'kanban' | 'list'>('kanban');

  const deals = data?.data ?? [];
  const totalValue = deals.reduce((sum, d) => sum + d.value, 0);
  const activeDeals = deals.filter((d) => d.stage !== 'closed_won' && d.stage !== 'closed_lost');

  return (
    <div className="space-y-6">
      <Breadcrumbs items={[{ label: 'Deals' }]} />

      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">Deals Pipeline</h1>
          <p className="text-sm text-slate-500">
            {activeDeals.length} active deals worth {formatCurrency(activeDeals.reduce((s, d) => s + d.value, 0))}
          </p>
        </div>
        <div className="flex items-center gap-2">
          <div className="flex rounded-lg border border-slate-200 bg-white">
            <button
              onClick={() => setView('kanban')}
              className={`rounded-l-lg p-2 ${view === 'kanban' ? 'bg-slate-100 text-slate-900' : 'text-slate-400 hover:text-slate-600'}`}
            >
              <LayoutGrid className="h-4 w-4" />
            </button>
            <button
              onClick={() => setView('list')}
              className={`rounded-r-lg p-2 ${view === 'list' ? 'bg-slate-100 text-slate-900' : 'text-slate-400 hover:text-slate-600'}`}
            >
              <List className="h-4 w-4" />
            </button>
          </div>
          <Link href="/deals/new">
            <Button className="gap-2">
              <Plus className="h-4 w-4" />
              Create Deal
            </Button>
          </Link>
        </div>
      </div>

      {loading ? (
        <div className="flex gap-4">
          {[1, 2, 3, 4, 5, 6].map((i) => (
            <div key={i} className="w-72 shrink-0">
              <Skeleton className="h-12 w-full mb-2 rounded-t-xl" />
              <Skeleton className="h-48 w-full rounded-b-xl" />
            </div>
          ))}
        </div>
      ) : view === 'kanban' ? (
        <PipelineBoard deals={deals} />
      ) : (
        <Card>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Deal</TableHead>
                <TableHead>Contact</TableHead>
                <TableHead>Stage</TableHead>
                <TableHead>Value</TableHead>
                <TableHead>Probability</TableHead>
                <TableHead>Close Date</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {deals.map((deal) => (
                <TableRow key={deal.id} className="cursor-pointer">
                  <TableCell>
                    <Link href={`/deals/${deal.id}`} className="font-medium text-slate-900 hover:text-indigo-600">
                      {deal.title}
                    </Link>
                  </TableCell>
                  <TableCell>
                    {deal.contact ? (
                      <span className="text-slate-500">{contactName(deal.contact)}</span>
                    ) : '--'}
                  </TableCell>
                  <TableCell>
                    <Badge variant={stageVariants[deal.stage]}>
                      {deal.stage.replace('_', ' ')}
                    </Badge>
                  </TableCell>
                  <TableCell>
                    <span className="font-semibold text-slate-900">{formatCurrency(deal.value)}</span>
                  </TableCell>
                  <TableCell>
                    <span className="text-slate-500">{deal.probability}%</span>
                  </TableCell>
                  <TableCell>
                    <span className="text-slate-400">
                      {deal.expected_close_date ? formatDate(deal.expected_close_date) : '--'}
                    </span>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </Card>
      )}
    </div>
  );
}
