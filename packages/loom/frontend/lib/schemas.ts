import { z } from "zod";

// --- Stats (GET /api/loom/stats) ---

export const StatsSchema = z.object({
  total_processed: z.number(),
  total_failed: z.number(),
  processed_last_hour: z.number(),
  failed_last_hour: z.number(),
  throughput_per_minute: z.number(),
  avg_runtime_ms: z.number(),
  avg_wait_ms: z.number(),
  active_workers: z.number(),
  queue_sizes: z.record(z.string(), z.number()),
});

export type Stats = z.infer<typeof StatsSchema>;

// --- Job (recent/failed/detail) ---

export const JobSchema = z.object({
  id: z.string(),
  class: z.string(),
  queue: z.string(),
  status: z.string(),
  runtime_ms: z.number().nullable().optional(),
  attempts: z.number().optional().default(0),
  created_at: z.string().nullable().optional(),
  completed_at: z.string().nullable().optional(),
});

export type Job = z.infer<typeof JobSchema>;

export const FailedJobSchema = JobSchema.extend({
  exception_class: z.string().optional(),
  exception_message: z.string().optional(),
  exception_trace: z
    .array(
      z.object({
        file: z.string().optional(),
        line: z.number().optional(),
        function: z.string().optional(),
        class: z.string().optional(),
      })
    )
    .optional(),
  failed_at: z.string().nullable().optional(),
});

export type FailedJob = z.infer<typeof FailedJobSchema>;

export const JobDetailSchema = FailedJobSchema.extend({
  connection: z.string().optional(),
  max_attempts: z.number().optional(),
  timeout: z.number().optional(),
  payload: z.unknown().optional(),
});

export type JobDetail = z.infer<typeof JobDetailSchema>;

// --- Paginated responses ---

export const PaginationMetaSchema = z.object({
  page: z.number(),
  per_page: z.number(),
});

export type PaginationMeta = z.infer<typeof PaginationMetaSchema>;

export const PaginatedJobsResponseSchema = z.object({
  data: z.array(JobSchema),
  meta: PaginationMetaSchema,
});

export const PaginatedFailedJobsResponseSchema = z.object({
  data: z.array(FailedJobSchema),
  meta: PaginationMetaSchema,
});

// --- Workers (GET /api/loom/workers) ---

export const WorkerSchema = z.object({
  id: z.string(),
  queue: z.string(),
  status: z.string(),
  pid: z.number(),
  memory_mb: z.number(),
  uptime: z.number(),
  jobs_processed: z.number(),
  last_heartbeat: z.number(),
});

export type Worker = z.infer<typeof WorkerSchema>;

export const WorkersResponseSchema = z.object({
  data: z.array(WorkerSchema),
});

// --- Metrics (GET /api/loom/metrics) ---

export const ThroughputPointSchema = z.object({
  timestamp: z.number(),
  count: z.number(),
});

export type ThroughputPoint = z.infer<typeof ThroughputPointSchema>;

export const RuntimePointSchema = z.object({
  timestamp: z.number(),
  avg_runtime_ms: z.number(),
});

export type RuntimePoint = z.infer<typeof RuntimePointSchema>;

export const TimeSeriesMetricsSchema = z.object({
  data: z.object({
    throughput: z.array(ThroughputPointSchema),
    runtime: z.array(RuntimePointSchema),
  }),
  meta: z.object({
    period: z.string(),
    queue: z.string().nullable(),
  }),
});

export type TimeSeriesMetrics = z.infer<typeof TimeSeriesMetricsSchema>;

// --- Mutation responses ---

export const RetryResponseSchema = z.object({
  status: z.literal("retried"),
  job_id: z.string(),
});

export const RetryAllResponseSchema = z.object({
  status: z.literal("retried"),
  count: z.number(),
});

export const DeleteResponseSchema = z.object({
  status: z.literal("deleted"),
});
