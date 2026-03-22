import { z } from 'zod';

// ── Base entry schema matching PHP Entry::jsonSerialize() ──

export const EntryTypeEnum = z.enum([
  'request',
  'query',
  'exception',
  'event',
  'cache',
  'job',
  'mail',
  'log',
  'model',
  'gate',
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

export const RequestEntrySchema = BaseEntrySchema.extend({
  type: z.literal('request'),
  data: RequestDataSchema,
});

export type RequestEntry = z.infer<typeof RequestEntrySchema>;

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

export const QueryEntrySchema = BaseEntrySchema.extend({
  type: z.literal('query'),
  data: QueryDataSchema,
});

export type QueryEntry = z.infer<typeof QueryEntrySchema>;

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

export const ExceptionEntrySchema = BaseEntrySchema.extend({
  type: z.literal('exception'),
  data: ExceptionDataSchema,
});

export type ExceptionEntry = z.infer<typeof ExceptionEntrySchema>;

// ── Event entry data ──

export const EventDataSchema = z.object({
  event_class: z.string(),
  payload: z.record(z.unknown()).optional(),
  listeners: z.array(z.string()),
  broadcast: z.boolean(),
});

export type EventData = z.infer<typeof EventDataSchema>;

export const EventEntrySchema = BaseEntrySchema.extend({
  type: z.literal('event'),
  data: EventDataSchema,
});

export type EventEntry = z.infer<typeof EventEntrySchema>;

// ── Cache entry data ──

export const CacheDataSchema = z.object({
  operation: z.enum(['hit', 'miss', 'write', 'forget']),
  key: z.string(),
  ttl: z.number().nullable().optional(),
  value_size: z.number().nullable().optional(),
  store: z.string(),
  duration_ms: z.number(),
});

export type CacheData = z.infer<typeof CacheDataSchema>;

export const CacheEntrySchema = BaseEntrySchema.extend({
  type: z.literal('cache'),
  data: CacheDataSchema,
});

export type CacheEntry = z.infer<typeof CacheEntrySchema>;

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

export const JobEntrySchema = BaseEntrySchema.extend({
  type: z.literal('job'),
  data: JobDataSchema,
});

export type JobEntry = z.infer<typeof JobEntrySchema>;

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

export const MailEntrySchema = BaseEntrySchema.extend({
  type: z.literal('mail'),
  data: MailDataSchema,
});

export type MailEntry = z.infer<typeof MailEntrySchema>;

// ── Log entry data ──

export const LogDataSchema = z.object({
  level: z.string(),
  message: z.string(),
  context: z.record(z.unknown()).optional(),
  channel: z.string(),
});

export type LogData = z.infer<typeof LogDataSchema>;

export const LogEntrySchema = BaseEntrySchema.extend({
  type: z.literal('log'),
  data: LogDataSchema,
});

export type LogEntry = z.infer<typeof LogEntrySchema>;

// ── Paginated response ──

export const PaginatedResponseSchema = <T extends z.ZodTypeAny>(itemSchema: T) =>
  z.object({
    data: z.array(itemSchema),
    total: z.number(),
    limit: z.number(),
    offset: z.number(),
  });

export type PaginatedResponse<T> = {
  data: T[];
  total: number;
  limit: number;
  offset: number;
};

// ── Status response ──

export const StatusResponseSchema = z.object({
  mode: z.enum(['dev', 'prod']),
  enabled: z.boolean(),
  storage_size: z.number().optional(),
  entry_counts: z.record(z.number()).optional(),
});

export type StatusResponse = z.infer<typeof StatusResponseSchema>;
