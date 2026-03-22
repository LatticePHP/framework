'use client';

import { useState, useEffect, useCallback } from 'react';
import type { Activity, PaginatedResponse } from '@/lib/types';

const DEMO_ACTIVITIES: Activity[] = [
  { id: 1, type: 'call', title: 'Follow up call with Alex Thompson', description: 'Discuss enterprise license terms and pricing adjustments', due_date: '2024-03-25T10:00:00Z', contact_id: 1, deal_id: 1, owner_id: 1, priority: 'high' },
  { id: 2, type: 'meeting', title: 'Product demo for GlobalTech', description: 'Showcase platform migration capabilities', due_date: '2024-03-26T14:00:00Z', contact_id: 2, deal_id: 2, owner_id: 1, priority: 'high' },
  { id: 3, type: 'task', title: 'Prepare proposal for StartupCo', description: 'Include initial setup timeline and pricing tiers', due_date: '2024-03-24T17:00:00Z', contact_id: 3, deal_id: 3, owner_id: 1, priority: 'medium' },
  { id: 4, type: 'email', title: 'Send case study to Emily Chen', description: 'Manufacturing digital transformation success stories', due_date: '2024-03-23T09:00:00Z', contact_id: 4, deal_id: 4, owner_id: 1, priority: 'low' },
  { id: 5, type: 'call', title: 'Negotiate terms with NexGen', description: 'Final negotiation on consulting package scope', due_date: '2024-03-22T15:00:00Z', completed_at: '2024-03-22T15:30:00Z', contact_id: 5, deal_id: 5, owner_id: 1, priority: 'high' },
  { id: 6, type: 'meeting', title: 'Quarterly review with Acme Corp', description: 'Review support metrics and renewal discussion', due_date: '2024-03-28T11:00:00Z', contact_id: 1, deal_id: 7, owner_id: 1, priority: 'medium' },
  { id: 7, type: 'task', title: 'Update CRM records for Q1', description: 'Clean up duplicate contacts and verify company info', due_date: '2024-03-20T17:00:00Z', owner_id: 1, priority: 'low' },
  { id: 8, type: 'email', title: 'Send welcome packet to Priya Patel', description: 'Include onboarding docs and platform access links', due_date: '2024-03-23T10:00:00Z', contact_id: 8, deal_id: 9, owner_id: 1, priority: 'medium' },
  { id: 9, type: 'call', title: 'Cold call follow-up batch', description: 'Follow up on last week outreach campaign', due_date: '2024-03-21T09:00:00Z', completed_at: '2024-03-21T10:00:00Z', owner_id: 1, priority: 'low' },
  { id: 10, type: 'meeting', title: 'EuroTech API requirements gathering', description: 'Technical deep-dive on integration requirements', due_date: '2024-03-27T09:00:00Z', contact_id: 6, deal_id: 6, owner_id: 1, priority: 'high' },
];

export function useActivities(params?: { filter?: 'upcoming' | 'overdue' | 'all'; page?: number }) {
  const [data, setData] = useState<PaginatedResponse<Activity> | null>(null);
  const [loading, setLoading] = useState(true);

  const fetchActivities = useCallback(() => {
    setLoading(true);
    setTimeout(() => {
      const now = new Date();
      let filtered = [...DEMO_ACTIVITIES];

      if (params?.filter === 'upcoming') {
        filtered = filtered.filter((a) => !a.completed_at && new Date(a.due_date) >= now);
      } else if (params?.filter === 'overdue') {
        filtered = filtered.filter((a) => !a.completed_at && new Date(a.due_date) < now);
      }

      setData({
        data: filtered,
        total: filtered.length,
        page: params?.page || 1,
        per_page: 20,
        last_page: 1,
      });
      setLoading(false);
    }, 300);
  }, [params?.filter, params?.page]);

  useEffect(() => {
    fetchActivities();
  }, [fetchActivities]);

  return { data, loading, refetch: fetchActivities };
}

export { DEMO_ACTIVITIES };
