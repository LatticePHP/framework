'use client';

import { useState, useEffect } from 'react';
import type { DashboardStats } from '@/lib/types';

const DEMO_STATS: DashboardStats = {
  total_contacts: 248,
  active_deals: 8,
  pipeline_value: 1260000,
  conversion_rate: 32.5,
};

interface PipelineStage {
  stage: string;
  label: string;
  count: number;
  value: number;
  color: string;
}

const DEMO_PIPELINE: PipelineStage[] = [
  { stage: 'lead', label: 'Lead', count: 2, value: 560000, color: 'bg-slate-400' },
  { stage: 'qualified', label: 'Qualified', count: 2, value: 195000, color: 'bg-blue-500' },
  { stage: 'proposal', label: 'Proposal', count: 2, value: 325000, color: 'bg-indigo-500' },
  { stage: 'negotiation', label: 'Negotiation', count: 2, value: 155000, color: 'bg-amber-500' },
  { stage: 'closed_won', label: 'Won', count: 1, value: 24000, color: 'bg-emerald-500' },
  { stage: 'closed_lost', label: 'Lost', count: 1, value: 45000, color: 'bg-rose-500' },
];

interface RecentActivity {
  id: number;
  text: string;
  time: string;
  type: 'deal' | 'contact' | 'activity';
}

const DEMO_RECENT: RecentActivity[] = [
  { id: 1, text: 'Closed deal: Acme Corp Support Renewal ($24,000)', time: '2024-03-22T15:30:00Z', type: 'deal' },
  { id: 2, text: 'New contact: Priya Patel from Innovate AI', time: '2024-03-22T13:00:00Z', type: 'contact' },
  { id: 3, text: 'Completed call with NexGen Solutions', time: '2024-03-22T15:30:00Z', type: 'activity' },
  { id: 4, text: 'New deal: Innovate AI Research Platform ($180K)', time: '2024-03-20T10:00:00Z', type: 'deal' },
  { id: 5, text: 'Moved GlobalTech Platform Migration to Proposal', time: '2024-03-19T16:00:00Z', type: 'deal' },
  { id: 6, text: 'New deal: GlobalTech Security Audit ($60K)', time: '2024-03-18T11:00:00Z', type: 'deal' },
];

export function useDashboard() {
  const [stats, setStats] = useState<DashboardStats | null>(null);
  const [pipeline, setPipeline] = useState<PipelineStage[]>([]);
  const [recentActivity, setRecentActivity] = useState<RecentActivity[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    setTimeout(() => {
      setStats(DEMO_STATS);
      setPipeline(DEMO_PIPELINE);
      setRecentActivity(DEMO_RECENT);
      setLoading(false);
    }, 400);
  }, []);

  return { stats, pipeline, recentActivity, loading };
}
