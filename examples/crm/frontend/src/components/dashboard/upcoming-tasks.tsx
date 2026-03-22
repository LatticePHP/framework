'use client';

import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Phone, Calendar, CheckSquare, Mail } from 'lucide-react';
import { formatDate } from '@/lib/utils';
import type { Activity } from '@/lib/types';

interface UpcomingTasksProps {
  activities: Activity[];
}

const typeIcons = {
  call: Phone,
  meeting: Calendar,
  task: CheckSquare,
  email: Mail,
};

const priorityVariants = {
  high: 'danger' as const,
  medium: 'warning' as const,
  low: 'secondary' as const,
};

export function UpcomingTasks({ activities }: UpcomingTasksProps) {
  const upcoming = activities
    .filter((a) => !a.completed_at)
    .sort((a, b) => new Date(a.due_date).getTime() - new Date(b.due_date).getTime())
    .slice(0, 5);

  return (
    <Card>
      <CardHeader>
        <CardTitle>Upcoming Tasks</CardTitle>
      </CardHeader>
      <CardContent>
        <div className="space-y-3">
          {upcoming.map((activity) => {
            const Icon = typeIcons[activity.type];
            return (
              <div
                key={activity.id}
                className="flex items-center gap-3 rounded-lg border border-slate-100 p-3"
              >
                <div className="rounded-md bg-slate-50 p-2">
                  <Icon className="h-4 w-4 text-slate-500" />
                </div>
                <div className="flex-1 min-w-0">
                  <p className="text-sm font-medium text-slate-700 truncate">{activity.title}</p>
                  <p className="text-xs text-slate-400">{formatDate(activity.due_date)}</p>
                </div>
                <Badge variant={priorityVariants[activity.priority]}>{activity.priority}</Badge>
              </div>
            );
          })}
          {upcoming.length === 0 && (
            <p className="text-sm text-slate-400 text-center py-4">No upcoming tasks</p>
          )}
        </div>
      </CardContent>
    </Card>
  );
}
