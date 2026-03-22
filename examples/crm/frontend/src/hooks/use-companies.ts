'use client';

import { useState, useEffect, useCallback } from 'react';
import type { Company, PaginatedResponse } from '@/lib/types';

const DEMO_COMPANIES: Company[] = [
  { id: 1, name: 'Acme Corp', domain: 'acmecorp.com', industry: 'Technology', size: '51-200', phone: '+1 555-1000', website: 'https://acmecorp.com', annual_revenue: 12000000, owner_id: 1, created_at: '2024-01-10T00:00:00Z' },
  { id: 2, name: 'GlobalTech', domain: 'globaltech.io', industry: 'SaaS', size: '201-500', phone: '+1 555-2000', website: 'https://globaltech.io', annual_revenue: 45000000, owner_id: 1, created_at: '2024-01-12T00:00:00Z' },
  { id: 3, name: 'StartupCo', domain: 'startup.co', industry: 'Fintech', size: '11-50', phone: '+1 555-3000', website: 'https://startup.co', annual_revenue: 2500000, owner_id: 1, created_at: '2024-02-01T00:00:00Z' },
  { id: 4, name: 'BigCorp Industries', domain: 'bigcorp.com', industry: 'Manufacturing', size: '1000+', phone: '+1 555-4000', website: 'https://bigcorp.com', annual_revenue: 500000000, owner_id: 1, created_at: '2024-01-05T00:00:00Z' },
  { id: 5, name: 'NexGen Solutions', domain: 'nexgen.io', industry: 'Consulting', size: '11-50', phone: '+1 555-5000', website: 'https://nexgen.io', annual_revenue: 5000000, owner_id: 1, created_at: '2024-03-01T00:00:00Z' },
  { id: 6, name: 'EuroTech', domain: 'eurotech.eu', industry: 'Technology', size: '201-500', phone: '+33 1-555-6000', website: 'https://eurotech.eu', annual_revenue: 28000000, owner_id: 1, created_at: '2024-02-15T00:00:00Z' },
  { id: 7, name: 'OldSchool Inc', domain: 'oldschool.com', industry: 'Retail', size: '51-200', phone: '+1 555-7000', website: 'https://oldschool.com', annual_revenue: 8000000, owner_id: 1, created_at: '2023-06-01T00:00:00Z' },
  { id: 8, name: 'Innovate AI', domain: 'innovate.ai', industry: 'AI/ML', size: '11-50', phone: '+1 555-8000', website: 'https://innovate.ai', annual_revenue: 1200000, owner_id: 1, created_at: '2024-03-20T00:00:00Z' },
];

export function useCompanies(params?: { page?: number; search?: string }) {
  const [data, setData] = useState<PaginatedResponse<Company> | null>(null);
  const [loading, setLoading] = useState(true);

  const fetchCompanies = useCallback(() => {
    setLoading(true);
    setTimeout(() => {
      let filtered = [...DEMO_COMPANIES];
      if (params?.search) {
        const s = params.search.toLowerCase();
        filtered = filtered.filter(
          (c) => c.name.toLowerCase().includes(s) || c.industry?.toLowerCase().includes(s),
        );
      }
      setData({
        data: filtered,
        total: filtered.length,
        page: params?.page || 1,
        per_page: 10,
        last_page: 1,
      });
      setLoading(false);
    }, 300);
  }, [params?.page, params?.search]);

  useEffect(() => {
    fetchCompanies();
  }, [fetchCompanies]);

  return { data, loading, refetch: fetchCompanies };
}

export function useCompany(id: number) {
  const [company, setCompany] = useState<Company | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    setTimeout(() => {
      setCompany(DEMO_COMPANIES.find((c) => c.id === id) || null);
      setLoading(false);
    }, 200);
  }, [id]);

  return { company, loading };
}

export { DEMO_COMPANIES };
