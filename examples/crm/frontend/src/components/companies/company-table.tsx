'use client';

import Link from 'next/link';
import { Table, TableHeader, TableBody, TableRow, TableHead, TableCell } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Avatar } from '@/components/ui/avatar';
import { formatCurrency, formatDate } from '@/lib/utils';
import type { Company } from '@/lib/types';

interface CompanyTableProps {
  companies: Company[];
}

const industryVariants: Record<string, 'default' | 'info' | 'success' | 'warning' | 'purple' | 'secondary'> = {
  Technology: 'default',
  SaaS: 'info',
  Fintech: 'success',
  Manufacturing: 'warning',
  Consulting: 'purple',
  Retail: 'secondary',
  'AI/ML': 'default',
};

const sizeLabels: Record<string, string> = {
  '1-10': 'Micro',
  '11-50': 'Small',
  '51-200': 'Medium',
  '201-500': 'Large',
  '501-1000': 'Enterprise',
  '1000+': 'Enterprise+',
};

export function CompanyTable({ companies }: CompanyTableProps) {
  return (
    <Table>
      <TableHeader>
        <TableRow>
          <TableHead>Company</TableHead>
          <TableHead>Industry</TableHead>
          <TableHead>Size</TableHead>
          <TableHead>Revenue</TableHead>
          <TableHead>Website</TableHead>
          <TableHead>Created</TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        {companies.map((company) => (
          <TableRow key={company.id} className="cursor-pointer">
            <TableCell>
              <Link href={`/companies/${company.id}`} className="flex items-center gap-3">
                <Avatar fallback={company.name} size="sm" />
                <div>
                  <span className="font-medium text-foreground">{company.name}</span>
                  {company.domain && (
                    <p className="text-xs text-muted-foreground">{company.domain}</p>
                  )}
                </div>
              </Link>
            </TableCell>
            <TableCell>
              {company.industry ? (
                <Badge variant={industryVariants[company.industry] || 'secondary'}>
                  {company.industry}
                </Badge>
              ) : (
                <span className="text-muted-foreground">--</span>
              )}
            </TableCell>
            <TableCell>
              {company.size ? (
                <div>
                  <span className="text-sm text-foreground">{sizeLabels[company.size] || company.size}</span>
                  <p className="text-xs text-muted-foreground">{company.size} employees</p>
                </div>
              ) : (
                <span className="text-muted-foreground">--</span>
              )}
            </TableCell>
            <TableCell>
              {company.annual_revenue ? (
                <span className="font-medium text-foreground">
                  {formatCurrency(company.annual_revenue)}
                </span>
              ) : (
                <span className="text-muted-foreground">--</span>
              )}
            </TableCell>
            <TableCell>
              {company.website ? (
                <a
                  href={company.website}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="text-primary hover:text-primary text-sm"
                >
                  {company.domain}
                </a>
              ) : (
                <span className="text-muted-foreground">--</span>
              )}
            </TableCell>
            <TableCell>
              <span className="text-muted-foreground">{formatDate(company.created_at)}</span>
            </TableCell>
          </TableRow>
        ))}
        {companies.length === 0 && (
          <TableRow>
            <TableCell colSpan={6} className="text-center py-8 text-muted-foreground">
              No companies found
            </TableCell>
          </TableRow>
        )}
      </TableBody>
    </Table>
  );
}
