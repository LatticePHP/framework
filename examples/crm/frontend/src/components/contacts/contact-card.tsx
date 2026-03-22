'use client';

import { Mail, Phone, Building2, MapPin } from 'lucide-react';
import { Avatar } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { contactName } from '@/lib/utils';
import type { Contact } from '@/lib/types';

interface ContactCardProps {
  contact: Contact;
}

const statusVariants = {
  lead: 'info' as const,
  prospect: 'warning' as const,
  customer: 'success' as const,
  churned: 'danger' as const,
};

export function ContactCard({ contact }: ContactCardProps) {
  return (
    <div className="flex items-start gap-6">
      <Avatar fallback={contactName(contact)} size="lg" />
      <div className="flex-1">
        <div className="flex items-center gap-3">
          <h1 className="text-2xl font-bold text-foreground">{contactName(contact)}</h1>
          <Badge variant={statusVariants[contact.status]}>{contact.status}</Badge>
        </div>
        {contact.title && (
          <p className="mt-1 text-lg text-muted-foreground">{contact.title}</p>
        )}
        <div className="mt-4 flex flex-wrap gap-4">
          <div className="flex items-center gap-2 text-sm text-muted-foreground">
            <Mail className="h-4 w-4 text-muted-foreground" />
            <a href={`mailto:${contact.email}`} className="hover:text-primary">
              {contact.email}
            </a>
          </div>
          {contact.phone && (
            <div className="flex items-center gap-2 text-sm text-muted-foreground">
              <Phone className="h-4 w-4 text-muted-foreground" />
              <a href={`tel:${contact.phone}`} className="hover:text-primary">
                {contact.phone}
              </a>
            </div>
          )}
          {contact.company && (
            <div className="flex items-center gap-2 text-sm text-muted-foreground">
              <Building2 className="h-4 w-4 text-muted-foreground" />
              <span>{contact.company.name}</span>
            </div>
          )}
        </div>
        {contact.tags && contact.tags.length > 0 && (
          <div className="mt-3 flex gap-2">
            {contact.tags.map((tag) => (
              <Badge key={tag} variant="secondary">{tag}</Badge>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
