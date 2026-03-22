'use client';

import { Breadcrumbs } from '@/components/layout/breadcrumbs';
import { ContactForm } from '@/components/contacts/contact-form';

export default function NewContactPage() {
  return (
    <div className="space-y-6">
      <Breadcrumbs
        items={[
          { label: 'Contacts', href: '/contacts' },
          { label: 'New Contact' },
        ]}
      />
      <div className="max-w-2xl">
        <ContactForm />
      </div>
    </div>
  );
}
