"use client";

import { useRouter } from "next/navigation";
import { useWorkflows, useWorkflowStats } from "@/lib/api";
import { useStore } from "@/lib/store";
import { DataTable } from "@/components/data/data-table";
import { workflowColumns } from "@/components/workflow-columns";
import { SearchInput } from "@/components/controls/search-input";
import { StatCard } from "@/components/dashboard/stat-card";
import { ErrorState } from "@/components/feedback/error-state";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Skeleton } from "@/components/ui/skeleton";
import { formatDuration } from "@/lib/formatters";
import { Activity, CheckCircle2, XCircle, Clock, X } from "lucide-react";
import type { WorkflowSummary } from "@/lib/schemas";

const STATUS_OPTIONS = [
  { key: "running", label: "Running" },
  { key: "completed", label: "Completed" },
  { key: "failed", label: "Failed" },
  { key: "cancelled", label: "Cancelled" },
  { key: "terminated", label: "Terminated" },
  { key: "timed_out", label: "Timed Out" },
];

function StatsBar() {
  const { data: statsResponse, isLoading } = useWorkflowStats();

  if (isLoading || !statsResponse) {
    return (
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        {Array.from({ length: 4 }).map((_, i) => (
          <StatCard key={i} title="" value="" loading />
        ))}
      </div>
    );
  }

  const stats = statsResponse.data;

  return (
    <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
      <StatCard title="Running" value={stats.running} icon={Activity} />
      <StatCard title="Completed" value={stats.completed} icon={CheckCircle2} />
      <StatCard title="Failed" value={stats.failed} icon={XCircle} />
      <StatCard title="Avg Duration" value={formatDuration(stats.avg_duration_ms)} icon={Clock} />
    </div>
  );
}

export default function WorkflowsPage() {
  const router = useRouter();
  const {
    statusFilter,
    search,
    sort,
    order,
    page,
    perPage,
    setStatusFilter,
    setSearch,
    setPage,
    setPerPage,
    resetFilters,
  } = useStore();

  const { data, isLoading, isError, error, refetch } = useWorkflows({
    status: statusFilter.length > 0 ? statusFilter.join(",") : undefined,
    search: search || undefined,
    sort,
    order,
    page,
    per_page: perPage,
  });

  const handleRowClick = (row: WorkflowSummary) => {
    router.push(`/workflows/${row.id}`);
  };

  const handleStatusToggle = (status: string) => {
    if (statusFilter.includes(status)) {
      setStatusFilter(statusFilter.filter((s) => s !== status));
    } else {
      setStatusFilter([...statusFilter, status]);
    }
  };

  const totalPages = data
    ? Math.max(1, Math.ceil(data.meta.total / data.meta.per_page))
    : 1;
  const hasActiveFilters = statusFilter.length > 0 || search;

  if (isError) {
    return (
      <ErrorState
        title="Failed to load workflows"
        message={
          error instanceof Error ? error.message : "An unexpected error occurred"
        }
        retry={() => void refetch()}
      />
    );
  }

  return (
    <div className="flex flex-col gap-6">
      <div>
        <h2 className="text-2xl font-bold tracking-tight">Workflows</h2>
        <p className="text-sm text-muted-foreground mt-1">
          Monitor and manage workflow executions
        </p>
      </div>

      <StatsBar />

      {/* Filters */}
      <div className="flex flex-col sm:flex-row gap-3">
        <SearchInput
          value={search}
          onChange={setSearch}
          placeholder="Search by workflow ID..."
          className="sm:max-w-xs"
        />

        <Select
          value={statusFilter.length === 1 ? statusFilter[0] : ""}
          onValueChange={(value) => handleStatusToggle(value)}
        >
          <SelectTrigger className="sm:max-w-[180px]">
            <SelectValue placeholder="Filter by status" />
          </SelectTrigger>
          <SelectContent>
            {STATUS_OPTIONS.map((opt) => (
              <SelectItem key={opt.key} value={opt.key}>
                {opt.label}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>

        <Select
          value={String(perPage)}
          onValueChange={(val) => setPerPage(Number(val))}
        >
          <SelectTrigger className="sm:max-w-[100px]">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="10">10</SelectItem>
            <SelectItem value="20">20</SelectItem>
            <SelectItem value="50">50</SelectItem>
          </SelectContent>
        </Select>

        {hasActiveFilters && (
          <Button variant="outline" size="sm" onClick={resetFilters} className="self-center">
            Clear Filters
          </Button>
        )}
      </div>

      {/* Active filter badges */}
      {hasActiveFilters && (
        <div className="flex gap-2 flex-wrap">
          {statusFilter.map((s) => (
            <Badge
              key={s}
              variant="secondary"
              className="cursor-pointer gap-1"
              onClick={() => setStatusFilter(statusFilter.filter((f) => f !== s))}
            >
              {s}
              <X className="h-3 w-3" />
            </Badge>
          ))}
          {search && (
            <Badge
              variant="secondary"
              className="cursor-pointer gap-1"
              onClick={() => setSearch("")}
            >
              Search: {search}
              <X className="h-3 w-3" />
            </Badge>
          )}
        </div>
      )}

      {/* Table */}
      <DataTable
        columns={workflowColumns}
        data={data?.data ?? []}
        loading={isLoading}
        emptyMessage="No workflows found"
        onRowClick={handleRowClick}
      />

      {/* Pagination */}
      {data && totalPages > 1 && (
        <div className="flex items-center justify-between">
          <span className="text-sm text-muted-foreground">
            Showing {(page - 1) * perPage + 1} -{" "}
            {Math.min(page * perPage, data.meta.total)} of {data.meta.total}
          </span>
          <div className="flex gap-2">
            <Button
              variant="outline"
              size="sm"
              disabled={page <= 1}
              onClick={() => setPage(page - 1)}
            >
              Previous
            </Button>
            <Button
              variant="outline"
              size="sm"
              disabled={page >= totalPages}
              onClick={() => setPage(page + 1)}
            >
              Next
            </Button>
          </div>
        </div>
      )}
    </div>
  );
}
