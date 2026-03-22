'use client';

import { use } from 'react';
import Link from 'next/link';
import { Edit, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import { Breadcrumbs } from '@/components/layout/breadcrumbs';
import { ContactCard } from '@/components/contacts/contact-card';
import { NoteList } from '@/components/notes/note-list';
import { NoteForm } from '@/components/notes/note-form';
import { ActivityList } from '@/components/activities/activity-list';
import { useContact } from '@/hooks/use-contacts';
import { useNotes } from '@/hooks/use-notes';
import { contactName, formatDate, formatCurrency } from '@/lib/utils';
import { DEMO_DEALS } from '@/hooks/use-deals';
import { DEMO_ACTIVITIES } from '@/hooks/use-activities';

export default function ContactDetailPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const contactId = parseInt(id);
  const { contact, loading } = useContact(contactId);
  const { notes, addNote } = useNotes('contact', contactId);

  const contactDeals = DEMO_DEALS.filter((d) => d.contact_id === contactId);
  const contactActivities = DEMO_ACTIVITIES.filter((a) => a.contact_id === contactId);

  if (loading) {
    return (
      <div className="space-y-6">
        <Skeleton className="h-6 w-48" />
        <Skeleton className="h-32 w-full" />
        <Skeleton className="h-64 w-full" />
      </div>
    );
  }

  if (!contact) {
    return (
      <div className="flex flex-col items-center justify-center py-20">
        <p className="text-lg text-muted-foreground">Contact not found</p>
        <Link href="/contacts" className="mt-4 text-primary hover:text-primary">
          Back to Contacts
        </Link>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <Breadcrumbs
        items={[
          { label: 'Contacts', href: '/contacts' },
          { label: contactName(contact) },
        ]}
      />

      {/* Header */}
      <Card className="p-6">
        <div className="flex items-start justify-between">
          <ContactCard contact={contact} />
          <div className="flex gap-2">
            <Button variant="outline" size="sm" className="gap-1.5">
              <Edit className="h-3.5 w-3.5" />
              Edit
            </Button>
            <Button variant="outline" size="sm" className="gap-1.5 text-destructive hover:bg-destructive/10">
              <Trash2 className="h-3.5 w-3.5" />
              Delete
            </Button>
          </div>
        </div>
      </Card>

      {/* Tabs */}
      <Tabs defaultValue="overview">
        <TabsList>
          <TabsTrigger value="overview">Overview</TabsTrigger>
          <TabsTrigger value="deals">Deals ({contactDeals.length})</TabsTrigger>
          <TabsTrigger value="activities">Activities ({contactActivities.length})</TabsTrigger>
          <TabsTrigger value="notes">Notes ({notes.length})</TabsTrigger>
        </TabsList>

        <TabsContent value="overview">
          <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <Card>
              <CardContent className="p-6">
                <h3 className="text-sm font-semibold text-foreground mb-4">Contact Information</h3>
                <dl className="space-y-3">
                  <div className="flex justify-between">
                    <dt className="text-sm text-muted-foreground">Email</dt>
                    <dd className="text-sm font-medium text-foreground">{contact.email}</dd>
                  </div>
                  <div className="flex justify-between">
                    <dt className="text-sm text-muted-foreground">Phone</dt>
                    <dd className="text-sm font-medium text-foreground">{contact.phone || '--'}</dd>
                  </div>
                  <div className="flex justify-between">
                    <dt className="text-sm text-muted-foreground">Title</dt>
                    <dd className="text-sm font-medium text-foreground">{contact.title || '--'}</dd>
                  </div>
                  <div className="flex justify-between">
                    <dt className="text-sm text-muted-foreground">Company</dt>
                    <dd className="text-sm font-medium text-foreground">{contact.company?.name || '--'}</dd>
                  </div>
                  <div className="flex justify-between">
                    <dt className="text-sm text-muted-foreground">Source</dt>
                    <dd className="text-sm font-medium text-foreground capitalize">{contact.source || '--'}</dd>
                  </div>
                  <div className="flex justify-between">
                    <dt className="text-sm text-muted-foreground">Created</dt>
                    <dd className="text-sm font-medium text-foreground">{formatDate(contact.created_at)}</dd>
                  </div>
                </dl>
              </CardContent>
            </Card>

            <Card>
              <CardContent className="p-6">
                <h3 className="text-sm font-semibold text-foreground mb-4">Deal Summary</h3>
                {contactDeals.length > 0 ? (
                  <div className="space-y-3">
                    <div className="flex justify-between">
                      <span className="text-sm text-muted-foreground">Total Deals</span>
                      <span className="text-sm font-medium text-foreground">{contactDeals.length}</span>
                    </div>
                    <div className="flex justify-between">
                      <span className="text-sm text-muted-foreground">Total Value</span>
                      <span className="text-sm font-bold text-foreground">
                        {formatCurrency(contactDeals.reduce((sum, d) => sum + d.value, 0))}
                      </span>
                    </div>
                    <div className="flex justify-between">
                      <span className="text-sm text-muted-foreground">Won</span>
                      <span className="text-sm font-medium text-emerald-600">
                        {formatCurrency(contactDeals.filter((d) => d.stage === 'closed_won').reduce((sum, d) => sum + d.value, 0))}
                      </span>
                    </div>
                  </div>
                ) : (
                  <p className="text-sm text-muted-foreground">No deals yet</p>
                )}
              </CardContent>
            </Card>
          </div>
        </TabsContent>

        <TabsContent value="deals">
          <div className="space-y-3">
            {contactDeals.map((deal) => (
              <Link key={deal.id} href={`/deals/${deal.id}`}>
                <Card className="p-4 hover:shadow-md transition-shadow cursor-pointer">
                  <div className="flex items-center justify-between">
                    <div>
                      <h4 className="font-medium text-foreground">{deal.title}</h4>
                      <p className="text-sm text-muted-foreground">Stage: {deal.stage.replace('_', ' ')}</p>
                    </div>
                    <div className="text-right">
                      <p className="font-bold text-foreground">{formatCurrency(deal.value)}</p>
                      <Badge variant={deal.stage === 'closed_won' ? 'success' : deal.stage === 'closed_lost' ? 'danger' : 'default'}>
                        {deal.probability}%
                      </Badge>
                    </div>
                  </div>
                </Card>
              </Link>
            ))}
            {contactDeals.length === 0 && (
              <div className="py-8 text-center text-muted-foreground">No deals for this contact</div>
            )}
          </div>
        </TabsContent>

        <TabsContent value="activities">
          <ActivityList activities={contactActivities} />
        </TabsContent>

        <TabsContent value="notes">
          <div className="space-y-4">
            <NoteForm onSubmit={addNote} />
            <NoteList notes={notes} />
          </div>
        </TabsContent>
      </Tabs>
    </div>
  );
}
