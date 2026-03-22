"use client";

import { useEffect, useState, useCallback } from "react";
import { useRouter } from "next/navigation";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { DataTable } from "@/components/data-table";
import { failedJobColumns } from "@/components/job-columns";
import { useLoomStore } from "@/lib/store";
import { apiGet, apiPost, apiDelete } from "@/lib/api";
import {
  PaginatedFailedJobsResponseSchema,
  RetryResponseSchema,
  RetryAllResponseSchema,
  DeleteResponseSchema,
  type FailedJob,
} from "@/lib/schemas";
import { Loader2, ChevronLeft, ChevronRight } from "lucide-react";

export default function FailedJobsPage() {
  const router = useRouter();
  const searchTerm = useLoomStore((s) => s.searchTerm);
  const setSearchTerm = useLoomStore((s) => s.setSearchTerm);
  const refreshInterval = useLoomStore((s) => s.refreshInterval);

  const [page, setPage] = useState(1);
  const perPage = 25;
  const [jobs, setJobs] = useState<FailedJob[]>([]);
  const [loading, setLoading] = useState(true);
  const [fetching, setFetching] = useState(false);
  const [retryingId, setRetryingId] = useState<string | null>(null);
  const [retryingAll, setRetryingAll] = useState(false);
  const [confirmRetryAll, setConfirmRetryAll] = useState(false);
  const [confirmDeleteId, setConfirmDeleteId] = useState<string | null>(null);

  const fetchJobs = useCallback(async () => {
    setFetching(true);
    try {
      const raw = await apiGet<unknown>("/jobs/failed", {
        page,
        per_page: perPage,
        search: searchTerm || null,
      });
      const parsed = PaginatedFailedJobsResponseSchema.parse(raw);
      setJobs(parsed.data);
    } catch {
      // silently ignore
    } finally {
      setLoading(false);
      setFetching(false);
    }
  }, [page, searchTerm]);

  useEffect(() => {
    void fetchJobs();
  }, [fetchJobs]);

  useEffect(() => {
    if (!refreshInterval) return;
    const id = setInterval(() => void fetchJobs(), refreshInterval);
    return () => clearInterval(id);
  }, [refreshInterval, fetchJobs]);

  const handleRetry = async (jobId: string) => {
    setRetryingId(jobId);
    try {
      const raw = await apiPost<unknown>(`/jobs/${jobId}/retry`);
      RetryResponseSchema.parse(raw);
      void fetchJobs();
    } catch {
      // silently ignore
    } finally {
      setRetryingId(null);
    }
  };

  const handleRetryAll = async () => {
    setRetryingAll(true);
    setConfirmRetryAll(false);
    try {
      const raw = await apiPost<unknown>("/jobs/retry-all");
      RetryAllResponseSchema.parse(raw);
      void fetchJobs();
    } catch {
      // silently ignore
    } finally {
      setRetryingAll(false);
    }
  };

  const handleDelete = async (jobId: string) => {
    setConfirmDeleteId(null);
    try {
      const raw = await apiDelete<unknown>(`/jobs/${jobId}`);
      DeleteResponseSchema.parse(raw);
      void fetchJobs();
    } catch {
      // silently ignore
    }
  };

  const columns = failedJobColumns({
    onRetry: (id) => void handleRetry(id),
    onDelete: (id) => setConfirmDeleteId(id),
    retryingId,
  });

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h2 className="text-xl font-semibold">Failed Jobs</h2>
        <div className="flex items-center gap-2">
          {fetching && !loading && (
            <Loader2 className="h-4 w-4 animate-spin text-muted-foreground" />
          )}
          {jobs.length > 0 && (
            <Button
              size="sm"
              variant="outline"
              disabled={retryingAll}
              onClick={() => setConfirmRetryAll(true)}
            >
              {retryingAll ? (
                <Loader2 className="mr-1 h-3 w-3 animate-spin" />
              ) : null}
              Retry All
            </Button>
          )}
        </div>
      </div>

      {/* Search */}
      <Input
        placeholder="Search class or exception..."
        value={searchTerm}
        onChange={(e) => {
          setSearchTerm(e.target.value);
          setPage(1);
        }}
        className="w-80"
      />

      {/* Table */}
      <DataTable
        columns={columns}
        data={jobs}
        loading={loading}
        emptyMessage="No failed jobs"
        onRowClick={(job) => router.push(`/jobs/${job.id}`)}
        rowKey={(job) => job.id}
      />

      {/* Pagination */}
      {jobs.length > 0 && (
        <div className="flex items-center justify-center gap-2 pt-2">
          <Button
            size="sm"
            variant="outline"
            disabled={page <= 1}
            onClick={() => setPage((p) => Math.max(1, p - 1))}
          >
            <ChevronLeft className="h-4 w-4" />
          </Button>
          <span className="text-sm text-muted-foreground">Page {page}</span>
          <Button
            size="sm"
            variant="outline"
            disabled={jobs.length < perPage}
            onClick={() => setPage((p) => p + 1)}
          >
            <ChevronRight className="h-4 w-4" />
          </Button>
        </div>
      )}

      {/* Retry All confirmation dialog */}
      {confirmRetryAll && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
          <div className="w-full max-w-md rounded-xl border bg-card p-6 shadow-lg">
            <h3 className="text-lg font-semibold">Retry All Failed Jobs</h3>
            <p className="mt-2 text-sm text-muted-foreground">
              Are you sure you want to retry all failed jobs? This will
              re-enqueue every failed job back into its original queue.
            </p>
            <div className="mt-4 flex justify-end gap-2">
              <Button
                variant="outline"
                size="sm"
                onClick={() => setConfirmRetryAll(false)}
              >
                Cancel
              </Button>
              <Button size="sm" onClick={() => void handleRetryAll()}>
                Retry All
              </Button>
            </div>
          </div>
        </div>
      )}

      {/* Delete confirmation dialog */}
      {confirmDeleteId && (
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
                onClick={() => setConfirmDeleteId(null)}
              >
                Cancel
              </Button>
              <Button
                variant="destructive"
                size="sm"
                onClick={() => void handleDelete(confirmDeleteId)}
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
