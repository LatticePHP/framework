'use client';

import { Breadcrumbs } from '@/components/layout/breadcrumbs';
import { CompanyForm } from '@/components/companies/company-form';

export default function NewCompanyPage() {
  return (
    <div className="space-y-6">
      <Breadcrumbs
        items={[
          { label: 'Companies', href: '/companies' },
          { label: 'New Company' },
        ]}
      />
      <div className="max-w-2xl">
        <CompanyForm />
      </div>
    </div>
  );
}
