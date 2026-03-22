'use client';

import { useState, useEffect } from 'react';
import { api } from '@/lib/api';
import type { DashboardStats } from '@/lib/types';

interface PipelineStage {
  stage: string;
  label: string;
  count: number;
  value: number;
  color: string;
}

interface RecentActivity {
  id: number;
  text: string;
  time: string;
  type: 'deal' | 'contact' | 'activity';
}

const STAGE_COLORS: Record<string, string> = {
  lead: 'bg-slate-400',
  qualified: 'bg-blue-500',
  proposal: 'bg-indigo-500',
  negotiation: 'bg-amber-500',
  closed_won: 'bg-emerald-500',
  closed_lost: 'bg-rose-500',
};

const STAGE_LABELS: Record<string, string> = {
  lead: 'Lead',
  qualified: 'Qualified',
  proposal: 'Proposal',
  negotiation: 'Negotiation',
  closed_won: 'Won',
  closed_lost: 'Lost',
};

export function useDashboard() {
  const [stats, setStats] = useState<DashboardStats | null>(null);
  const [pipeline, setPipeline] = useState<PipelineStage[]>([]);
  const [recentActivity, setRecentActivity] = useState<RecentActivity[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchDashboard();
  }, []);

  async function fetchDashboard() {
    try {
      const res = await api.get<{ data: {
        contacts: { total: number };
        deals: { total: number; total_value: number; open_value: number; by_stage: Record<string, { count: number; value: number }> };
        activities: { total: number; upcoming: number; overdue: number };
      } }>('/dashboard/stats');

      const d = res.data;

      setStats({
        total_contacts: d.contacts.total,
        active_deals: d.deals.total,
        pipeline_value: d.deals.open_value,
        conversion_rate: d.deals.by_stage.closed_won
          ? Math.round((d.deals.by_stage.closed_won.count / d.deals.total) * 100 * 10) / 10
          : 0,
      });

      const stages: PipelineStage[] = Object.entries(d.deals.by_stage).map(([stage, data]) => ({
        stage,
        label: STAGE_LABELS[stage] || stage,
        count: data.count,
        value: data.value,
        color: STAGE_COLORS[stage] || 'bg-muted',
      }));
      setPipeline(stages);

      // Recent activity from the API feed
      try {
        const feedRes = await api.get<{ data: Array<{ id: number; description: string; created_at: string; type: string }> }>('/dashboard/feed');
        setRecentActivity(feedRes.data.slice(0, 6).map((item) => ({
          id: item.id,
          text: item.description,
          time: item.created_at,
          type: (item.type === 'deal' ? 'deal' : item.type === 'contact' ? 'contact' : 'activity') as 'deal' | 'contact' | 'activity',
        })));
      } catch {
        setRecentActivity([]);
      }
    } catch {
      // Backend not available — use demo data
      setStats({
        total_contacts: 248,
        active_deals: 8,
        pipeline_value: 1260000,
        conversion_rate: 32.5,
      });
      setPipeline([
        { stage: 'lead', label: 'Lead', count: 2, value: 560000, color: 'bg-slate-400' },
        { stage: 'qualified', label: 'Qualified', count: 2, value: 195000, color: 'bg-blue-500' },
        { stage: 'proposal', label: 'Proposal', count: 2, value: 325000, color: 'bg-indigo-500' },
        { stage: 'negotiation', label: 'Negotiation', count: 2, value: 155000, color: 'bg-amber-500' },
        { stage: 'closed_won', label: 'Won', count: 1, value: 24000, color: 'bg-emerald-500' },
        { stage: 'closed_lost', label: 'Lost', count: 1, value: 45000, color: 'bg-rose-500' },
      ]);
      setRecentActivity([]);
    } finally {
      setLoading(false);
    }
  }

  return { stats, pipeline, recentActivity, loading };
}
