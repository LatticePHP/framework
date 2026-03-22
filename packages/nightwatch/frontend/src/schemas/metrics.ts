import { z } from 'zod';

// ── Metrics overview ──

export const MetricValueSchema = z.object({
  label: z.string(),
  value: z.number(),
  unit: z.string().optional(),
  trend: z.enum(['up', 'down', 'stable']).optional(),
  change_percent: z.number().optional(),
});

export type MetricValue = z.infer<typeof MetricValueSchema>;

export const MetricsOverviewSchema = z.object({
  requests_per_minute: MetricValueSchema,
  avg_response_time: MetricValueSchema,
  p99_response_time: MetricValueSchema,
  error_rate: MetricValueSchema,
  slow_queries_count: MetricValueSchema,
  cache_hit_ratio: MetricValueSchema,
  queue_throughput: MetricValueSchema,
  cpu_usage: MetricValueSchema.optional(),
  memory_usage: MetricValueSchema.optional(),
  disk_usage: MetricValueSchema.optional(),
});

export type MetricsOverview = z.infer<typeof MetricsOverviewSchema>;

// ── Slow requests ──

export const SlowRequestSchema = z.object({
  endpoint: z.string(),
  method: z.string().optional(),
  count: z.number(),
  avg: z.number(),
  p50: z.number(),
  p95: z.number(),
  p99: z.number(),
  min: z.number().optional(),
  max: z.number().optional(),
  status_codes: z.record(z.number()).optional(),
});

export type SlowRequest = z.infer<typeof SlowRequestSchema>;

export const SlowRequestsResponseSchema = z.object({
  data: z.array(SlowRequestSchema),
  period: z.string(),
  total_requests: z.number(),
});

export type SlowRequestsResponse = z.infer<typeof SlowRequestsResponseSchema>;

// ── Slow queries ──

export const SlowQuerySchema = z.object({
  sql: z.string(),
  count: z.number(),
  avg_duration: z.number(),
  p95_duration: z.number(),
  max_duration: z.number().optional(),
  total_time: z.number().optional(),
});

export type SlowQuery = z.infer<typeof SlowQuerySchema>;

export const SlowQueriesResponseSchema = z.object({
  data: z.array(SlowQuerySchema),
  period: z.string(),
  total_queries: z.number(),
});

export type SlowQueriesResponse = z.infer<typeof SlowQueriesResponseSchema>;

// ── Exception counts ──

export const ExceptionCountSchema = z.object({
  class: z.string(),
  count: z.number(),
  trend: z.enum(['increasing', 'decreasing', 'stable']),
  first_seen: z.string().nullable(),
  last_seen: z.string().nullable(),
});

export type ExceptionCount = z.infer<typeof ExceptionCountSchema>;

export const ExceptionCountsResponseSchema = z.object({
  data: z.array(ExceptionCountSchema),
  total_exceptions: z.number(),
});

export type ExceptionCountsResponse = z.infer<typeof ExceptionCountsResponseSchema>;

// ── Cache ratio ──

export const CacheRatioPointSchema = z.object({
  timestamp: z.string(),
  hit_ratio: z.number(),
  total_operations: z.number(),
});

export type CacheRatioPoint = z.infer<typeof CacheRatioPointSchema>;

// ── Queue throughput ──

export const QueueThroughputPointSchema = z.object({
  timestamp: z.string(),
  processed: z.number(),
  failed: z.number(),
  avg_wait_ms: z.number().optional(),
});

export type QueueThroughputPoint = z.infer<typeof QueueThroughputPointSchema>;

// ── Server vitals ──

export const ServerVitalsPointSchema = z.object({
  timestamp: z.string(),
  cpu_percent: z.number(),
  memory_percent: z.number(),
  memory_used_mb: z.number().optional(),
  memory_total_mb: z.number().optional(),
  disk_percent: z.number(),
  disk_used_gb: z.number().optional(),
  disk_total_gb: z.number().optional(),
});

export type ServerVitalsPoint = z.infer<typeof ServerVitalsPointSchema>;

// ── Time period type ──

export const TimePeriodSchema = z.enum(['1h', '6h', '24h', '7d', '30d']);
export type TimePeriod = z.infer<typeof TimePeriodSchema>;
