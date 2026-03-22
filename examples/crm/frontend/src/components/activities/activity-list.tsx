'use client';

import { Phone, Calendar, CheckSquare, Mail, Check, Clock, AlertTriangle } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { formatDate } from '@/lib/utils';
import type { Activity } from '@/lib/types';

interface ActivityListProps {
  activities: Activity[];
  onComplete?: (id: number) => void;
}

const typeIcons = {
  call: Phone,
  meeting: Calendar,
  task: CheckSquare,
  email: Mail,
};

const typeColors = {
  call: 'bg-blue-50 text-blue-600',
  meeting: 'bg-purple-50 text-purple-600',
  task: 'bg-emerald-50 text-emerald-600',
  email: 'bg-amber-50 text-amber-600',
};

const priorityVariants = {
  high: 'danger' as const,
  medium: 'warning' as const,
  low: 'secondary' as const,
};

export function ActivityList({ activities, onComplete }: ActivityListProps) {
  const now = new Date();

  return (
    <div className="space-y-2">
      {activities.map((activity) => {
        const Icon = typeIcons[activity.type];
        const isOverdue = !activity.completed_at && new Date(activity.due_date) < now;
        const isCompleted = !!activity.completed_at;

        return (
          <Card
            key={activity.id}
            className={`flex items-center gap-4 p-4 ${isCompleted ? 'opacity-60' : ''}`}
          >
            <div className={`rounded-lg p-2.5 ${typeColors[activity.type]}`}>
              <Icon className="h-5 w-5" />
            </div>

            <div className="flex-1 min-w-0">
              <div className="flex items-center gap-2">
                <h4 className={`text-sm font-medium ${isCompleted ? 'line-through text-muted-foreground' : 'text-foreground'}`}>
                  {activity.title}
                </h4>
                {isOverdue && (
                  <span className="inline-flex items-center gap-1 text-xs text-rose-600">
                    <AlertTriangle className="h-3 w-3" />
                    Overdue
                  </span>
                )}
              </div>
              {activity.description && (
                <p className="mt-0.5 text-sm text-muted-foreground truncate">{activity.description}</p>
              )}
              <div className="mt-1.5 flex items-center gap-3 text-xs text-muted-foreground">
                <span className="flex items-center gap-1">
                  <Clock className="h-3 w-3" />
                  {formatDate(activity.due_date)}
                </span>
                <span className="capitalize">{activity.type}</span>
              </div>
            </div>

            <div className="flex items-center gap-2">
              <Badge variant={priorityVariants[activity.priority]}>{activity.priority}</Badge>
              {!isCompleted && onComplete && (
                <Button
                  size="icon-sm"
                  variant="ghost"
                  onClick={() => onComplete(activity.id)}
                  title="Mark complete"
                >
                  <Check className="h-4 w-4" />
                </Button>
              )}
              {isCompleted && (
                <div className="rounded-full bg-emerald-50 p-1">
                  <Check className="h-4 w-4 text-emerald-600" />
                </div>
              )}
            </div>
          </Card>
        );
      })}
      {activities.length === 0 && (
        <div className="py-12 text-center text-muted-foreground">
          <CalendarCheck className="mx-auto h-12 w-12 mb-3 text-muted-foreground/50" />
          <p>No activities found</p>
        </div>
      )}
    </div>
  );
}

function CalendarCheck(props: React.SVGProps<SVGSVGElement>) {
  return (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}>
      <path d="M8 2v4" /><path d="M16 2v4" /><rect width="18" height="18" x="3" y="4" rx="2" /><path d="M3 10h18" /><path d="m9 16 2 2 4-4" />
    </svg>
  );
}
