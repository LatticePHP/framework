'use client';

import Link from 'next/link';
import { Card, CardContent } from '@/components/ui/card';
import {
  Clock,
  Package,
  Eye,
  Bug,
  ExternalLink,
  Activity,
  BarChart3,
  Shield,
  Server,
} from 'lucide-react';

const dashboards = [
  {
    name: 'Chronos',
    description: 'Workflow execution dashboard — monitor running workflows, replay history, send signals, and view execution timelines.',
    icon: Clock,
    href: '/chronos',
    port: 3002,
    color: 'text-amber-500',
    bgColor: 'bg-amber-500/10',
    features: ['Workflow list & search', 'Execution timeline', 'Signal dispatch', 'Statistics & charts'],
  },
  {
    name: 'Loom',
    description: 'Queue monitoring dashboard — track job throughput, inspect failed jobs, manage workers, and monitor queue health.',
    icon: Package,
    href: '/loom',
    port: 3003,
    color: 'text-emerald-500',
    bgColor: 'bg-emerald-500/10',
    features: ['Job throughput metrics', 'Failed job inspector', 'Worker management', 'Queue health'],
  },
  {
    name: 'Nightwatch',
    description: 'Unified monitoring — dev mode for requests, queries, exceptions, and logs. Prod mode for overview, slow requests, and error tracking.',
    icon: Eye,
    href: '/nightwatch',
    port: 3004,
    color: 'text-blue-500',
    bgColor: 'bg-blue-500/10',
    features: ['Request inspector', 'Query profiler', 'Exception tracker', 'Production metrics'],
  },
  {
    name: 'Prism',
    description: 'Error reporting dashboard — self-hosted error tracking with stack traces, occurrence grouping, and resolution workflow.',
    icon: Bug,
    href: '/prism',
    port: 3005,
    color: 'text-rose-500',
    bgColor: 'bg-rose-500/10',
    features: ['Error grouping', 'Stack trace viewer', 'Issue management', 'Occurrence timeline'],
  },
];

const systemInfo = [
  { label: 'Framework', value: 'LatticePHP', icon: Server },
  { label: 'API Server', value: 'localhost:8000', icon: Activity },
  { label: 'CRM Frontend', value: 'localhost:3001', icon: BarChart3 },
  { label: 'Auth', value: 'JWT (RS256)', icon: Shield },
];

export default function AdminPortalPage() {
  return (
    <div className="space-y-8">
      {/* Page Header */}
      <div>
        <h1 className="text-2xl font-bold text-foreground">Admin Portal</h1>
        <p className="mt-1 text-sm text-muted-foreground">
          Manage and monitor your LatticePHP application from a single place.
        </p>
      </div>

      {/* System Info */}
      <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
        {systemInfo.map((info) => (
          <Card key={info.label}>
            <CardContent className="flex items-center gap-3 p-4">
              <div className="rounded-lg bg-muted p-2">
                <info.icon className="h-4 w-4 text-muted-foreground" />
              </div>
              <div className="min-w-0">
                <p className="text-xs text-muted-foreground">{info.label}</p>
                <p className="truncate text-sm font-medium text-foreground">{info.value}</p>
              </div>
            </CardContent>
          </Card>
        ))}
      </div>

      {/* Dashboard Cards */}
      <div>
        <h2 className="mb-4 text-lg font-semibold text-foreground">Dashboards</h2>
        <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
          {dashboards.map((dashboard) => (
            <Card key={dashboard.name} className="group overflow-hidden transition-shadow hover:shadow-lg">
              <CardContent className="p-6">
                <div className="flex items-start justify-between">
                  <div className="flex items-center gap-3">
                    <div className={`rounded-xl p-2.5 ${dashboard.bgColor}`}>
                      <dashboard.icon className={`h-6 w-6 ${dashboard.color}`} />
                    </div>
                    <div>
                      <h3 className="text-lg font-semibold text-foreground">{dashboard.name}</h3>
                      <p className="text-xs text-muted-foreground">Port {dashboard.port}</p>
                    </div>
                  </div>
                  <Link
                    href={`http://localhost:${dashboard.port}${dashboard.href}`}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="rounded-lg p-2 text-muted-foreground opacity-0 transition-all hover:bg-accent hover:text-accent-foreground group-hover:opacity-100"
                  >
                    <ExternalLink className="h-4 w-4" />
                  </Link>
                </div>

                <p className="mt-3 text-sm text-muted-foreground leading-relaxed">
                  {dashboard.description}
                </p>

                <div className="mt-4 flex flex-wrap gap-2">
                  {dashboard.features.map((feature) => (
                    <span
                      key={feature}
                      className="rounded-md bg-muted px-2 py-1 text-xs font-medium text-muted-foreground"
                    >
                      {feature}
                    </span>
                  ))}
                </div>

                <div className="mt-4 pt-4 border-t border-border">
                  <Link
                    href={`http://localhost:${dashboard.port}${dashboard.href}`}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:text-primary/80 transition-colors"
                  >
                    Open {dashboard.name}
                    <ExternalLink className="h-3.5 w-3.5" />
                  </Link>
                </div>
              </CardContent>
            </Card>
          ))}
        </div>
      </div>
    </div>
  );
}
