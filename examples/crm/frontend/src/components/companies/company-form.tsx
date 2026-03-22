'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useToast } from '@/components/ui/toast';
import type { Company } from '@/lib/types';

interface CompanyFormProps {
  company?: Company;
}

export function CompanyForm({ company }: CompanyFormProps) {
  const router = useRouter();
  const { toast } = useToast();
  const [formData, setFormData] = useState({
    name: company?.name || '',
    domain: company?.domain || '',
    industry: company?.industry || '',
    size: company?.size || '',
    phone: company?.phone || '',
    website: company?.website || '',
    annual_revenue: company?.annual_revenue?.toString() || '',
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    toast({
      type: 'success',
      title: company ? 'Company updated' : 'Company created',
      description: `${formData.name} has been ${company ? 'updated' : 'added'}.`,
    });
    router.push('/companies');
  };

  return (
    <Card>
      <CardHeader>
        <CardTitle>{company ? 'Edit Company' : 'New Company'}</CardTitle>
      </CardHeader>
      <CardContent>
        <form onSubmit={handleSubmit} className="space-y-6">
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div className="space-y-2">
              <Label htmlFor="name">Company Name</Label>
              <Input
                id="name"
                value={formData.name}
                onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                placeholder="Acme Corp"
                required
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="domain">Domain</Label>
              <Input
                id="domain"
                value={formData.domain}
                onChange={(e) => setFormData({ ...formData, domain: e.target.value })}
                placeholder="acmecorp.com"
              />
            </div>
          </div>

          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div className="space-y-2">
              <Label htmlFor="industry">Industry</Label>
              <Select
                id="industry"
                value={formData.industry}
                onChange={(e) => setFormData({ ...formData, industry: e.target.value })}
              >
                <option value="">Select industry...</option>
                <option value="Technology">Technology</option>
                <option value="SaaS">SaaS</option>
                <option value="Fintech">Fintech</option>
                <option value="Manufacturing">Manufacturing</option>
                <option value="Consulting">Consulting</option>
                <option value="Retail">Retail</option>
                <option value="AI/ML">AI/ML</option>
                <option value="Healthcare">Healthcare</option>
                <option value="Finance">Finance</option>
              </Select>
            </div>
            <div className="space-y-2">
              <Label htmlFor="size">Company Size</Label>
              <Select
                id="size"
                value={formData.size}
                onChange={(e) => setFormData({ ...formData, size: e.target.value })}
              >
                <option value="">Select size...</option>
                <option value="1-10">1-10</option>
                <option value="11-50">11-50</option>
                <option value="51-200">51-200</option>
                <option value="201-500">201-500</option>
                <option value="501-1000">501-1000</option>
                <option value="1000+">1000+</option>
              </Select>
            </div>
          </div>

          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div className="space-y-2">
              <Label htmlFor="phone">Phone</Label>
              <Input
                id="phone"
                value={formData.phone}
                onChange={(e) => setFormData({ ...formData, phone: e.target.value })}
                placeholder="+1 555-1000"
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="website">Website</Label>
              <Input
                id="website"
                value={formData.website}
                onChange={(e) => setFormData({ ...formData, website: e.target.value })}
                placeholder="https://acmecorp.com"
              />
            </div>
          </div>

          <div className="space-y-2">
            <Label htmlFor="annual_revenue">Annual Revenue</Label>
            <Input
              id="annual_revenue"
              type="number"
              value={formData.annual_revenue}
              onChange={(e) => setFormData({ ...formData, annual_revenue: e.target.value })}
              placeholder="1000000"
            />
          </div>

          <div className="flex justify-end gap-3 pt-4">
            <Button type="button" variant="outline" onClick={() => router.back()}>
              Cancel
            </Button>
            <Button type="submit">
              {company ? 'Save Changes' : 'Create Company'}
            </Button>
          </div>
        </form>
      </CardContent>
    </Card>
  );
}
