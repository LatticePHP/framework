'use client';

import { use } from 'react';
import Link from 'next/link';
import { Edit, DollarSign, Calendar, Percent, ArrowRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Avatar } from '@/components/ui/avatar';
import { Skeleton } from '@/components/ui/skeleton';
import { Separator } from '@/components/ui/separator';
import { Breadcrumbs } from '@/components/layout/breadcrumbs';
import { NoteList } from '@/components/notes/note-list';
import { NoteForm } from '@/components/notes/note-form';
import { ActivityList } from '@/components/activities/activity-list';
import { useDeal } from '@/hooks/use-deals';
import { useNotes } from '@/hooks/use-notes';
import { DEMO_ACTIVITIES } from '@/hooks/use-activities';
import { formatCurrency, formatDate, contactName } from '@/lib/utils';
import type { DealStage } from '@/lib/types';

const stageOrder: DealStage[] = ['lead', 'qualified', 'proposal', 'negotiation', 'closed_won', 'closed_lost'];
const stageLabels: Record<string, string> = {
  lead: 'Lead',
  qualified: 'Qualified',
  proposal: 'Proposal',
  negotiation: 'Negotiation',
  closed_won: 'Won',
  closed_lost: 'Lost',
};
const stageColors: Record<string, string> = {
  lead: 'bg-slate-200',
  qualified: 'bg-blue-500',
  proposal: 'bg-indigo-500',
  negotiation: 'bg-amber-500',
  closed_won: 'bg-emerald-500',
  closed_lost: 'bg-rose-500',
};

export default function DealDetailPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const dealId = parseInt(id);
  const { deal, loading } = useDeal(dealId);
  const { notes, addNote } = useNotes('deal', dealId);

  const dealActivities = DEMO_ACTIVITIES.filter((a) => a.deal_id === dealId);

  if (loading) {
    return (
      <div className="space-y-6">
        <Skeleton className="h-6 w-48" />
        <Skeleton className="h-32 w-full" />
        <Skeleton className="h-64 w-full" />
      </div>
    );
  }

  if (!deal) {
    return (
      <div className="flex flex-col items-center justify-center py-20">
        <p className="text-lg text-muted-foreground">Deal not found</p>
        <Link href="/deals" className="mt-4 text-primary hover:text-primary">Back to Deals</Link>
      </div>
    );
  }

  const currentStageIndex = stageOrder.indexOf(deal.stage);

  return (
    <div className="space-y-6">
      <Breadcrumbs
        items={[
          { label: 'Deals', href: '/deals' },
          { label: deal.title },
        ]}
      />

      {/* Deal Header */}
      <Card className="p-6">
        <div className="flex items-start justify-between">
          <div>
            <h1 className="text-2xl font-bold text-foreground">{deal.title}</h1>
            <div className="mt-2 flex items-center gap-4 text-sm text-muted-foreground">
              {deal.contact && (
                <Link href={`/contacts/${deal.contact.id}`} className="flex items-center gap-1.5 hover:text-primary">
                  <Avatar fallback={contactName(deal.contact)} size="sm" className="h-5 w-5 text-[10px]" />
                  {contactName(deal.contact)}
                </Link>
              )}
            </div>
          </div>
          <Button variant="outline" size="sm" className="gap-1.5">
            <Edit className="h-3.5 w-3.5" />
            Edit
          </Button>
        </div>

        {/* Stage Pipeline */}
        <div className="mt-6 flex items-center gap-1">
          {stageOrder.filter(s => s !== 'closed_lost').map((stage, i) => {
            const isActive = i <= currentStageIndex && deal.stage !== 'closed_lost';
            const isCurrent = stage === deal.stage;
            return (
              <div key={stage} className="flex flex-1 items-center gap-1">
                <div className={`flex h-8 flex-1 items-center justify-center rounded-md text-xs font-medium transition-colors ${isActive ? `${stageColors[stage]} text-primary-foreground` : 'bg-muted text-muted-foreground'} ${isCurrent ? 'ring-2 ring-offset-1 ring-ring' : ''}`}>
                  {stageLabels[stage]}
                </div>
                {i < stageOrder.length - 2 && (
                  <ArrowRight className="h-3 w-3 shrink-0 text-muted-foreground/50" />
                )}
              </div>
            );
          })}
        </div>

        {deal.stage === 'closed_lost' && (
          <div className="mt-2 rounded-lg bg-destructive/10 p-2 text-center text-sm font-medium text-destructive">
            This deal was lost
          </div>
        )}
      </Card>

      {/* Key Metrics */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <Card className="p-4">
          <div className="flex items-center gap-3">
            <div className="rounded-lg bg-emerald-50 p-2">
              <DollarSign className="h-5 w-5 text-emerald-600" />
            </div>
            <div>
              <p className="text-sm text-muted-foreground">Value</p>
              <p className="text-xl font-bold text-foreground">{formatCurrency(deal.value)}</p>
            </div>
          </div>
        </Card>
        <Card className="p-4">
          <div className="flex items-center gap-3">
            <div className="rounded-lg bg-accent p-2">
              <Percent className="h-5 w-5 text-primary" />
            </div>
            <div>
              <p className="text-sm text-muted-foreground">Probability</p>
              <p className="text-xl font-bold text-foreground">{deal.probability}%</p>
            </div>
          </div>
        </Card>
        <Card className="p-4">
          <div className="flex items-center gap-3">
            <div className="rounded-lg bg-amber-50 p-2">
              <Calendar className="h-5 w-5 text-amber-600" />
            </div>
            <div>
              <p className="text-sm text-muted-foreground">Expected Close</p>
              <p className="text-xl font-bold text-foreground">
                {deal.expected_close_date ? formatDate(deal.expected_close_date) : 'Not set'}
              </p>
            </div>
          </div>
        </Card>
      </div>

      {/* Activities & Notes */}
      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <Card>
          <CardContent className="p-6">
            <h3 className="text-sm font-semibold text-foreground mb-4">Activities ({dealActivities.length})</h3>
            <ActivityList activities={dealActivities} />
          </CardContent>
        </Card>

        <Card>
          <CardContent className="p-6">
            <h3 className="text-sm font-semibold text-foreground mb-4">Notes</h3>
            <NoteForm onSubmit={addNote} />
            <Separator className="my-4" />
            <NoteList notes={notes} />
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
