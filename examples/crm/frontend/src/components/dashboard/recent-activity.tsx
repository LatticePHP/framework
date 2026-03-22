'use client';

import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Handshake, Users, CalendarCheck } from 'lucide-react';
import { formatRelativeDate } from '@/lib/utils';

interface RecentActivityItem {
  id: number;
  text: string;
  time: string;
  type: 'deal' | 'contact' | 'activity';
}

interface RecentActivityProps {
  activities: RecentActivityItem[];
}

const typeIcons = {
  deal: Handshake,
  contact: Users,
  activity: CalendarCheck,
};

const typeColors = {
  deal: 'bg-indigo-50 text-indigo-600',
  contact: 'bg-blue-50 text-blue-600',
  activity: 'bg-emerald-50 text-emerald-600',
};

export function RecentActivity({ activities }: RecentActivityProps) {
  return (
    <Card>
      <CardHeader>
        <CardTitle>Recent Activity</CardTitle>
      </CardHeader>
      <CardContent>
        <div className="space-y-4">
          {activities.map((activity) => {
            const Icon = typeIcons[activity.type];
            return (
              <div key={activity.id} className="flex items-start gap-3">
                <div className={`rounded-lg p-2 ${typeColors[activity.type]}`}>
                  <Icon className="h-4 w-4" />
                </div>
                <div className="flex-1 min-w-0">
                  <p className="text-sm text-slate-700">{activity.text}</p>
                  <p className="text-xs text-slate-400 mt-0.5">{formatRelativeDate(activity.time)}</p>
                </div>
              </div>
            );
          })}
        </div>
      </CardContent>
    </Card>
  );
}
