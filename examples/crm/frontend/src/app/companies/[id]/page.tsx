'use client';

import { use } from 'react';
import Link from 'next/link';
import { Edit, Globe, Phone, Building2, DollarSign, Users } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Avatar } from '@/components/ui/avatar';
import { Skeleton } from '@/components/ui/skeleton';
import { Breadcrumbs } from '@/components/layout/breadcrumbs';
import { useCompany } from '@/hooks/use-companies';
import { DEMO_CONTACTS } from '@/hooks/use-contacts';
import { DEMO_DEALS } from '@/hooks/use-deals';
import { formatCurrency, formatDate, contactName } from '@/lib/utils';

export default function CompanyDetailPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const companyId = parseInt(id);
  const { company, loading } = useCompany(companyId);

  const companyContacts = DEMO_CONTACTS.filter((c) => c.company_id === companyId);
  const companyDeals = DEMO_DEALS.filter((d) => d.company_id === companyId);

  if (loading) {
    return (
      <div className="space-y-6">
        <Skeleton className="h-6 w-48" />
        <Skeleton className="h-48 w-full" />
      </div>
    );
  }

  if (!company) {
    return (
      <div className="flex flex-col items-center justify-center py-20">
        <p className="text-lg text-muted-foreground">Company not found</p>
        <Link href="/companies" className="mt-4 text-primary hover:text-primary">
          Back to Companies
        </Link>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <Breadcrumbs
        items={[
          { label: 'Companies', href: '/companies' },
          { label: company.name },
        ]}
      />

      {/* Company Header */}
      <Card className="p-6">
        <div className="flex items-start justify-between">
          <div className="flex items-start gap-4">
            <Avatar fallback={company.name} size="lg" />
            <div>
              <h1 className="text-2xl font-bold text-foreground">{company.name}</h1>
              <div className="mt-2 flex flex-wrap gap-3">
                {company.industry && <Badge>{company.industry}</Badge>}
                {company.size && <Badge variant="secondary">{company.size} employees</Badge>}
              </div>
              <div className="mt-3 flex flex-wrap gap-4 text-sm text-muted-foreground">
                {company.website && (
                  <a href={company.website} target="_blank" rel="noopener noreferrer" className="flex items-center gap-1 hover:text-primary">
                    <Globe className="h-4 w-4" />
                    {company.domain}
                  </a>
                )}
                {company.phone && (
                  <span className="flex items-center gap-1">
                    <Phone className="h-4 w-4" />
                    {company.phone}
                  </span>
                )}
                {company.annual_revenue && (
                  <span className="flex items-center gap-1">
                    <DollarSign className="h-4 w-4" />
                    {formatCurrency(company.annual_revenue)} annual revenue
                  </span>
                )}
              </div>
            </div>
          </div>
          <Button variant="outline" size="sm" className="gap-1.5">
            <Edit className="h-3.5 w-3.5" />
            Edit
          </Button>
        </div>
      </Card>

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {/* Contacts */}
        <Card>
          <CardContent className="p-6">
            <div className="flex items-center justify-between mb-4">
              <h3 className="text-sm font-semibold text-foreground flex items-center gap-2">
                <Users className="h-4 w-4 text-muted-foreground" />
                Contacts ({companyContacts.length})
              </h3>
            </div>
            <div className="space-y-3">
              {companyContacts.map((c) => (
                <Link key={c.id} href={`/contacts/${c.id}`} className="flex items-center gap-3 rounded-lg p-2 hover:bg-accent">
                  <Avatar fallback={contactName(c)} size="sm" />
                  <div className="flex-1 min-w-0">
                    <p className="text-sm font-medium text-foreground">{contactName(c)}</p>
                    <p className="text-xs text-muted-foreground">{c.title}</p>
                  </div>
                  <Badge variant={c.status === 'customer' ? 'success' : c.status === 'lead' ? 'info' : 'secondary'} className="text-xs">
                    {c.status}
                  </Badge>
                </Link>
              ))}
              {companyContacts.length === 0 && (
                <p className="text-sm text-muted-foreground text-center py-4">No contacts</p>
              )}
            </div>
          </CardContent>
        </Card>

        {/* Deals */}
        <Card>
          <CardContent className="p-6">
            <div className="flex items-center justify-between mb-4">
              <h3 className="text-sm font-semibold text-foreground flex items-center gap-2">
                <Building2 className="h-4 w-4 text-muted-foreground" />
                Deals ({companyDeals.length})
              </h3>
            </div>
            <div className="space-y-3">
              {companyDeals.map((d) => (
                <Link key={d.id} href={`/deals/${d.id}`} className="flex items-center justify-between rounded-lg p-2 hover:bg-accent">
                  <div className="min-w-0">
                    <p className="text-sm font-medium text-foreground truncate">{d.title}</p>
                    <p className="text-xs text-muted-foreground capitalize">{d.stage.replace('_', ' ')}</p>
                  </div>
                  <span className="text-sm font-bold text-foreground">{formatCurrency(d.value)}</span>
                </Link>
              ))}
              {companyDeals.length === 0 && (
                <p className="text-sm text-muted-foreground text-center py-4">No deals</p>
              )}
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Company Info */}
      <Card>
        <CardContent className="p-6">
          <h3 className="text-sm font-semibold text-foreground mb-4">Company Details</h3>
          <dl className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
              <dt className="text-sm text-muted-foreground">Domain</dt>
              <dd className="text-sm font-medium text-foreground">{company.domain || '--'}</dd>
            </div>
            <div>
              <dt className="text-sm text-muted-foreground">Industry</dt>
              <dd className="text-sm font-medium text-foreground">{company.industry || '--'}</dd>
            </div>
            <div>
              <dt className="text-sm text-muted-foreground">Size</dt>
              <dd className="text-sm font-medium text-foreground">{company.size ? `${company.size} employees` : '--'}</dd>
            </div>
            <div>
              <dt className="text-sm text-muted-foreground">Annual Revenue</dt>
              <dd className="text-sm font-medium text-foreground">{company.annual_revenue ? formatCurrency(company.annual_revenue) : '--'}</dd>
            </div>
            <div>
              <dt className="text-sm text-muted-foreground">Phone</dt>
              <dd className="text-sm font-medium text-foreground">{company.phone || '--'}</dd>
            </div>
            <div>
              <dt className="text-sm text-muted-foreground">Created</dt>
              <dd className="text-sm font-medium text-foreground">{formatDate(company.created_at)}</dd>
            </div>
          </dl>
        </CardContent>
      </Card>
    </div>
  );
}
