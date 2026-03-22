'use client';

import { useState } from 'react';
import Link from 'next/link';
import { Plus, Search } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Card } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { Breadcrumbs } from '@/components/layout/breadcrumbs';
import { ContactTable } from '@/components/contacts/contact-table';
import { useContacts } from '@/hooks/use-contacts';

export default function ContactsPage() {
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState('');
  const [page, setPage] = useState(1);
  const { data, loading } = useContacts({ page, search, status: status || undefined });

  return (
    <div className="space-y-6">
      <Breadcrumbs items={[{ label: 'Contacts' }]} />

      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">Contacts</h1>
          <p className="text-sm text-slate-500">{data?.total ?? 0} total contacts</p>
        </div>
        <Link href="/contacts/new">
          <Button className="gap-2">
            <Plus className="h-4 w-4" />
            Add Contact
          </Button>
        </Link>
      </div>

      {/* Filters */}
      <div className="flex flex-wrap items-center gap-3">
        <div className="relative flex-1 max-w-sm">
          <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
          <Input
            placeholder="Search contacts..."
            value={search}
            onChange={(e) => { setSearch(e.target.value); setPage(1); }}
            className="pl-10"
          />
        </div>
        <Select
          value={status}
          onChange={(e) => { setStatus(e.target.value); setPage(1); }}
          className="w-40"
        >
          <option value="">All statuses</option>
          <option value="lead">Lead</option>
          <option value="prospect">Prospect</option>
          <option value="customer">Customer</option>
          <option value="churned">Churned</option>
        </Select>
      </div>

      {/* Table */}
      <Card>
        {loading ? (
          <div className="p-6 space-y-3">
            {[1, 2, 3, 4, 5].map((i) => (
              <Skeleton key={i} className="h-12 w-full" />
            ))}
          </div>
        ) : (
          <ContactTable contacts={data?.data ?? []} />
        )}
      </Card>

      {/* Pagination */}
      {data && data.last_page > 1 && (
        <div className="flex items-center justify-between">
          <p className="text-sm text-slate-500">
            Showing page {data.page} of {data.last_page}
          </p>
          <div className="flex gap-2">
            <Button
              variant="outline"
              size="sm"
              disabled={page <= 1}
              onClick={() => setPage(page - 1)}
            >
              Previous
            </Button>
            <Button
              variant="outline"
              size="sm"
              disabled={page >= data.last_page}
              onClick={() => setPage(page + 1)}
            >
              Next
            </Button>
          </div>
        </div>
      )}
    </div>
  );
}
