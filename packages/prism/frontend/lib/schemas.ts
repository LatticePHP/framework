import { z } from "zod";

// ── Error levels matching PHP ErrorLevel enum ──

export const ErrorLevelEnum = z.enum(["error", "warning", "fatal", "info"]);
export type ErrorLevel = z.infer<typeof ErrorLevelEnum>;

// ── Issue status matching PHP IssueStatus enum ──

export const IssueStatusEnum = z.enum(["unresolved", "resolved", "ignored"]);
export type IssueStatus = z.infer<typeof IssueStatusEnum>;

// ── Stack frame matching PHP StackFrame ──

export const StackFrameSchema = z.object({
  file: z.string(),
  line: z.number(),
  function: z.string().nullable().optional(),
  class: z.string().nullable().optional(),
  module: z.string().nullable().optional(),
  column: z.number().nullable().optional(),
  code_context: z
    .object({
      pre: z.array(z.string()),
      line: z.string(),
      post: z.array(z.string()),
    })
    .nullable()
    .optional(),
});

export type StackFrame = z.infer<typeof StackFrameSchema>;

// ── Project matching PHP Project::toArray() ──

export const ProjectSchema = z.object({
  id: z.string(),
  name: z.string(),
  slug: z.string().nullable().optional(),
  created_at: z.string(),
});

export type Project = z.infer<typeof ProjectSchema>;

// ── Issue matching PHP Issue::toArray() ──

export const IssueSchema = z.object({
  id: z.string(),
  project_id: z.string(),
  fingerprint: z.string(),
  title: z.string(),
  level: ErrorLevelEnum,
  status: IssueStatusEnum,
  count: z.number(),
  first_seen: z.string(),
  last_seen: z.string(),
  culprit: z.string().nullable().optional(),
  platform: z.string().nullable().optional(),
  environment: z.string().nullable().optional(),
  release: z.string().nullable().optional(),
  created_at: z.string().nullable().optional(),
  updated_at: z.string().nullable().optional(),
});

export type Issue = z.infer<typeof IssueSchema>;

// ── Error event matching PHP ErrorEvent::toArray() ──

export const ErrorEventSchema = z.object({
  event_id: z.string(),
  timestamp: z.string(),
  project_id: z.string(),
  environment: z.string(),
  platform: z.string(),
  level: ErrorLevelEnum,
  exception: z
    .object({
      type: z.string().optional(),
      value: z.string().optional(),
      stacktrace: z.array(StackFrameSchema).optional(),
    })
    .optional(),
  context: z.record(z.unknown()).optional(),
  tags: z.record(z.string()).optional(),
  release: z.string().nullable().optional(),
  server_name: z.string().nullable().optional(),
  transaction: z.string().nullable().optional(),
});

export type ErrorEvent = z.infer<typeof ErrorEventSchema>;

// ── Stats response matching PHP StatsAction ──

export const StatsSchema = z.object({
  total_issues: z.number(),
  unresolved: z.number(),
  resolved: z.number(),
  ignored: z.number(),
  total_events: z.number(),
  by_level: z.record(z.number()),
});

export type Stats = z.infer<typeof StatsSchema>;

// ── API response wrappers ──

export const ApiResponseSchema = <T extends z.ZodTypeAny>(dataSchema: T) =>
  z.object({
    status: z.number(),
    data: dataSchema,
  });

export const PaginatedMetaSchema = z.object({
  total: z.number(),
  limit: z.number(),
  offset: z.number(),
});

export type PaginatedMeta = z.infer<typeof PaginatedMetaSchema>;
