"use client";

import { useEffect, useState, useCallback } from "react";
import { useRouter } from "next/navigation";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Button } from "@/components/ui/button";
import { DataTable } from "@/components/data-table";
import { recentJobColumns } from "@/components/job-columns";
import { useLoomStore } from "@/lib/store";
import { apiGet } from "@/lib/api";
import {
  PaginatedJobsResponseSchema,
  StatsSchema,
  type Job,
} from "@/lib/schemas";
import { Loader2, ChevronLeft, ChevronRight } from "lucide-react";

export default function RecentJobsPage() {
  const router = useRouter();
  const searchTerm = useLoomStore((s) => s.searchTerm);
  const setSearchTerm = useLoomStore((s) => s.setSearchTerm);
  const selectedQueue = useLoomStore((s) => s.selectedQueue);
  const setSelectedQueue = useLoomStore((s) => s.setSelectedQueue);
  const refreshInterval = useLoomStore((s) => s.refreshInterval);

  const [page, setPage] = useState(1);
  const perPage = 25;
  const [jobs, setJobs] = useState<Job[]>([]);
  const [queues, setQueues] = useState<string[]>([]);
  const [loading, setLoading] = useState(true);
  const [fetching, setFetching] = useState(false);

  const fetchJobs = useCallback(async () => {
    setFetching(true);
    try {
      const raw = await apiGet<unknown>("/jobs/recent", {
        page,
        per_page: perPage,
        queue: selectedQueue,
        search: searchTerm || null,
      });
      const parsed = PaginatedJobsResponseSchema.parse(raw);
      setJobs(parsed.data);
    } catch {
      // silently ignore
    } finally {
      setLoading(false);
      setFetching(false);
    }
  }, [page, selectedQueue, searchTerm]);

  const fetchQueues = useCallback(async () => {
    try {
      const raw = await apiGet<unknown>("/stats", { period: "1h" });
      const parsed = StatsSchema.parse(raw);
      setQueues(Object.keys(parsed.queue_sizes));
    } catch {
      // silently ignore
    }
  }, []);

  useEffect(() => {
    void fetchJobs();
  }, [fetchJobs]);

  useEffect(() => {
    void fetchQueues();
  }, [fetchQueues]);

  useEffect(() => {
    if (!refreshInterval) return;
    const id = setInterval(() => {
      void fetchJobs();
    }, refreshInterval);
    return () => clearInterval(id);
  }, [refreshInterval, fetchJobs]);

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h2 className="text-xl font-semibold">Recent Jobs</h2>
        {fetching && !loading && (
          <Loader2 className="h-4 w-4 animate-spin text-muted-foreground" />
        )}
      </div>

      {/* Filters */}
      <div className="flex flex-wrap gap-3">
        <Input
          placeholder="Search job class..."
          value={searchTerm}
          onChange={(e) => {
            setSearchTerm(e.target.value);
            setPage(1);
          }}
          className="w-64"
        />
        <Select
          value={selectedQueue ?? ""}
          onChange={(e) => {
            setSelectedQueue(e.target.value || null);
            setPage(1);
          }}
          className="w-48"
          aria-label="Filter by queue"
        >
          <option value="">All queues</option>
          {queues.map((q) => (
            <option key={q} value={q}>
              {q}
            </option>
          ))}
        </Select>
      </div>

      {/* Table */}
      <DataTable
        columns={recentJobColumns}
        data={jobs}
        loading={loading}
        emptyMessage="No jobs found"
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
    </div>
  );
}
