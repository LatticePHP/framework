'use client';

import { Breadcrumbs } from '@/components/layout/breadcrumbs';
import { DealForm } from '@/components/deals/deal-form';

export default function NewDealPage() {
  return (
    <div className="space-y-6">
      <Breadcrumbs
        items={[
          { label: 'Deals', href: '/deals' },
          { label: 'New Deal' },
        ]}
      />
      <div className="max-w-2xl">
        <DealForm />
      </div>
    </div>
  );
}
