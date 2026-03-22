'use client';

import Link from 'next/link';
import { Plus, Handshake, Users } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { StatsCards } from '@/components/dashboard/stats-cards';
import { PipelineChart } from '@/components/dashboard/pipeline-chart';
import { RecentActivity } from '@/components/dashboard/recent-activity';
import { UpcomingTasks } from '@/components/dashboard/upcoming-tasks';
import { Skeleton } from '@/components/ui/skeleton';
import { Card, CardContent } from '@/components/ui/card';
import { useDashboard } from '@/hooks/use-dashboard';
import { DEMO_ACTIVITIES } from '@/hooks/use-activities';

export default function DashboardPage() {
  const { stats, pipeline, recentActivity, loading } = useDashboard();

  return (
    <div className="space-y-6">
      {/* Page Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">Dashboard</h1>
          <p className="text-sm text-slate-500">Welcome back, Sarah. Here is what is happening today.</p>
        </div>
        <div className="flex gap-2">
          <Link href="/contacts/new">
            <Button variant="outline" className="gap-2">
              <Users className="h-4 w-4" />
              Add Contact
            </Button>
          </Link>
          <Link href="/deals/new">
            <Button className="gap-2">
              <Plus className="h-4 w-4" />
              Create Deal
            </Button>
          </Link>
        </div>
      </div>

      {/* Stats */}
      <StatsCards stats={stats} loading={loading} />

      {/* Main grid */}
      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {/* Pipeline Chart - takes 2 cols */}
        <div className="lg:col-span-2">
          {loading ? (
            <Card className="p-6"><Skeleton className="h-64 w-full" /></Card>
          ) : (
            <PipelineChart pipeline={pipeline} />
          )}
        </div>

        {/* Quick Actions */}
        <div className="space-y-6">
          <Card>
            <CardContent className="p-6">
              <h3 className="text-sm font-semibold text-slate-900 mb-4">Quick Actions</h3>
              <div className="space-y-2">
                <Link href="/contacts/new" className="block">
                  <div className="flex items-center gap-3 rounded-lg border border-slate-200 p-3 hover:bg-slate-50 transition-colors">
                    <div className="rounded-lg bg-blue-50 p-2">
                      <Users className="h-4 w-4 text-blue-600" />
                    </div>
                    <div>
                      <p className="text-sm font-medium text-slate-700">Add Contact</p>
                      <p className="text-xs text-slate-400">Create a new contact record</p>
                    </div>
                  </div>
                </Link>
                <Link href="/deals/new" className="block">
                  <div className="flex items-center gap-3 rounded-lg border border-slate-200 p-3 hover:bg-slate-50 transition-colors">
                    <div className="rounded-lg bg-indigo-50 p-2">
                      <Handshake className="h-4 w-4 text-indigo-600" />
                    </div>
                    <div>
                      <p className="text-sm font-medium text-slate-700">Create Deal</p>
                      <p className="text-xs text-slate-400">Start tracking a new deal</p>
                    </div>
                  </div>
                </Link>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>

      {/* Bottom grid */}
      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {loading ? (
          <>
            <Card className="p-6"><Skeleton className="h-48 w-full" /></Card>
            <Card className="p-6"><Skeleton className="h-48 w-full" /></Card>
          </>
        ) : (
          <>
            <RecentActivity activities={recentActivity} />
            <UpcomingTasks activities={DEMO_ACTIVITIES} />
          </>
        )}
      </div>
    </div>
  );
}
