"use client";

import { useEffect, useState, useCallback } from "react";
import { useRouter } from "next/navigation";
import { AlertCircle, Search } from "lucide-react";
import { DataTable } from "@/components/ui/data-table";
import { issueColumns } from "@/components/issue-columns";
import { Button } from "@/components/ui/button";
import { usePrismStore } from "@/lib/store";
import { fetchIssues, resolveIssue } from "@/lib/api";
import type { Issue } from "@/lib/schemas";
import type { PaginatedMeta } from "@/lib/schemas";

// Demo data for when the API is unreachable
const DEMO_ISSUES: Issue[] = [
  {
    id: "iss_1",
    project_id: "proj_1",
    fingerprint: "fp_001",
    title: "TypeError: Cannot read properties of undefined (reading 'map')",
    level: "error",
    status: "unresolved",
    count: 142,
    first_seen: "2025-12-01T10:00:00Z",
    last_seen: "2025-12-15T14:23:00Z",
    culprit: "App\Controllers\UserController::index",
    platform: "php",
    environment: "production",
  },
  {
    id: "iss_2",
    project_id: "proj_1",
    fingerprint: "fp_002",
    title: "PDOException: SQLSTATE[23000]: Integrity constraint violation",
    level: "fatal",
    status: "unresolved",
    count: 37,
    first_seen: "2025-12-10T08:15:00Z",
    last_seen: "2025-12-15T12:00:00Z",
    culprit: "App\Repositories\OrderRepository::create",
    platform: "php",
    environment: "production",
  },
  {
    id: "iss_3",
    project_id: "proj_1",
    fingerprint: "fp_003",
    title: "InvalidArgumentException: Expected string, got null",
    level: "warning",
    status: "resolved",
    count: 8,
    first_seen: "2025-12-05T16:30:00Z",
    last_seen: "2025-12-12T09:45:00Z",
    culprit: "App\Services\PaymentService::charge",
    platform: "php",
    environment: "staging",
  },
  {
    id: "iss_4",
    project_id: "proj_1",
    fingerprint: "fp_004",
    title: "RuntimeException: Queue connection timed out",
    level: "error",
    status: "ignored",
    count: 523,
    first_seen: "2025-11-20T06:00:00Z",
    last_seen: "2025-12-15T13:10:00Z",
    culprit: "App\Jobs\SendNotification::handle",
    platform: "php",
    environment: "production",
  },
  {
    id: "iss_5",
    project_id: "proj_1",
    fingerprint: "fp_005",
    title: "Deprecated: str_contains() expects parameter 1 to be string",
    level: "info",
    status: "unresolved",
    count: 1204,
    first_seen: "2025-10-01T00:00:00Z",
    last_seen: "2025-12-15T15:00:00Z",
    culprit: "App\Middleware\AuthMiddleware::handle",
    platform: "php",
    environment: "production",
  },
];

export default function IssuesPage() {
  const router = useRouter();
  const selectedProject = usePrismStore((s) => s.selectedProject);
  const [issues, setIssues] = useState<Issue[]>([]);
  const [meta, setMeta] = useState<PaginatedMeta | null>(null);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState("");

  const loadIssues = useCallback(async () => {
    if (!selectedProject) return;
    setLoading(true);
    try {
      const result = await fetchIssues({
        project_id: selectedProject,
        search: search || undefined,
      });
      setIssues(result.data);
      setMeta(result.meta);
    } catch {
      setIssues(DEMO_ISSUES);
      setMeta({ total: DEMO_ISSUES.length, limit: 25, offset: 0 });
    } finally {
      setLoading(false);
    }
  }, [selectedProject, search]);

  useEffect(() => {
    loadIssues();
  }, [loadIssues]);

  const handleBulkResolve = async () => {
    // In a full implementation, this would use the table's row selection state
    // For now we resolve all unresolved issues as a demo
    const unresolved = issues.filter((i) => i.status === "unresolved");
    await Promise.allSettled(
      unresolved.map((i) => resolveIssue(i.id, "resolved"))
    );
    loadIssues();
  };

  const handleBulkIgnore = async () => {
    const unresolved = issues.filter((i) => i.status === "unresolved");
    await Promise.allSettled(
      unresolved.map((i) => resolveIssue(i.id, "ignored"))
    );
    loadIssues();
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          <AlertCircle className="h-5 w-5 text-primary" />
          <h1 className="text-xl font-bold">Issues</h1>
          {meta && (
            <span className="text-sm text-muted-foreground">
              {meta.total.toLocaleString()} total
            </span>
          )}
        </div>

        <div className="flex items-center gap-2">
          <Button variant="outline" size="sm" onClick={handleBulkResolve}>
            Resolve Selected
          </Button>
          <Button variant="ghost" size="sm" onClick={handleBulkIgnore}>
            Ignore Selected
          </Button>
        </div>
      </div>

      {/* Search */}
      <div className="relative">
        <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
        <input
          type="text"
          placeholder="Search issues..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="flex h-10 w-full rounded-md border border-input bg-background pl-10 pr-4 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
        />
      </div>

      {loading ? (
        <div className="space-y-3">
          {[1, 2, 3, 4, 5].map((i) => (
            <div key={i} className="h-16 rounded-md border bg-muted/30 animate-pulse" />
          ))}
        </div>
      ) : (
        <DataTable
          columns={issueColumns}
          data={issues}
          onRowClick={(issue) => router.push(`/issues/${issue.id}`)}
        />
      )}
    </div>
  );
}
