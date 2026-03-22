'use client';

import Link from 'next/link';
import { Table, TableHeader, TableBody, TableRow, TableHead, TableCell } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Avatar } from '@/components/ui/avatar';
import { contactName, formatDate } from '@/lib/utils';
import type { Contact } from '@/lib/types';

interface ContactTableProps {
  contacts: Contact[];
}

const statusVariants = {
  lead: 'info' as const,
  prospect: 'warning' as const,
  customer: 'success' as const,
  churned: 'danger' as const,
};

const statusLabels = {
  lead: 'Lead',
  prospect: 'Prospect',
  customer: 'Customer',
  churned: 'Churned',
};

export function ContactTable({ contacts }: ContactTableProps) {
  return (
    <Table>
      <TableHeader>
        <TableRow>
          <TableHead>Name</TableHead>
          <TableHead>Email</TableHead>
          <TableHead>Company</TableHead>
          <TableHead>Status</TableHead>
          <TableHead>Title</TableHead>
          <TableHead>Created</TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        {contacts.map((contact) => (
          <TableRow key={contact.id} className="cursor-pointer">
            <TableCell>
              <Link href={`/contacts/${contact.id}`} className="flex items-center gap-3">
                <Avatar fallback={contactName(contact)} size="sm" />
                <span className="font-medium text-foreground">{contactName(contact)}</span>
              </Link>
            </TableCell>
            <TableCell>
              <span className="text-muted-foreground">{contact.email}</span>
            </TableCell>
            <TableCell>
              {contact.company ? (
                <Link
                  href={`/companies/${contact.company.id}`}
                  className="text-primary hover:text-primary"
                >
                  {contact.company.name}
                </Link>
              ) : (
                <span className="text-muted-foreground">--</span>
              )}
            </TableCell>
            <TableCell>
              <Badge variant={statusVariants[contact.status]}>
                {statusLabels[contact.status]}
              </Badge>
            </TableCell>
            <TableCell>
              <span className="text-muted-foreground">{contact.title || '--'}</span>
            </TableCell>
            <TableCell>
              <span className="text-muted-foreground">{formatDate(contact.created_at)}</span>
            </TableCell>
          </TableRow>
        ))}
        {contacts.length === 0 && (
          <TableRow>
            <TableCell colSpan={6} className="text-center py-8 text-muted-foreground">
              No contacts found
            </TableCell>
          </TableRow>
        )}
      </TableBody>
    </Table>
  );
}
