'use client';

import { DealCard } from './deal-card';
import { formatCurrency } from '@/lib/utils';
import type { Deal, DealStage } from '@/lib/types';

interface PipelineBoardProps {
  deals: Deal[];
}

interface StageConfig {
  key: DealStage;
  label: string;
  color: string;
  bgColor: string;
  borderColor: string;
}

const stages: StageConfig[] = [
  { key: 'lead', label: 'Lead', color: 'text-slate-700', bgColor: 'bg-slate-50', borderColor: 'border-slate-200' },
  { key: 'qualified', label: 'Qualified', color: 'text-blue-700', bgColor: 'bg-blue-50', borderColor: 'border-blue-200' },
  { key: 'proposal', label: 'Proposal', color: 'text-indigo-700', bgColor: 'bg-indigo-50', borderColor: 'border-indigo-200' },
  { key: 'negotiation', label: 'Negotiation', color: 'text-amber-700', bgColor: 'bg-amber-50', borderColor: 'border-amber-200' },
  { key: 'closed_won', label: 'Closed Won', color: 'text-emerald-700', bgColor: 'bg-emerald-50', borderColor: 'border-emerald-200' },
  { key: 'closed_lost', label: 'Closed Lost', color: 'text-rose-700', bgColor: 'bg-rose-50', borderColor: 'border-rose-200' },
];

export function PipelineBoard({ deals }: PipelineBoardProps) {
  const dealsByStage = stages.map((stage) => ({
    ...stage,
    deals: deals.filter((d) => d.stage === stage.key),
    total: deals.filter((d) => d.stage === stage.key).reduce((sum, d) => sum + d.value, 0),
  }));

  return (
    <div className="flex gap-4 overflow-x-auto pb-4">
      {dealsByStage.map((stage) => (
        <div key={stage.key} className="flex w-72 shrink-0 flex-col">
          {/* Column Header */}
          <div className={`rounded-t-xl border ${stage.borderColor} ${stage.bgColor} p-3`}>
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2">
                <h3 className={`text-sm font-semibold ${stage.color}`}>{stage.label}</h3>
                <span className={`inline-flex h-5 min-w-5 items-center justify-center rounded-full ${stage.bgColor} px-1.5 text-xs font-medium ${stage.color} ring-1 ring-inset ring-current/20`}>
                  {stage.deals.length}
                </span>
              </div>
              <span className={`text-xs font-medium ${stage.color}`}>
                {formatCurrency(stage.total)}
              </span>
            </div>
          </div>

          {/* Column Body */}
          <div className={`flex-1 space-y-2 rounded-b-xl border border-t-0 ${stage.borderColor} bg-slate-50/50 p-2 min-h-[200px]`}>
            {stage.deals.map((deal) => (
              <DealCard key={deal.id} deal={deal} stageColor={stage.color} />
            ))}
            {stage.deals.length === 0 && (
              <div className="flex h-24 items-center justify-center rounded-lg border-2 border-dashed border-slate-200">
                <p className="text-xs text-slate-400">No deals</p>
              </div>
            )}
          </div>
        </div>
      ))}
    </div>
  );
}
