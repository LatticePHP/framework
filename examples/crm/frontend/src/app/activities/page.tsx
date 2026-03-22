'use client';

import { useState } from 'react';
import { Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs';
import { Skeleton } from '@/components/ui/skeleton';
import { Breadcrumbs } from '@/components/layout/breadcrumbs';
import { ActivityList } from '@/components/activities/activity-list';
import { ActivityForm } from '@/components/activities/activity-form';
import { useActivities } from '@/hooks/use-activities';
import { useToast } from '@/components/ui/toast';

export default function ActivitiesPage() {
  const [activeTab, setActiveTab] = useState('all');
  const [showForm, setShowForm] = useState(false);
  const { toast } = useToast();

  const filter = activeTab === 'all' ? undefined : (activeTab as 'upcoming' | 'overdue');
  const { data, loading } = useActivities({ filter });

  const handleComplete = (id: number) => {
    toast({ type: 'success', title: 'Activity completed' });
  };

  return (
    <div className="space-y-6">
      <Breadcrumbs items={[{ label: 'Activities' }]} />

      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-foreground">Activities</h1>
          <p className="text-sm text-muted-foreground">{data?.total ?? 0} total activities</p>
        </div>
        <Button className="gap-2" onClick={() => setShowForm(true)}>
          <Plus className="h-4 w-4" />
          New Activity
        </Button>
      </div>

      <Tabs value={activeTab} onValueChange={setActiveTab}>
        <TabsList>
          <TabsTrigger value="all">All</TabsTrigger>
          <TabsTrigger value="upcoming">Upcoming</TabsTrigger>
          <TabsTrigger value="overdue">Overdue</TabsTrigger>
        </TabsList>

        <TabsContent value={activeTab}>
          {loading ? (
            <div className="space-y-3">
              {[1, 2, 3, 4, 5].map((i) => (
                <Skeleton key={i} className="h-16 w-full" />
              ))}
            </div>
          ) : (
            <ActivityList activities={data?.data ?? []} onComplete={handleComplete} />
          )}
        </TabsContent>
      </Tabs>

      <ActivityForm open={showForm} onClose={() => setShowForm(false)} />
    </div>
  );
}
