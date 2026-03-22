"use client";

import type { WorkflowEvent, WorkflowEventType } from "@/lib/schemas";
import { Card, CardContent } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Separator } from "@/components/ui/separator";
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from "@/components/ui/collapsible";
import { ChevronDown } from "lucide-react";
import { cn } from "@/lib/utils";
import { formatTimestamp, formatDuration, formatEventType } from "@/lib/formatters";
import { EmptyState } from "@/components/feedback/empty-state";
import { Clock } from "lucide-react";

const EVENT_TYPE_VARIANT: Record<string, "default" | "secondary" | "destructive" | "outline" | "success" | "warning"> = {
  workflow_started: "default",
  workflow_completed: "success",
  workflow_failed: "destructive",
  workflow_cancelled: "warning",
  workflow_terminated: "secondary",
  activity_scheduled: "outline",
  activity_started: "default",
  activity_completed: "success",
  activity_failed: "destructive",
  activity_timed_out: "warning",
  timer_started: "outline",
  timer_fired: "default",
  timer_cancelled: "secondary",
  signal_received: "warning",
  query_received: "secondary",
  update_received: "default",
  child_workflow_started: "default",
  child_workflow_completed: "success",
  child_workflow_failed: "destructive",
};

function getEventVariant(type: WorkflowEventType) {
  return EVENT_TYPE_VARIANT[type] ?? "secondary";
}

function getDotColor(type: WorkflowEventType): string {
  const variant = getEventVariant(type);
  switch (variant) {
    case "success":
      return "bg-emerald-500";
    case "destructive":
      return "bg-destructive";
    case "warning":
      return "bg-amber-500";
    case "default":
      return "bg-primary";
    default:
      return "bg-muted-foreground";
  }
}

interface EventTimelineProps {
  events: WorkflowEvent[];
}

export function EventTimeline({ events }: EventTimelineProps) {
  if (events.length === 0) {
    return (
      <EmptyState
        icon={Clock}
        title="No events recorded"
        description="Events will appear here as the workflow executes."
      />
    );
  }

  return (
    <div className="relative">
      {/* Vertical line */}
      <div className="absolute left-4 top-0 bottom-0 w-px bg-border" />

      <div className="flex flex-col gap-4">
        {events.map((event, index) => {
          const hasData = event.data != null;

          return (
            <div key={`${event.sequence}-${index}`} className="relative pl-10">
              {/* Timeline dot */}
              <div
                className={cn(
                  "absolute left-[11px] top-4 h-3 w-3 rounded-full ring-2 ring-background",
                  getDotColor(event.type)
                )}
              />

              <Card>
                <CardContent className="p-3">
                  <div className="flex items-start justify-between gap-2">
                    <div className="flex items-center gap-2 flex-wrap">
                      <Badge variant={getEventVariant(event.type)}>
                        {formatEventType(event.type)}
                      </Badge>
                      {event.duration_ms != null && (
                        <span className="text-xs text-muted-foreground">
                          +{formatDuration(event.duration_ms)}
                        </span>
                      )}
                    </div>
                    <span className="text-xs text-muted-foreground whitespace-nowrap">
                      {formatTimestamp(event.timestamp)}
                    </span>
                  </div>

                  {hasData && (
                    <>
                      <Separator className="my-2" />
                      <Collapsible>
                        <CollapsibleTrigger className="flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground transition-colors">
                          <ChevronDown className="h-3 w-3" />
                          Event Data
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                          <pre className="mt-2 text-xs bg-muted rounded-md p-3 overflow-auto max-h-64 font-mono">
                            {JSON.stringify(event.data, null, 2)}
                          </pre>
                        </CollapsibleContent>
                      </Collapsible>
                    </>
                  )}
                </CardContent>
              </Card>
            </div>
          );
        })}
      </div>
    </div>
  );
}
