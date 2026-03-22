'use client';

import { useState, useEffect, useCallback } from 'react';
import type { Contact, PaginatedResponse } from '@/lib/types';

// Demo data
const DEMO_CONTACTS: Contact[] = [
  { id: 1, first_name: 'Alex', last_name: 'Thompson', email: 'alex@acmecorp.com', phone: '+1 555-0101', company_id: 1, company: { id: 1, name: 'Acme Corp', industry: 'Technology', size: '51-200', owner_id: 1, created_at: '2024-01-10T00:00:00Z' }, title: 'VP of Engineering', status: 'customer', source: 'referral', owner_id: 1, tags: ['enterprise', 'tech'], created_at: '2024-01-15T10:30:00Z' },
  { id: 2, first_name: 'Maria', last_name: 'Garcia', email: 'maria@globaltech.io', phone: '+1 555-0102', company_id: 2, company: { id: 2, name: 'GlobalTech', industry: 'SaaS', size: '201-500', owner_id: 1, created_at: '2024-01-12T00:00:00Z' }, title: 'CTO', status: 'prospect', source: 'website', owner_id: 1, tags: ['saas'], created_at: '2024-02-20T14:00:00Z' },
  { id: 3, first_name: 'James', last_name: 'Wilson', email: 'james@startup.co', phone: '+1 555-0103', company_id: 3, company: { id: 3, name: 'StartupCo', industry: 'Fintech', size: '11-50', owner_id: 1, created_at: '2024-02-01T00:00:00Z' }, title: 'CEO', status: 'lead', source: 'linkedin', owner_id: 1, tags: ['fintech', 'startup'], created_at: '2024-03-01T09:00:00Z' },
  { id: 4, first_name: 'Emily', last_name: 'Chen', email: 'emily@bigcorp.com', phone: '+1 555-0104', company_id: 4, company: { id: 4, name: 'BigCorp Industries', industry: 'Manufacturing', size: '1000+', owner_id: 1, created_at: '2024-01-05T00:00:00Z' }, title: 'Director of IT', status: 'customer', source: 'conference', owner_id: 1, tags: ['enterprise'], created_at: '2024-01-20T11:00:00Z' },
  { id: 5, first_name: 'David', last_name: 'Kim', email: 'david@nexgen.io', phone: '+1 555-0105', company_id: 5, company: { id: 5, name: 'NexGen Solutions', industry: 'Consulting', size: '11-50', owner_id: 1, created_at: '2024-03-01T00:00:00Z' }, title: 'Managing Partner', status: 'prospect', source: 'referral', owner_id: 1, tags: ['consulting'], created_at: '2024-03-10T16:00:00Z' },
  { id: 6, first_name: 'Sophie', last_name: 'Laurent', email: 'sophie@eurotech.eu', phone: '+33 1-555-0106', company_id: 6, company: { id: 6, name: 'EuroTech', industry: 'Technology', size: '201-500', owner_id: 1, created_at: '2024-02-15T00:00:00Z' }, title: 'Head of Product', status: 'lead', source: 'website', owner_id: 1, tags: ['international'], created_at: '2024-03-15T08:00:00Z' },
  { id: 7, first_name: 'Michael', last_name: 'Brown', email: 'michael@oldschool.com', phone: '+1 555-0107', company_id: 7, company: { id: 7, name: 'OldSchool Inc', industry: 'Retail', size: '51-200', owner_id: 1, created_at: '2023-06-01T00:00:00Z' }, title: 'Owner', status: 'churned', source: 'cold-call', owner_id: 1, tags: ['retail'], created_at: '2023-08-15T10:00:00Z' },
  { id: 8, first_name: 'Priya', last_name: 'Patel', email: 'priya@innovate.ai', phone: '+1 555-0108', company_id: 8, company: { id: 8, name: 'Innovate AI', industry: 'AI/ML', size: '11-50', owner_id: 1, created_at: '2024-03-20T00:00:00Z' }, title: 'Founder', status: 'lead', source: 'event', owner_id: 1, tags: ['ai', 'startup'], created_at: '2024-03-22T13:00:00Z' },
];

export function useContacts(params?: { page?: number; search?: string; status?: string }) {
  const [data, setData] = useState<PaginatedResponse<Contact> | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchContacts = useCallback(() => {
    setLoading(true);
    // Simulate API call with demo data
    setTimeout(() => {
      let filtered = [...DEMO_CONTACTS];
      if (params?.search) {
        const s = params.search.toLowerCase();
        filtered = filtered.filter(
          (c) =>
            c.first_name.toLowerCase().includes(s) ||
            c.last_name.toLowerCase().includes(s) ||
            c.email.toLowerCase().includes(s) ||
            c.company?.name.toLowerCase().includes(s),
        );
      }
      if (params?.status) {
        filtered = filtered.filter((c) => c.status === params.status);
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
  }, [params?.page, params?.search, params?.status]);

  useEffect(() => {
    fetchContacts();
  }, [fetchContacts]);

  return { data, loading, error, refetch: fetchContacts };
}

export function useContact(id: number) {
  const [contact, setContact] = useState<Contact | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    setTimeout(() => {
      const found = DEMO_CONTACTS.find((c) => c.id === id) || null;
      setContact(found);
      setLoading(false);
    }, 200);
  }, [id]);

  return { contact, loading };
}

export { DEMO_CONTACTS };
