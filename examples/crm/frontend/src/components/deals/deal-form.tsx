'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useToast } from '@/components/ui/toast';
import type { Deal } from '@/lib/types';

interface DealFormProps {
  deal?: Deal;
}

export function DealForm({ deal }: DealFormProps) {
  const router = useRouter();
  const { toast } = useToast();
  const [formData, setFormData] = useState({
    title: deal?.title || '',
    value: deal?.value?.toString() || '',
    currency: deal?.currency || 'USD',
    stage: deal?.stage || 'lead',
    probability: deal?.probability?.toString() || '10',
    expected_close_date: deal?.expected_close_date?.split('T')[0] || '',
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    toast({
      type: 'success',
      title: deal ? 'Deal updated' : 'Deal created',
      description: `${formData.title} has been ${deal ? 'updated' : 'added'}.`,
    });
    router.push('/deals');
  };

  return (
    <Card>
      <CardHeader>
        <CardTitle>{deal ? 'Edit Deal' : 'New Deal'}</CardTitle>
      </CardHeader>
      <CardContent>
        <form onSubmit={handleSubmit} className="space-y-6">
          <div className="space-y-2">
            <Label htmlFor="title">Deal Title</Label>
            <Input
              id="title"
              value={formData.title}
              onChange={(e) => setFormData({ ...formData, title: e.target.value })}
              placeholder="Enterprise License Agreement"
              required
            />
          </div>

          <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div className="space-y-2">
              <Label htmlFor="value">Value</Label>
              <Input
                id="value"
                type="number"
                value={formData.value}
                onChange={(e) => setFormData({ ...formData, value: e.target.value })}
                placeholder="50000"
                required
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="currency">Currency</Label>
              <Select
                id="currency"
                value={formData.currency}
                onChange={(e) => setFormData({ ...formData, currency: e.target.value })}
              >
                <option value="USD">USD</option>
                <option value="EUR">EUR</option>
                <option value="GBP">GBP</option>
              </Select>
            </div>
            <div className="space-y-2">
              <Label htmlFor="probability">Probability (%)</Label>
              <Input
                id="probability"
                type="number"
                min="0"
                max="100"
                value={formData.probability}
                onChange={(e) => setFormData({ ...formData, probability: e.target.value })}
              />
            </div>
          </div>

          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div className="space-y-2">
              <Label htmlFor="stage">Stage</Label>
              <Select
                id="stage"
                value={formData.stage}
                onChange={(e) => setFormData({ ...formData, stage: e.target.value as Deal['stage'] })}
              >
                <option value="lead">Lead</option>
                <option value="qualified">Qualified</option>
                <option value="proposal">Proposal</option>
                <option value="negotiation">Negotiation</option>
                <option value="closed_won">Closed Won</option>
                <option value="closed_lost">Closed Lost</option>
              </Select>
            </div>
            <div className="space-y-2">
              <Label htmlFor="expected_close_date">Expected Close Date</Label>
              <Input
                id="expected_close_date"
                type="date"
                value={formData.expected_close_date}
                onChange={(e) => setFormData({ ...formData, expected_close_date: e.target.value })}
              />
            </div>
          </div>

          <div className="flex justify-end gap-3 pt-4">
            <Button type="button" variant="outline" onClick={() => router.back()}>
              Cancel
            </Button>
            <Button type="submit">
              {deal ? 'Save Changes' : 'Create Deal'}
            </Button>
          </div>
        </form>
      </CardContent>
    </Card>
  );
}
