import { z } from "zod";

// --- Workflow Status ---
export const WorkflowStatusSchema = z.enum([
  "running",
  "completed",
  "failed",
  "cancelled",
  "terminated",
  "timed_out",
]);
export type WorkflowStatus = z.infer<typeof WorkflowStatusSchema>;

// --- Event Types ---
export const WorkflowEventTypeSchema = z.enum([
  "workflow_started",
  "workflow_completed",
  "workflow_failed",
  "workflow_cancelled",
  "workflow_terminated",
  "activity_scheduled",
  "activity_started",
  "activity_completed",
  "activity_failed",
  "activity_timed_out",
  "timer_started",
  "timer_fired",
  "timer_cancelled",
  "signal_received",
  "query_received",
  "update_received",
  "child_workflow_started",
  "child_workflow_completed",
  "child_workflow_failed",
]);
export type WorkflowEventType = z.infer<typeof WorkflowEventTypeSchema>;

// --- Workflow list item ---
export const WorkflowSummarySchema = z.object({
  id: z.string(),
  workflow_id: z.string(),
  type: z.string(),
  status: WorkflowStatusSchema,
  started_at: z.string(),
  duration_ms: z.number().nullable(),
  last_event_type: WorkflowEventTypeSchema.nullable().optional(),
  last_event_at: z.string().nullable().optional(),
});
export type WorkflowSummary = z.infer<typeof WorkflowSummarySchema>;

// --- Pagination meta ---
export const PaginationMetaSchema = z.object({
  page: z.number(),
  per_page: z.number(),
  total: z.number(),
  has_more: z.boolean(),
});
export type PaginationMeta = z.infer<typeof PaginationMetaSchema>;

// --- Workflow list response ---
export const WorkflowListResponseSchema = z.object({
  data: z.array(WorkflowSummarySchema),
  meta: PaginationMetaSchema,
});
export type WorkflowListResponse = z.infer<typeof WorkflowListResponseSchema>;

// --- Event ---
export const EventSchema = z.object({
  sequence: z.number(),
  type: WorkflowEventTypeSchema,
  timestamp: z.string(),
  data: z.unknown().nullable(),
  duration_ms: z.number().nullable().optional(),
});
export type WorkflowEvent = z.infer<typeof EventSchema>;

// --- Workflow detail ---
export const WorkflowDetailSchema = z.object({
  id: z.string(),
  workflow_id: z.string(),
  type: z.string(),
  run_id: z.string().nullable().optional(),
  status: WorkflowStatusSchema,
  input: z.unknown().nullable(),
  output: z.unknown().nullable(),
  started_at: z.string(),
  completed_at: z.string().nullable(),
  duration_ms: z.number().nullable(),
  parent_workflow_id: z.string().nullable(),
  events: z.array(EventSchema),
  has_more_events: z.boolean(),
  total_events: z.number(),
});
export type WorkflowDetail = z.infer<typeof WorkflowDetailSchema>;

export const WorkflowDetailResponseSchema = z.object({
  data: WorkflowDetailSchema,
});
export type WorkflowDetailResponse = z.infer<typeof WorkflowDetailResponseSchema>;

// --- Events list response ---
export const EventsListResponseSchema = z.object({
  data: z.array(EventSchema),
  meta: PaginationMetaSchema,
});
export type EventsListResponse = z.infer<typeof EventsListResponseSchema>;

// --- Stats ---
export const StatsSchema = z.object({
  running: z.number(),
  completed: z.number(),
  failed: z.number(),
  cancelled: z.number(),
  avg_duration_ms: z.number(),
});
export type WorkflowStats = z.infer<typeof StatsSchema>;

export const StatsResponseSchema = z.object({
  data: StatsSchema,
});
export type StatsResponse = z.infer<typeof StatsResponseSchema>;

// --- Signal request / response ---
export const SignalRequestSchema = z.object({
  signal: z.string().min(1),
  payload: z.unknown().optional(),
});
export type SignalRequest = z.infer<typeof SignalRequestSchema>;

export const SignalResponseSchema = z.object({
  data: z.object({
    id: z.string(),
    signal: z.string(),
    status: z.string(),
  }),
});

// --- Retry response ---
export const RetryResponseSchema = z.object({
  data: z.object({
    id: z.string(),
    status: z.string(),
    message: z.string(),
  }),
});

// --- Cancel response ---
export const CancelResponseSchema = z.object({
  data: z.object({
    id: z.string(),
    status: z.string(),
    message: z.string(),
  }),
});

// --- API error ---
export const ApiErrorSchema = z.object({
  type: z.string().optional(),
  title: z.string(),
  status: z.number(),
  detail: z.string(),
});
export type ApiError = z.infer<typeof ApiErrorSchema>;
