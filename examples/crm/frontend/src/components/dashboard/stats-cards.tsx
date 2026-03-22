'use client';

import { Users, Handshake, DollarSign, TrendingUp } from 'lucide-react';
import { Card } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { formatCurrency } from '@/lib/utils';
import type { DashboardStats } from '@/lib/types';

interface StatsCardsProps {
  stats: DashboardStats | null;
  loading: boolean;
}

export function StatsCards({ stats, loading }: StatsCardsProps) {
  const cards = [
    {
      title: 'Total Contacts',
      value: stats?.total_contacts.toLocaleString() ?? '0',
      icon: Users,
      iconBg: 'bg-blue-50',
      iconColor: 'text-blue-600',
      change: '+12.5%',
      changeType: 'positive' as const,
    },
    {
      title: 'Active Deals',
      value: stats?.active_deals.toString() ?? '0',
      icon: Handshake,
      iconBg: 'bg-indigo-50',
      iconColor: 'text-indigo-600',
      change: '+3',
      changeType: 'positive' as const,
    },
    {
      title: 'Pipeline Value',
      value: stats ? formatCurrency(stats.pipeline_value) : '$0',
      icon: DollarSign,
      iconBg: 'bg-emerald-50',
      iconColor: 'text-emerald-600',
      change: '+18.2%',
      changeType: 'positive' as const,
    },
    {
      title: 'Conversion Rate',
      value: stats ? `${stats.conversion_rate}%` : '0%',
      icon: TrendingUp,
      iconBg: 'bg-amber-50',
      iconColor: 'text-amber-600',
      change: '+2.1%',
      changeType: 'positive' as const,
    },
  ];

  if (loading) {
    return (
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {[1, 2, 3, 4].map((i) => (
          <Card key={i} className="p-6">
            <Skeleton className="h-4 w-24 mb-3" />
            <Skeleton className="h-8 w-32 mb-2" />
            <Skeleton className="h-3 w-16" />
          </Card>
        ))}
      </div>
    );
  }

  return (
    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
      {cards.map((card) => (
        <Card key={card.title} className="p-6">
          <div className="flex items-center justify-between">
            <p className="text-sm font-medium text-slate-500">{card.title}</p>
            <div className={`rounded-lg p-2 ${card.iconBg}`}>
              <card.icon className={`h-5 w-5 ${card.iconColor}`} />
            </div>
          </div>
          <p className="mt-2 text-3xl font-bold text-slate-900">{card.value}</p>
          <p className="mt-1 text-sm text-emerald-600">
            {card.change} <span className="text-slate-400">vs last month</span>
          </p>
        </Card>
      ))}
    </div>
  );
}
