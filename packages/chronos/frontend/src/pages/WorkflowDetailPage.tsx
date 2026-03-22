import { useState, useMemo } from 'react';
import {
  Button,
  Spinner,
  Accordion,
  AccordionItem,
  Tabs,
  Tab,
  Chip,
  Pagination,
  Select,
  SelectItem,
} from '@nextui-org/react';
import { useWorkflow, useWorkflowEvents } from '@/api/workflows';
import { useRetryWorkflow, useCancelWorkflow } from '@/api/mutations';
import { StatusBadge } from '@/components/StatusBadge';
import { EventTimeline } from '@/components/EventTimeline';
import { SignalModal } from '@/components/SignalModal';
import type { WorkflowStatus } from '@/schemas/workflow';

interface WorkflowDetailPageProps {
  workflowId: string;
}

const EVENT_TYPE_OPTIONS: { key: string; label: string }[] = [
  { key: 'workflow_started', label: 'Workflow Started' },
  { key: 'workflow_completed', label: 'Workflow Completed' },
  { key: 'workflow_failed', label: 'Workflow Failed' },
  { key: 'activity_scheduled', label: 'Activity Scheduled' },
  { key: 'activity_started', label: 'Activity Started' },
  { key: 'activity_completed', label: 'Activity Completed' },
  { key: 'activity_failed', label: 'Activity Failed' },
  { key: 'signal_received', label: 'Signal Received' },
  { key: 'timer_started', label: 'Timer Started' },
  { key: 'timer_fired', label: 'Timer Fired' },
  { key: 'child_workflow_started', label: 'Child Workflow Started' },
  { key: 'child_workflow_completed', label: 'Child Workflow Completed' },
  { key: 'child_workflow_failed', label: 'Child Workflow Failed' },
];

function formatTimestamp(ts: string | null | undefined): string {
  if (!ts) return '--';
  return new Date(ts).toLocaleString(undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  });
}

function formatDuration(ms: number | null): string {
  if (ms == null) return '--';
  if (ms < 1000) return `${ms}ms`;
  if (ms < 60000) return `${(ms / 1000).toFixed(1)}s`;
  if (ms < 3600000) return `${(ms / 60000).toFixed(1)}m`;
  return `${(ms / 3600000).toFixed(1)}h`;
}

function BackIcon() {
  return (
    <svg
      width="16"
      height="16"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
    >
      <line x1="19" y1="12" x2="5" y2="12" />
      <polyline points="12 19 5 12 12 5" />
    </svg>
  );
}

function CopyIcon() {
  return (
    <svg
      width="14"
      height="14"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
    >
      <rect x="9" y="9" width="13" height="13" rx="2" ry="2" />
      <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" />
    </svg>
  );
}

export function WorkflowDetailPage({ workflowId }: WorkflowDetailPageProps) {
  const [signalOpen, setSignalOpen] = useState(false);
  const [eventTypesFilter, setEventTypesFilter] = useState<Set<string>>(new Set());
  const [eventsPage, setEventsPage] = useState(1);
  const [copied, setCopied] = useState(false);

  const { data: workflowResponse, isLoading, isError, error } = useWorkflow(workflowId);
  const retryMutation = useRetryWorkflow();
  const cancelMutation = useCancelWorkflow();

  const eventTypeParam = eventTypesFilter.size > 0
    ? Array.from(eventTypesFilter).join(',')
    : undefined;

  const {
    data: eventsResponse,
    isLoading: eventsLoading,
  } = useWorkflowEvents(workflowId, {
    page: eventsPage,
    per_page: 50,
    event_type: eventTypeParam,
  });

  const workflow = workflowResponse?.data;

  const canRetry = workflow?.status === 'failed';
  const canCancel = workflow?.status === 'running' || workflow?.status === 'completed';
  const canSignal = workflow?.status === 'running' || workflow?.status === 'completed';

  const handleCopyId = async () => {
    if (!workflow) return;
    await navigator.clipboard.writeText(workflow.id);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };

  const events = useMemo(() => {
    // Use paginated events endpoint if available, fall back to inline events
    if (eventsResponse?.data) return eventsResponse.data;
    if (workflow?.events) return workflow.events;
    return [];
  }, [eventsResponse, workflow]);

  const eventsTotalPages = eventsResponse
    ? Math.max(1, Math.ceil(eventsResponse.meta.total / eventsResponse.meta.per_page))
    : 1;

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-64">
        <Spinner size="lg" label="Loading workflow..." />
      </div>
    );
  }

  if (isError || !workflow) {
    return (
      <div className="space-y-4">
        <Button
          variant="light"
          size="sm"
          startContent={<BackIcon />}
          onPress={() => (window.location.hash = '/workflows')}
        >
          Back to Workflows
        </Button>
        <div className="bg-danger-50 dark:bg-danger-100/10 border border-danger-200 dark:border-danger-500/30 rounded-xl p-6">
          <h3 className="text-danger font-bold text-lg">Workflow Not Found</h3>
          <p className="text-default-500 mt-2">
            {error instanceof Error ? error.message : `Could not load workflow: ${workflowId}`}
          </p>
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Back navigation */}
      <Button
        variant="light"
        size="sm"
        startContent={<BackIcon />}
        onPress={() => (window.location.hash = '/workflows')}
      >
        Back to Workflows
      </Button>

      {/* Header */}
      <div className="bg-content1 rounded-xl border border-divider p-6">
        <div className="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
          <div className="space-y-2">
            <div className="flex items-center gap-3">
              <StatusBadge status={workflow.status as WorkflowStatus} size="md" />
              <h2 className="text-xl font-bold">{workflow.type}</h2>
            </div>

            <div className="flex items-center gap-2 text-sm">
              <span className="font-mono text-default-500">{workflow.id}</span>
              <Button
                isIconOnly
                variant="light"
                size="sm"
                onPress={() => void handleCopyId()}
                aria-label="Copy ID"
              >
                {copied ? (
                  <span className="text-success text-xs">Copied</span>
                ) : (
                  <CopyIcon />
                )}
              </Button>
            </div>

            {workflow.parent_workflow_id && (
              <p className="text-xs text-default-400">
                Parent:{' '}
                <a
                  href={`#/workflows/${workflow.parent_workflow_id}`}
                  className="text-primary hover:underline font-mono"
                >
                  {workflow.parent_workflow_id}
                </a>
              </p>
            )}
          </div>

          {/* Action buttons */}
          <div className="flex gap-2 flex-wrap">
            {canSignal && (
              <Button
                color="primary"
                variant="flat"
                size="sm"
                onPress={() => setSignalOpen(true)}
              >
                Send Signal
              </Button>
            )}
            {canRetry && (
              <Button
                color="warning"
                variant="flat"
                size="sm"
                isLoading={retryMutation.isPending}
                onPress={() => retryMutation.mutate({ id: workflow.id })}
              >
                Retry
              </Button>
            )}
            {canCancel && (
              <Button
                color="danger"
                variant="flat"
                size="sm"
                isLoading={cancelMutation.isPending}
                onPress={() => cancelMutation.mutate({ id: workflow.id })}
              >
                Cancel
              </Button>
            )}
          </div>
        </div>

        {/* Metadata grid */}
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mt-6 pt-4 border-t border-divider">
          <div>
            <p className="text-xs text-default-400 uppercase tracking-wider">Started</p>
            <p className="text-sm mt-1">{formatTimestamp(workflow.started_at)}</p>
          </div>
          <div>
            <p className="text-xs text-default-400 uppercase tracking-wider">Completed</p>
            <p className="text-sm mt-1">{formatTimestamp(workflow.completed_at)}</p>
          </div>
          <div>
            <p className="text-xs text-default-400 uppercase tracking-wider">Duration</p>
            <p className="text-sm font-mono mt-1">
              {formatDuration(workflow.duration_ms)}
            </p>
          </div>
          <div>
            <p className="text-xs text-default-400 uppercase tracking-wider">Total Events</p>
            <p className="text-sm mt-1">{workflow.total_events}</p>
          </div>
        </div>

        {/* Mutation results */}
        {retryMutation.isSuccess && (
          <div className="mt-4 p-3 rounded-lg bg-success-50 dark:bg-success-100/10 border border-success-200 dark:border-success-500/30">
            <p className="text-sm text-success">Retry initiated successfully</p>
          </div>
        )}
        {retryMutation.isError && (
          <div className="mt-4 p-3 rounded-lg bg-danger-50 dark:bg-danger-100/10 border border-danger-200 dark:border-danger-500/30">
            <p className="text-sm text-danger">
              Retry failed:{' '}
              {retryMutation.error instanceof Error
                ? retryMutation.error.message
                : 'Unknown error'}
            </p>
          </div>
        )}
        {cancelMutation.isSuccess && (
          <div className="mt-4 p-3 rounded-lg bg-warning-50 dark:bg-warning-100/10 border border-warning-200 dark:border-warning-500/30">
            <p className="text-sm text-warning">Cancellation dispatched</p>
          </div>
        )}
      </div>

      {/* Tabs */}
      <Tabs aria-label="Workflow sections" color="primary" variant="underlined">
        <Tab key="timeline" title="Timeline">
          <div className="space-y-4 mt-4">
            {/* Event type filter */}
            <div className="flex items-center gap-3 flex-wrap">
              <Select
                label="Filter events"
                placeholder="All event types"
                selectionMode="multiple"
                size="sm"
                className="max-w-xs"
                selectedKeys={eventTypesFilter}
                onSelectionChange={(keys) => {
                  setEventTypesFilter(new Set(Array.from(keys) as string[]));
                  setEventsPage(1);
                }}
              >
                {EVENT_TYPE_OPTIONS.map((opt) => (
                  <SelectItem key={opt.key}>{opt.label}</SelectItem>
                ))}
              </Select>
              {eventTypesFilter.size > 0 && (
                <div className="flex gap-1 flex-wrap">
                  {Array.from(eventTypesFilter).map((t) => (
                    <Chip
                      key={t}
                      size="sm"
                      variant="flat"
                      onClose={() => {
                        const next = new Set(eventTypesFilter);
                        next.delete(t);
                        setEventTypesFilter(next);
                      }}
                    >
                      {t.split('_').map((w) => w.charAt(0).toUpperCase() + w.slice(1)).join(' ')}
                    </Chip>
                  ))}
                </div>
              )}
            </div>

            {eventsLoading ? (
              <div className="flex justify-center py-8">
                <Spinner label="Loading events..." />
              </div>
            ) : (
              <>
                <EventTimeline events={events} />
                {eventsTotalPages > 1 && (
                  <div className="flex justify-center mt-4">
                    <Pagination
                      isCompact
                      showControls
                      color="primary"
                      page={eventsPage}
                      total={eventsTotalPages}
                      onChange={setEventsPage}
                    />
                  </div>
                )}
              </>
            )}
          </div>
        </Tab>

        <Tab key="input-output" title="Input / Output">
          <div className="mt-4 space-y-4">
            <Accordion variant="bordered">
              <AccordionItem key="input" aria-label="Workflow Input" title="Input">
                <pre className="text-sm font-mono bg-default-100 rounded-lg p-4 overflow-auto max-h-96">
                  {workflow.input != null
                    ? JSON.stringify(workflow.input, null, 2)
                    : 'null'}
                </pre>
              </AccordionItem>
              <AccordionItem key="output" aria-label="Workflow Output" title="Output">
                <pre className="text-sm font-mono bg-default-100 rounded-lg p-4 overflow-auto max-h-96">
                  {workflow.output != null
                    ? JSON.stringify(workflow.output, null, 2)
                    : 'null'}
                </pre>
              </AccordionItem>
            </Accordion>
          </div>
        </Tab>

        <Tab key="details" title="Details">
          <div className="mt-4">
            <div className="bg-content1 rounded-xl border border-divider p-6">
              <h3 className="text-lg font-semibold mb-4">Execution Details</h3>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <p className="text-xs text-default-400 uppercase tracking-wider">
                    Execution ID
                  </p>
                  <p className="text-sm font-mono mt-1">{workflow.id}</p>
                </div>
                <div>
                  <p className="text-xs text-default-400 uppercase tracking-wider">
                    Workflow ID
                  </p>
                  <p className="text-sm font-mono mt-1">{workflow.workflow_id}</p>
                </div>
                {workflow.run_id && (
                  <div>
                    <p className="text-xs text-default-400 uppercase tracking-wider">
                      Run ID
                    </p>
                    <p className="text-sm font-mono mt-1">{workflow.run_id}</p>
                  </div>
                )}
                <div>
                  <p className="text-xs text-default-400 uppercase tracking-wider">
                    Type
                  </p>
                  <p className="text-sm mt-1">{workflow.type}</p>
                </div>
                <div>
                  <p className="text-xs text-default-400 uppercase tracking-wider">
                    Status
                  </p>
                  <div className="mt-1">
                    <StatusBadge status={workflow.status as WorkflowStatus} size="md" />
                  </div>
                </div>
                <div>
                  <p className="text-xs text-default-400 uppercase tracking-wider">
                    Has More Events
                  </p>
                  <p className="text-sm mt-1">{workflow.has_more_events ? 'Yes' : 'No'}</p>
                </div>
              </div>
            </div>
          </div>
        </Tab>
      </Tabs>

      {/* Signal Modal */}
      <SignalModal
        isOpen={signalOpen}
        onClose={() => setSignalOpen(false)}
        workflowId={workflow.id}
      />
    </div>
  );
}
