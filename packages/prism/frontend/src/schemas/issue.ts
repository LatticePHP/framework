import { z } from "zod";

// --- Stack Frame ---

export const StackFrameSchema = z.object({
  file: z.string(),
  line: z.number(),
  function: z.string().nullish(),
  class: z.string().nullish(),
  module: z.string().nullish(),
  column: z.number().nullish(),
  code_context: z
    .object({
      pre: z.array(z.string()),
      line: z.string(),
      post: z.array(z.string()),
    })
    .nullish(),
});

export type StackFrame = z.infer<typeof StackFrameSchema>;

// --- Error Event ---

export const ErrorEventSchema = z.object({
  event_id: z.string(),
  timestamp: z.string(),
  project_id: z.string(),
  environment: z.string(),
  platform: z.string(),
  level: z.enum(["error", "warning", "fatal", "info"]),
  exception: z
    .object({
      type: z.string().nullish(),
      value: z.string().nullish(),
      stacktrace: z.array(StackFrameSchema).optional(),
    })
    .optional(),
  context: z.record(z.string(), z.unknown()).optional(),
  tags: z.record(z.string(), z.string()).optional(),
  release: z.string().nullish(),
  server_name: z.string().nullish(),
  transaction: z.string().nullish(),
  fingerprint: z.array(z.string()).nullish(),
});

export type ErrorEvent = z.infer<typeof ErrorEventSchema>;

// --- Issue ---

export const IssueSchema = z.object({
  id: z.string(),
  project_id: z.string(),
  fingerprint: z.string(),
  title: z.string(),
  level: z.enum(["error", "warning", "fatal", "info"]),
  status: z.enum(["unresolved", "resolved", "ignored"]),
  count: z.number(),
  first_seen: z.string(),
  last_seen: z.string(),
  culprit: z.string().nullish(),
  platform: z.string().nullish(),
  environment: z.string().nullish(),
  release: z.string().nullish(),
  created_at: z.string().nullish(),
  updated_at: z.string().nullish(),
});

export type Issue = z.infer<typeof IssueSchema>;

export type IssueLevel = Issue["level"];
export type IssueStatus = Issue["status"];

// --- Project ---

export const ProjectSchema = z.object({
  id: z.string(),
  name: z.string(),
  slug: z.string().nullish(),
  created_at: z.string(),
});

export type Project = z.infer<typeof ProjectSchema>;

// --- API Responses ---

export const IssueListResponseSchema = z.object({
  status: z.number(),
  data: z.array(IssueSchema),
  meta: z.object({
    total: z.number(),
    limit: z.number(),
    offset: z.number(),
  }),
});

export const IssueDetailResponseSchema = z.object({
  status: z.number(),
  data: z.object({
    issue: IssueSchema,
    sample_events: z.array(ErrorEventSchema),
  }),
});

export const ProjectListResponseSchema = z.object({
  status: z.number(),
  data: z.array(ProjectSchema),
});

export const StatsResponseSchema = z.object({
  status: z.number(),
  data: z.object({
    total_issues: z.number(),
    unresolved: z.number(),
    resolved: z.number(),
    ignored: z.number(),
    total_events: z.number(),
    by_level: z.record(z.string(), z.number()),
  }),
});

export type Stats = z.infer<typeof StatsResponseSchema>["data"];

// --- Live Feed Signal ---

export const LiveSignalSchema = z.object({
  event_id: z.string(),
  issue_id: z.string(),
  fingerprint: z.string(),
  level: z.enum(["error", "warning", "fatal", "info"]),
  title: z.string(),
  is_new_issue: z.boolean(),
  is_regression: z.boolean(),
  timestamp: z.string().optional(),
  environment: z.string().optional(),
  project_id: z.string().optional(),
});

export type LiveSignal = z.infer<typeof LiveSignalSchema>;
