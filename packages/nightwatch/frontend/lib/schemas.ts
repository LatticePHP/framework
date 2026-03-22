import { z } from "zod";

// ── Entry types ──

export const EntryTypeEnum = z.enum([
  "request",
  "query",
  "exception",
  "event",
  "cache",
  "job",
  "mail",
  "log",
  "model",
  "gate",
]);

export type EntryType = z.infer<typeof EntryTypeEnum>;

export const BaseEntrySchema = z.object({
  uuid: z.string(),
  type: EntryTypeEnum,
  timestamp: z.string(),
  data: z.record(z.unknown()),
  tags: z.array(z.string()),
  batch_id: z.string().nullable(),
});

export type BaseEntry = z.infer<typeof BaseEntrySchema>;

// ── Request entry data ──

export const RequestDataSchema = z.object({
  method: z.string(),
  uri: z.string(),
  route_name: z.string().nullable().optional(),
  controller: z.string().nullable().optional(),
  headers: z.record(z.unknown()).optional(),
  ip: z.string().nullable().optional(),
  session_id: z.string().nullable().optional(),
  status: z.number(),
  response_size: z.number().nullable().optional(),
  content_type: z.string().nullable().optional(),
  duration_ms: z.number(),
  user_id: z.union([z.string(), z.number()]).nullable().optional(),
  middleware: z.array(z.string()).optional(),
});

export type RequestData = z.infer<typeof RequestDataSchema>;

// ── Query entry data ──

export const QueryDataSchema = z.object({
  sql: z.string(),
  bindings: z.array(z.unknown()).optional(),
  duration_ms: z.number(),
  connection: z.string(),
  caller: z.string().nullable().optional(),
  slow: z.boolean(),
  query_type: z.string(),
  n1_detected: z.boolean(),
});

export type QueryData = z.infer<typeof QueryDataSchema>;

// ── Exception entry data ──

export const StackFrameSchema = z.object({
  file: z.string().nullable().optional(),
  line: z.number().nullable().optional(),
  class: z.string().nullable().optional(),
  function: z.string().nullable().optional(),
  type: z.string().nullable().optional(),
});

export type StackFrame = z.infer<typeof StackFrameSchema>;

export const ExceptionDataSchema = z.object({
  class: z.string(),
  message: z.string(),
  code: z.union([z.number(), z.string()]).optional(),
  file: z.string().optional(),
  line: z.number().optional(),
  trace: z.array(StackFrameSchema).optional(),
  request_context: z.record(z.unknown()).optional(),
  previous: z.record(z.unknown()).nullable().optional(),
  custom_context: z.record(z.unknown()).optional(),
});

export type ExceptionData = z.infer<typeof ExceptionDataSchema>;

// ── Event entry data ──

export const EventDataSchema = z.object({
  event_class: z.string(),
  payload: z.record(z.unknown()).optional(),
  listeners: z.array(z.string()),
  broadcast: z.boolean(),
});

export type EventData = z.infer<typeof EventDataSchema>;

// ── Cache entry data ──

export const CacheDataSchema = z.object({
  operation: z.enum(["hit", "miss", "write", "forget"]),
  key: z.string(),
  ttl: z.number().nullable().optional(),
  value_size: z.number().nullable().optional(),
  store: z.string(),
  duration_ms: z.number(),
});

export type CacheData = z.infer<typeof CacheDataSchema>;

// ── Job entry data ──

export const JobDataSchema = z.object({
  job_class: z.string(),
  queue: z.string(),
  connection: z.string(),
  payload: z.record(z.unknown()).optional(),
  status: z.string(),
  duration_ms: z.number().nullable().optional(),
  attempt: z.number(),
  max_tries: z.number().nullable().optional(),
  exception: z.string().nullable().optional(),
});

export type JobData = z.infer<typeof JobDataSchema>;

// ── Mail entry data ──

export const MailDataSchema = z.object({
  to: z.array(z.string()).or(z.string()).optional(),
  cc: z.array(z.string()).nullable().optional(),
  bcc: z.array(z.string()).nullable().optional(),
  subject: z.string(),
  from: z.string().optional(),
  mailable_class: z.string().optional(),
  attachments: z.array(z.string()).optional(),
  queued: z.boolean().optional(),
});

export type MailData = z.infer<typeof MailDataSchema>;

// ── Log entry data ──

export const LogDataSchema = z.object({
  level: z.string(),
  message: z.string(),
  context: z.record(z.unknown()).optional(),
  channel: z.string(),
});

export type LogData = z.infer<typeof LogDataSchema>;

// ── Paginated response ──

export type PaginatedResponse<T> = {
  data: T[];
  total: number;
  limit: number;
  offset: number;
};

// ── Status response ──

export const StatusResponseSchema = z.object({
  mode: z.enum(["dev", "prod"]),
  enabled: z.boolean(),
  storage_size: z.number().optional(),
  entry_counts: z.record(z.number()).optional(),
});

export type StatusResponse = z.infer<typeof StatusResponseSchema>;

// ── Metrics types ──

export const TimePeriodSchema = z.enum(["1h", "6h", "24h", "7d", "30d"]);
export type TimePeriod = z.infer<typeof TimePeriodSchema>;

export interface MetricValue {
  label: string;
  value: number;
  unit?: string;
  trend?: "up" | "down" | "stable";
  change_percent?: number;
}

export interface MetricsOverview {
  requests_per_minute: MetricValue;
  avg_response_time: MetricValue;
  p99_response_time: MetricValue;
  error_rate: MetricValue;
  slow_queries_count: MetricValue;
  cache_hit_ratio: MetricValue;
  queue_throughput: MetricValue;
  cpu_usage?: MetricValue;
  memory_usage?: MetricValue;
  disk_usage?: MetricValue;
}

export interface SlowRequest {
  endpoint: string;
  method?: string;
  count: number;
  avg: number;
  p50: number;
  p95: number;
  p99: number;
  min?: number;
  max?: number;
  status_codes?: Record<string, number>;
}

export interface SlowRequestsResponse {
  data: SlowRequest[];
  period: string;
  total_requests: number;
}

export interface SlowQuery {
  sql: string;
  count: number;
  avg_duration: number;
  p95_duration: number;
  max_duration?: number;
  total_time?: number;
}

export interface SlowQueriesResponse {
  data: SlowQuery[];
  period: string;
  total_queries: number;
}

export interface ExceptionCount {
  class: string;
  count: number;
  trend: "increasing" | "decreasing" | "stable";
  first_seen: string | null;
  last_seen: string | null;
}

export interface ExceptionCountsResponse {
  data: ExceptionCount[];
  total_exceptions: number;
}
