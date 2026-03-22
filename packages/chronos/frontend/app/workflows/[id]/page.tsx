"use client";

import { useState, useMemo } from "react";
import { useParams, useRouter } from "next/navigation";
import { useWorkflow, useWorkflowEvents, useRetryWorkflow, useCancelWorkflow } from "@/lib/api";
import { StatusBadge } from "@/components/feedback/status-badge";
import { EventTimeline } from "@/components/event-timeline";
import { SignalDialog } from "@/components/signal-dialog";
import { JsonViewer } from "@/components/data/json-viewer";
import { ErrorState } from "@/components/feedback/error-state";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Badge } from "@/components/ui/badge";
import { Separator } from "@/components/ui/separator";
import { Skeleton } from "@/components/ui/skeleton";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { formatTimestamp, formatDuration } from "@/lib/formatters";
import {
  ArrowLeft,
  Copy,
  Check,
  Send,
  RotateCcw,
  XCircle,
  Loader2,
  X,
} from "lucide-react";
import type { WorkflowStatus } from "@/lib/schemas";

const EVENT_TYPE_OPTIONS = [
  { key: "workflow_started", label: "Workflow Started" },
  { key: "workflow_completed", label: "Workflow Completed" },
  { key: "workflow_failed", label: "Workflow Failed" },
  { key: "activity_scheduled", label: "Activity Scheduled" },
  { key: "activity_started", label: "Activity Started" },
  { key: "activity_completed", label: "Activity Completed" },
  { key: "activity_failed", label: "Activity Failed" },
  { key: "signal_received", label: "Signal Received" },
  { key: "timer_started", label: "Timer Started" },
  { key: "timer_fired", label: "Timer Fired" },
  { key: "child_workflow_started", label: "Child WF Started" },
  { key: "child_workflow_completed", label: "Child WF Completed" },
  { key: "child_workflow_failed", label: "Child WF Failed" },
];

export default function WorkflowDetailPage() {
  const params = useParams<{ id: string }>();
  const router = useRouter();
  const workflowId = params.id;

  const [signalOpen, setSignalOpen] = useState(false);
  const [eventTypeFilter, setEventTypeFilter] = useState("");
  const [eventsPage, setEventsPage] = useState(1);
  const [copied, setCopied] = useState(false);

  const {
    data: workflowResponse,
    isLoading,
    isError,
    error,
    refetch,
  } = useWorkflow(workflowId);
  const retryMutation = useRetryWorkflow();
  const cancelMutation = useCancelWorkflow();

  const {
    data: eventsResponse,
    isLoading: eventsLoading,
  } = useWorkflowEvents(workflowId, {
    page: eventsPage,
    per_page: 50,
    event_type: eventTypeFilter || undefined,
  });

  const workflow = workflowResponse?.data;

  const canRetry = workflow?.status === "failed";
  const canCancel = workflow?.status === "running" || workflow?.status === "completed";
  const canSignal = workflow?.status === "running" || workflow?.status === "completed";

  const handleCopyId = async () => {
    if (!workflow) return;
    await navigator.clipboard.writeText(workflow.id);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };

  const events = useMemo(() => {
    if (eventsResponse?.data) return eventsResponse.data;
    if (workflow?.events) return workflow.events;
    return [];
  }, [eventsResponse, workflow]);

  const eventsTotalPages = eventsResponse
    ? Math.max(1, Math.ceil(eventsResponse.meta.total / eventsResponse.meta.per_page))
    : 1;

  // Loading state
  if (isLoading) {
    return (
      <div className="flex flex-col gap-6">
        <Skeleton className="h-8 w-48" />
        <Card>
          <CardContent className="p-6">
            <div className="flex flex-col gap-4">
              <Skeleton className="h-6 w-64" />
              <Skeleton className="h-4 w-96" />
              <div className="grid grid-cols-4 gap-4 mt-4">
                {Array.from({ length: 4 }).map((_, i) => (
                  <Skeleton key={i} className="h-16" />
                ))}
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
    );
  }

  // Error state
  if (isError || !workflow) {
    return (
      <div className="flex flex-col gap-4">
        <Button variant="ghost" size="sm" onClick={() => router.push("/workflows")}>
          <ArrowLeft className="h-4 w-4" />
          Back to Workflows
        </Button>
        <ErrorState
          title="Workflow Not Found"
          message={
            error instanceof Error
              ? error.message
              : `Could not load workflow: ${workflowId}`
          }
          retry={() => void refetch()}
        />
      </div>
    );
  }

  return (
    <div className="flex flex-col gap-6">
      {/* Back navigation */}
      <Button
        variant="ghost"
        size="sm"
        className="self-start"
        onClick={() => router.push("/workflows")}
      >
        <ArrowLeft className="h-4 w-4" />
        Back to Workflows
      </Button>

      {/* Header card */}
      <Card>
        <CardContent className="p-6">
          <div className="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
            <div className="flex flex-col gap-2">
              <div className="flex items-center gap-3">
                <StatusBadge status={workflow.status as WorkflowStatus} />
                <h2 className="text-xl font-bold">{workflow.type}</h2>
              </div>

              <div className="flex items-center gap-2 text-sm">
                <span className="font-mono text-muted-foreground">{workflow.id}</span>
                <Button
                  variant="ghost"
                  size="icon"
                  className="h-7 w-7"
                  onClick={() => void handleCopyId()}
                >
                  {copied ? (
                    <Check className="h-3 w-3 text-emerald-500" />
                  ) : (
                    <Copy className="h-3 w-3" />
                  )}
                  <span className="sr-only">Copy ID</span>
                </Button>
              </div>

              {workflow.parent_workflow_id && (
                <p className="text-xs text-muted-foreground">
                  Parent:{" "}
                  <button
                    className="text-primary hover:underline font-mono"
                    onClick={() =>
                      router.push(`/workflows/${workflow.parent_workflow_id}`)
                    }
                  >
                    {workflow.parent_workflow_id}
                  </button>
                </p>
              )}
            </div>

            {/* Action buttons */}
            <div className="flex gap-2 flex-wrap">
              {canSignal && (
                <Button variant="outline" size="sm" onClick={() => setSignalOpen(true)}>
                  <Send className="h-4 w-4" />
                  Send Signal
                </Button>
              )}
              {canRetry && (
                <Button
                  variant="outline"
                  size="sm"
                  disabled={retryMutation.isPending}
                  onClick={() => retryMutation.mutate({ id: workflow.id })}
                >
                  {retryMutation.isPending ? (
                    <Loader2 className="h-4 w-4 animate-spin" />
                  ) : (
                    <RotateCcw className="h-4 w-4" />
                  )}
                  Retry
                </Button>
              )}
              {canCancel && (
                <Button
                  variant="destructive"
                  size="sm"
                  disabled={cancelMutation.isPending}
                  onClick={() => cancelMutation.mutate({ id: workflow.id })}
                >
                  {cancelMutation.isPending ? (
                    <Loader2 className="h-4 w-4 animate-spin" />
                  ) : (
                    <XCircle className="h-4 w-4" />
                  )}
                  Cancel
                </Button>
              )}
            </div>
          </div>

          {/* Metadata grid */}
          <Separator className="my-4" />
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div>
              <p className="text-xs text-muted-foreground uppercase tracking-wider">
                Started
              </p>
              <p className="text-sm mt-1">{formatTimestamp(workflow.started_at)}</p>
            </div>
            <div>
              <p className="text-xs text-muted-foreground uppercase tracking-wider">
                Completed
              </p>
              <p className="text-sm mt-1">
                {formatTimestamp(workflow.completed_at)}
              </p>
            </div>
            <div>
              <p className="text-xs text-muted-foreground uppercase tracking-wider">
                Duration
              </p>
              <p className="text-sm font-mono mt-1">
                {formatDuration(workflow.duration_ms)}
              </p>
            </div>
            <div>
              <p className="text-xs text-muted-foreground uppercase tracking-wider">
                Total Events
              </p>
              <p className="text-sm mt-1">{workflow.total_events}</p>
            </div>
          </div>

          {/* Mutation feedback */}
          {retryMutation.isSuccess && (
            <div className="mt-4 rounded-md border border-emerald-500/30 bg-emerald-500/10 p-3">
              <p className="text-sm text-emerald-700 dark:text-emerald-400">
                Retry initiated successfully
              </p>
            </div>
          )}
          {retryMutation.isError && (
            <div className="mt-4 rounded-md border border-destructive/30 bg-destructive/10 p-3">
              <p className="text-sm text-destructive">
                Retry failed:{" "}
                {retryMutation.error instanceof Error
                  ? retryMutation.error.message
                  : "Unknown error"}
              </p>
            </div>
          )}
          {cancelMutation.isSuccess && (
            <div className="mt-4 rounded-md border border-amber-500/30 bg-amber-500/10 p-3">
              <p className="text-sm text-amber-700 dark:text-amber-400">
                Cancellation dispatched
              </p>
            </div>
          )}
        </CardContent>
      </Card>

      {/* Tabs */}
      <Tabs defaultValue="timeline">
        <TabsList>
          <TabsTrigger value="timeline">Timeline</TabsTrigger>
          <TabsTrigger value="input-output">Input / Output</TabsTrigger>
          <TabsTrigger value="details">Details</TabsTrigger>
        </TabsList>

        <TabsContent value="timeline">
          <div className="flex flex-col gap-4 mt-4">
            {/* Event type filter */}
            <div className="flex items-center gap-3 flex-wrap">
              <Select
                value={eventTypeFilter}
                onValueChange={(val) => {
                  setEventTypeFilter(val);
                  setEventsPage(1);
                }}
              >
                <SelectTrigger className="max-w-xs">
                  <SelectValue placeholder="Filter events" />
                </SelectTrigger>
                <SelectContent>
                  {EVENT_TYPE_OPTIONS.map((opt) => (
                    <SelectItem key={opt.key} value={opt.key}>
                      {opt.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              {eventTypeFilter && (
                <Badge
                  variant="secondary"
                  className="cursor-pointer gap-1"
                  onClick={() => setEventTypeFilter("")}
                >
                  {eventTypeFilter.split("_").map((w) => w.charAt(0).toUpperCase() + w.slice(1)).join(" ")}
                  <X className="h-3 w-3" />
                </Badge>
              )}
            </div>

            {eventsLoading ? (
              <div className="flex flex-col gap-4">
                {Array.from({ length: 3 }).map((_, i) => (
                  <div key={i} className="pl-10">
                    <Skeleton className="h-20 w-full rounded-lg" />
                  </div>
                ))}
              </div>
            ) : (
              <>
                <EventTimeline events={events} />
                {eventsTotalPages > 1 && (
                  <div className="flex items-center justify-center gap-2 mt-4">
                    <Button
                      variant="outline"
                      size="sm"
                      disabled={eventsPage <= 1}
                      onClick={() => setEventsPage(eventsPage - 1)}
                    >
                      Previous
                    </Button>
                    <span className="text-sm text-muted-foreground">
                      Page {eventsPage} of {eventsTotalPages}
                    </span>
                    <Button
                      variant="outline"
                      size="sm"
                      disabled={eventsPage >= eventsTotalPages}
                      onClick={() => setEventsPage(eventsPage + 1)}
                    >
                      Next
                    </Button>
                  </div>
                )}
              </>
            )}
          </div>
        </TabsContent>

        <TabsContent value="input-output">
          <div className="flex flex-col gap-4 mt-4">
            <Card>
              <CardHeader>
                <CardTitle className="text-base">Input</CardTitle>
              </CardHeader>
              <CardContent>
                <JsonViewer data={workflow.input} />
              </CardContent>
            </Card>
            <Card>
              <CardHeader>
                <CardTitle className="text-base">Output</CardTitle>
              </CardHeader>
              <CardContent>
                <JsonViewer data={workflow.output} />
              </CardContent>
            </Card>
          </div>
        </TabsContent>

        <TabsContent value="details">
          <Card className="mt-4">
            <CardHeader>
              <CardTitle className="text-base">Execution Details</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <p className="text-xs text-muted-foreground uppercase tracking-wider">
                    Execution ID
                  </p>
                  <p className="text-sm font-mono mt-1">{workflow.id}</p>
                </div>
                <div>
                  <p className="text-xs text-muted-foreground uppercase tracking-wider">
                    Workflow ID
                  </p>
                  <p className="text-sm font-mono mt-1">{workflow.workflow_id}</p>
                </div>
                {workflow.run_id && (
                  <div>
                    <p className="text-xs text-muted-foreground uppercase tracking-wider">
                      Run ID
                    </p>
                    <p className="text-sm font-mono mt-1">{workflow.run_id}</p>
                  </div>
                )}
                <div>
                  <p className="text-xs text-muted-foreground uppercase tracking-wider">
                    Type
                  </p>
                  <p className="text-sm mt-1">{workflow.type}</p>
                </div>
                <div>
                  <p className="text-xs text-muted-foreground uppercase tracking-wider">
                    Status
                  </p>
                  <div className="mt-1">
                    <StatusBadge status={workflow.status as WorkflowStatus} />
                  </div>
                </div>
                <div>
                  <p className="text-xs text-muted-foreground uppercase tracking-wider">
                    Has More Events
                  </p>
                  <p className="text-sm mt-1">
                    {workflow.has_more_events ? "Yes" : "No"}
                  </p>
                </div>
              </div>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>

      {/* Signal Dialog */}
      <SignalDialog
        open={signalOpen}
        onOpenChange={setSignalOpen}
        workflowId={workflow.id}
      />
    </div>
  );
}
