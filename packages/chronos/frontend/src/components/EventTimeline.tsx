import { Card, CardBody, Chip, Accordion, AccordionItem } from '@nextui-org/react';
import type { WorkflowEvent, WorkflowEventType } from '@/schemas/workflow';

const EVENT_TYPE_CONFIG: Record<
  string,
  { color: 'success' | 'danger' | 'primary' | 'warning' | 'secondary' | 'default'; icon: string }
> = {
  workflow_started: { color: 'primary', icon: '\u25B6' },
  workflow_completed: { color: 'success', icon: '\u2714' },
  workflow_failed: { color: 'danger', icon: '\u2718' },
  workflow_cancelled: { color: 'warning', icon: '\u26A0' },
  workflow_terminated: { color: 'default', icon: '\u23F9' },
  activity_scheduled: { color: 'secondary', icon: '\u23F0' },
  activity_started: { color: 'primary', icon: '\u2699' },
  activity_completed: { color: 'success', icon: '\u2714' },
  activity_failed: { color: 'danger', icon: '\u2718' },
  activity_timed_out: { color: 'warning', icon: '\u231B' },
  timer_started: { color: 'secondary', icon: '\u23F1' },
  timer_fired: { color: 'primary', icon: '\u{1F514}' },
  timer_cancelled: { color: 'default', icon: '\u23F9' },
  signal_received: { color: 'warning', icon: '\u26A1' },
  query_received: { color: 'secondary', icon: '\u2753' },
  update_received: { color: 'primary', icon: '\u{1F504}' },
  child_workflow_started: { color: 'primary', icon: '\u{1F517}' },
  child_workflow_completed: { color: 'success', icon: '\u{1F517}' },
  child_workflow_failed: { color: 'danger', icon: '\u{1F517}' },
};

function getEventConfig(type: WorkflowEventType) {
  return EVENT_TYPE_CONFIG[type] ?? { color: 'default' as const, icon: '\u2022' };
}

function formatTimestamp(ts: string): string {
  const date = new Date(ts);
  return date.toLocaleString(undefined, {
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  });
}

function formatDuration(ms: number | null | undefined): string {
  if (ms == null) return '';
  if (ms < 1000) return `${ms}ms`;
  if (ms < 60000) return `${(ms / 1000).toFixed(1)}s`;
  return `${(ms / 60000).toFixed(1)}m`;
}

function formatEventType(type: string): string {
  return type
    .split('_')
    .map((w) => w.charAt(0).toUpperCase() + w.slice(1))
    .join(' ');
}

interface EventTimelineProps {
  events: WorkflowEvent[];
}

export function EventTimeline({ events }: EventTimelineProps) {
  if (events.length === 0) {
    return (
      <div className="flex items-center justify-center py-12 text-default-400">
        No events recorded yet.
      </div>
    );
  }

  return (
    <div className="relative">
      {/* Vertical line */}
      <div className="absolute left-4 top-0 bottom-0 w-0.5 bg-default-200" />

      <div className="space-y-4">
        {events.map((event, index) => {
          const config = getEventConfig(event.type);
          const hasData = event.data != null;

          return (
            <div key={`${event.sequence}-${index}`} className="relative pl-10">
              {/* Timeline dot */}
              <div
                className={`absolute left-2.5 top-3 w-3 h-3 rounded-full ring-2 ring-background ${
                  config.color === 'success'
                    ? 'bg-success'
                    : config.color === 'danger'
                      ? 'bg-danger'
                      : config.color === 'primary'
                        ? 'bg-primary'
                        : config.color === 'warning'
                          ? 'bg-warning'
                          : config.color === 'secondary'
                            ? 'bg-secondary'
                            : 'bg-default'
                }`}
              />

              <Card shadow="sm" className="w-full">
                <CardBody className="p-3">
                  <div className="flex items-start justify-between gap-2">
                    <div className="flex items-center gap-2 flex-wrap">
                      <span className="text-lg" role="img" aria-label={event.type}>
                        {config.icon}
                      </span>
                      <Chip color={config.color} variant="flat" size="sm">
                        {formatEventType(event.type)}
                      </Chip>
                      {event.duration_ms != null && (
                        <span className="text-xs text-default-400">
                          +{formatDuration(event.duration_ms)}
                        </span>
                      )}
                    </div>
                    <span className="text-xs text-default-400 whitespace-nowrap">
                      {formatTimestamp(event.timestamp)}
                    </span>
                  </div>

                  {hasData && (
                    <Accordion isCompact className="mt-2 -mx-1">
                      <AccordionItem
                        key="data"
                        aria-label="Event Data"
                        title={
                          <span className="text-xs text-default-500">Event Data</span>
                        }
                      >
                        <pre className="text-xs bg-default-100 rounded-lg p-3 overflow-auto max-h-64">
                          {JSON.stringify(event.data, null, 2)}
                        </pre>
                      </AccordionItem>
                    </Accordion>
                  )}
                </CardBody>
              </Card>
            </div>
          );
        })}
      </div>
    </div>
  );
}
