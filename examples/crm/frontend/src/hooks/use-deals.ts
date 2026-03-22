'use client';

import { useState, useEffect, useCallback } from 'react';
import type { Deal, PaginatedResponse } from '@/lib/types';

const DEMO_DEALS: Deal[] = [
  { id: 1, title: 'Acme Corp Enterprise License', value: 120000, currency: 'USD', stage: 'negotiation', probability: 75, contact_id: 1, contact: { id: 1, first_name: 'Alex', last_name: 'Thompson', email: 'alex@acmecorp.com', status: 'customer', owner_id: 1, created_at: '2024-01-15T10:30:00Z' }, company_id: 1, owner_id: 1, expected_close_date: '2024-04-15T00:00:00Z', created_at: '2024-02-01T10:00:00Z' },
  { id: 2, title: 'GlobalTech Platform Migration', value: 250000, currency: 'USD', stage: 'proposal', probability: 50, contact_id: 2, contact: { id: 2, first_name: 'Maria', last_name: 'Garcia', email: 'maria@globaltech.io', status: 'prospect', owner_id: 1, created_at: '2024-02-20T14:00:00Z' }, company_id: 2, owner_id: 1, expected_close_date: '2024-05-01T00:00:00Z', created_at: '2024-02-15T09:00:00Z' },
  { id: 3, title: 'StartupCo Initial Setup', value: 15000, currency: 'USD', stage: 'qualified', probability: 30, contact_id: 3, contact: { id: 3, first_name: 'James', last_name: 'Wilson', email: 'james@startup.co', status: 'lead', owner_id: 1, created_at: '2024-03-01T09:00:00Z' }, company_id: 3, owner_id: 1, expected_close_date: '2024-04-30T00:00:00Z', created_at: '2024-03-05T14:00:00Z' },
  { id: 4, title: 'BigCorp Digital Transformation', value: 500000, currency: 'USD', stage: 'lead', probability: 10, contact_id: 4, contact: { id: 4, first_name: 'Emily', last_name: 'Chen', email: 'emily@bigcorp.com', status: 'customer', owner_id: 1, created_at: '2024-01-20T11:00:00Z' }, company_id: 4, owner_id: 1, expected_close_date: '2024-06-30T00:00:00Z', created_at: '2024-03-10T10:00:00Z' },
  { id: 5, title: 'NexGen Consulting Package', value: 35000, currency: 'USD', stage: 'negotiation', probability: 80, contact_id: 5, contact: { id: 5, first_name: 'David', last_name: 'Kim', email: 'david@nexgen.io', status: 'prospect', owner_id: 1, created_at: '2024-03-10T16:00:00Z' }, company_id: 5, owner_id: 1, expected_close_date: '2024-04-10T00:00:00Z', created_at: '2024-03-01T11:00:00Z' },
  { id: 6, title: 'EuroTech API Integration', value: 75000, currency: 'USD', stage: 'proposal', probability: 40, contact_id: 6, contact: { id: 6, first_name: 'Sophie', last_name: 'Laurent', email: 'sophie@eurotech.eu', status: 'lead', owner_id: 1, created_at: '2024-03-15T08:00:00Z' }, company_id: 6, owner_id: 1, expected_close_date: '2024-05-15T00:00:00Z', created_at: '2024-03-12T15:00:00Z' },
  { id: 7, title: 'Acme Corp Support Renewal', value: 24000, currency: 'USD', stage: 'closed_won', probability: 100, contact_id: 1, contact: { id: 1, first_name: 'Alex', last_name: 'Thompson', email: 'alex@acmecorp.com', status: 'customer', owner_id: 1, created_at: '2024-01-15T10:30:00Z' }, company_id: 1, owner_id: 1, expected_close_date: '2024-03-01T00:00:00Z', created_at: '2024-01-15T10:00:00Z' },
  { id: 8, title: 'OldSchool POS System', value: 45000, currency: 'USD', stage: 'closed_lost', probability: 0, contact_id: 7, contact: { id: 7, first_name: 'Michael', last_name: 'Brown', email: 'michael@oldschool.com', status: 'churned', owner_id: 1, created_at: '2023-08-15T10:00:00Z' }, company_id: 7, owner_id: 1, expected_close_date: '2024-02-28T00:00:00Z', created_at: '2023-11-01T09:00:00Z' },
  { id: 9, title: 'Innovate AI Research Platform', value: 180000, currency: 'USD', stage: 'qualified', probability: 25, contact_id: 8, contact: { id: 8, first_name: 'Priya', last_name: 'Patel', email: 'priya@innovate.ai', status: 'lead', owner_id: 1, created_at: '2024-03-22T13:00:00Z' }, company_id: 8, owner_id: 1, expected_close_date: '2024-06-01T00:00:00Z', created_at: '2024-03-20T10:00:00Z' },
  { id: 10, title: 'GlobalTech Security Audit', value: 60000, currency: 'USD', stage: 'lead', probability: 15, contact_id: 2, contact: { id: 2, first_name: 'Maria', last_name: 'Garcia', email: 'maria@globaltech.io', status: 'prospect', owner_id: 1, created_at: '2024-02-20T14:00:00Z' }, company_id: 2, owner_id: 1, expected_close_date: '2024-07-01T00:00:00Z', created_at: '2024-03-18T11:00:00Z' },
];

export function useDeals(params?: { page?: number; stage?: string }) {
  const [data, setData] = useState<PaginatedResponse<Deal> | null>(null);
  const [loading, setLoading] = useState(true);

  const fetchDeals = useCallback(() => {
    setLoading(true);
    setTimeout(() => {
      let filtered = [...DEMO_DEALS];
      if (params?.stage) {
        filtered = filtered.filter((d) => d.stage === params.stage);
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
  }, [params?.page, params?.stage]);

  useEffect(() => {
    fetchDeals();
  }, [fetchDeals]);

  return { data, loading, refetch: fetchDeals };
}

export function useDeal(id: number) {
  const [deal, setDeal] = useState<Deal | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    setTimeout(() => {
      setDeal(DEMO_DEALS.find((d) => d.id === id) || null);
      setLoading(false);
    }, 200);
  }, [id]);

  return { deal, loading };
}

export { DEMO_DEALS };
