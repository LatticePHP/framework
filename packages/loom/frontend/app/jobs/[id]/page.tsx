"use client";

import { useEffect, useState, useCallback, use } from "react";
import { useRouter } from "next/navigation";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Skeleton } from "@/components/ui/skeleton";
import { JobStatusBadge } from "@/components/job-columns";
import { JsonViewer } from "@/components/json-viewer";
import { StackTrace } from "@/components/stack-trace";
import { apiGet, apiPost, apiDelete } from "@/lib/api";
import {
  JobDetailSchema,
  RetryResponseSchema,
  DeleteResponseSchema,
  type JobDetail,
} from "@/lib/schemas";
import { formatMs, formatTime } from "@/lib/utils";
import { ArrowLeft, Copy, Loader2 } from "lucide-react";

export default function JobDetailPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = use(params);
  const router = useRouter();
  const [job, setJob] = useState<JobDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [retrying, setRetrying] = useState(false);
  const [retrySuccess, setRetrySuccess] = useState(false);
  const [confirmDelete, setConfirmDelete] = useState(false);

  const fetchJob = useCallback(async () => {
    try {
      const raw = await apiGet<{ data: unknown }>(`/jobs/${id}`);
      setJob(JobDetailSchema.parse(raw.data));
    } finally {
      setLoading(false);
    }
  }, [id]);

  useEffect(() => {
    void fetchJob();
  }, [fetchJob]);

  const handleRetry = async () => {
    setRetrying(true);
    try {
      const raw = await apiPost<unknown>(`/jobs/${id}/retry`);
      RetryResponseSchema.parse(raw);
      setRetrySuccess(true);
      void fetchJob();
    } catch {
      // silently ignore
    } finally {
      setRetrying(false);
    }
  };

  const handleDelete = async () => {
    setConfirmDelete(false);
    try {
      const raw = await apiDelete<unknown>(`/jobs/${id}`);
      DeleteResponseSchema.parse(raw);
      router.push("/jobs/failed");
    } catch {
      // silently ignore
    }
  };

  const copyToClipboard = async (text: string) => {
    await navigator.clipboard.writeText(text);
  };

  if (loading) {
    return (
      <div className="space-y-4">
        <Skeleton className="h-8 w-48 rounded-lg" />
        <Skeleton className="h-40 w-full rounded-lg" />
        <Skeleton className="h-60 w-full rounded-lg" />
      </div>
    );
  }

  if (!job) {
    return (
      <div className="flex flex-col items-center justify-center gap-4 py-20">
        <p className="text-sm text-muted-foreground">Job not found</p>
        <Button size="sm" variant="outline" onClick={() => router.back()}>
          Go Back
        </Button>
      </div>
    );
  }

  const isFailed = job.status === "failed";

  return (
    <div className="max-w-4xl space-y-6">
      {/* Header */}
      <div className="flex items-start justify-between">
        <div className="space-y-2">
          <div className="flex items-center gap-3">
            <Button size="sm" variant="outline" onClick={() => router.back()}>
              <ArrowLeft className="mr-1 h-4 w-4" /> Back
            </Button>
            <h2 className="font-mono text-xl font-semibold">{job.class}</h2>
          </div>
          <div className="flex items-center gap-2">
            <JobStatusBadge status={job.status} />
            <Badge variant="outline">{job.queue}</Badge>
          </div>
        </div>
        {isFailed && (
          <div className="flex gap-2">
            <Button
              size="sm"
              onClick={() => void handleRetry()}
              disabled={retrying}
            >
              {retrying && <Loader2 className="mr-1 h-3 w-3 animate-spin" />}
              Retry
            </Button>
            <Button
              size="sm"
              variant="destructive"
              onClick={() => setConfirmDelete(true)}
            >
              Delete
            </Button>
          </div>
        )}
      </div>

      {/* Metadata */}
      <Card>
        <CardHeader>
          <CardTitle className="text-sm">Job Details</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-2 gap-4 text-sm md:grid-cols-3">
            <div>
              <p className="text-xs text-muted-foreground">Job ID</p>
              <div className="flex items-center gap-1">
                <p className="truncate font-mono text-xs">{job.id}</p>
                <Button
                  variant="ghost"
                  size="icon"
                  className="h-6 w-6"
                  onClick={() => void copyToClipboard(job.id)}
                  aria-label="Copy job ID"
                >
                  <Copy className="h-3 w-3" />
                </Button>
              </div>
            </div>
            {job.connection && (
              <div>
                <p className="text-xs text-muted-foreground">Connection</p>
                <p>{job.connection}</p>
              </div>
            )}
            <div>
              <p className="text-xs text-muted-foreground">Attempts</p>
              <p>
                {job.attempts}
                {job.max_attempts !== undefined && ` / ${job.max_attempts}`}
              </p>
            </div>
            {job.timeout !== undefined && (
              <div>
                <p className="text-xs text-muted-foreground">Timeout</p>
                <p>{job.timeout}s</p>
              </div>
            )}
            <div>
              <p className="text-xs text-muted-foreground">Runtime</p>
              <p className="font-mono">{formatMs(job.runtime_ms)}</p>
            </div>
            <div>
              <p className="text-xs text-muted-foreground">Created</p>
              <p className="text-xs">{formatTime(job.created_at)}</p>
            </div>
            {job.completed_at && (
              <div>
                <p className="text-xs text-muted-foreground">Completed</p>
                <p className="text-xs">{formatTime(job.completed_at)}</p>
              </div>
            )}
            {job.failed_at && (
              <div>
                <p className="text-xs text-muted-foreground">Failed</p>
                <p className="text-xs text-destructive">
                  {formatTime(job.failed_at)}
                </p>
              </div>
            )}
          </div>
        </CardContent>
      </Card>

      {/* Payload */}
      <Card>
        <CardHeader className="flex flex-row items-center justify-between">
          <CardTitle className="text-sm">Payload</CardTitle>
          {job.payload != null && (
            <Button
              size="sm"
              variant="outline"
              onClick={() =>
                void copyToClipboard(JSON.stringify(job.payload, null, 2))
              }
            >
              <Copy className="mr-1 h-3 w-3" /> Copy
            </Button>
          )}
        </CardHeader>
        <CardContent>
          <JsonViewer data={job.payload} />
        </CardContent>
      </Card>

      {/* Exception */}
      {isFailed && (job.exception_class || job.exception_message) && (
        <Card>
          <CardHeader>
            <CardTitle className="text-sm text-destructive">Exception</CardTitle>
          </CardHeader>
          <CardContent>
            <StackTrace
              exceptionClass={job.exception_class}
              exceptionMessage={job.exception_message}
              trace={job.exception_trace}
            />
          </CardContent>
        </Card>
      )}

      {/* Retry success */}
      {retrySuccess && (
        <p className="text-sm text-emerald-600 dark:text-emerald-400">
          Job has been re-enqueued for processing.
        </p>
      )}

      {/* Delete confirmation dialog */}
      {confirmDelete && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
          <div className="w-full max-w-md rounded-xl border bg-card p-6 shadow-lg">
            <h3 className="text-lg font-semibold">Delete Failed Job</h3>
            <p className="mt-2 text-sm text-muted-foreground">
              Are you sure you want to permanently delete this failed job? This
              action cannot be undone.
            </p>
            <div className="mt-4 flex justify-end gap-2">
              <Button
                variant="outline"
                size="sm"
                onClick={() => setConfirmDelete(false)}
              >
                Cancel
              </Button>
              <Button
                variant="destructive"
                size="sm"
                onClick={() => void handleDelete()}
              >
                Delete
              </Button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
