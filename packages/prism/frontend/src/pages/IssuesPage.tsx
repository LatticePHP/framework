import { useCallback, useEffect, useRef, useState } from "react";
import { useNavigate } from "react-router-dom";
import {
  Table,
  TableHeader,
  TableColumn,
  TableBody,
  TableRow,
  TableCell,
  Input,
  Select,
  SelectItem,
  Button,
  Checkbox,
  Pagination,
  Skeleton,
  Card,
  CardBody,
  Chip,
} from "@nextui-org/react";
import { useIssues } from "@/api/issues";
import { useResolveIssue, useIgnoreIssue } from "@/api/mutations";
import { useProjectStore } from "@/stores/project";
import { IssueBadge } from "@/components/IssueBadge";
import { StatusChip } from "@/components/StatusChip";
import { EventCount } from "@/components/EventCount";
import { TimeAgo } from "@/components/TimeAgo";

const STATUS_OPTIONS = [
  { key: "", label: "All statuses" },
  { key: "unresolved", label: "Unresolved" },
  { key: "resolved", label: "Resolved" },
  { key: "ignored", label: "Ignored" },
];

const LEVEL_OPTIONS = [
  { key: "", label: "All levels" },
  { key: "fatal", label: "Fatal" },
  { key: "error", label: "Error" },
  { key: "warning", label: "Warning" },
  { key: "info", label: "Info" },
];

const SORT_OPTIONS = [
  { key: "last_seen", label: "Last seen" },
  { key: "first_seen", label: "First seen" },
  { key: "count", label: "Event count" },
];

export function IssuesPage() {
  const navigate = useNavigate();
  const selectedProjectId = useProjectStore((s) => s.selectedProjectId);
  const filters = useProjectStore((s) => s.filters);
  const setFilter = useProjectStore((s) => s.setFilter);
  const page = useProjectStore((s) => s.page);
  const pageSize = useProjectStore((s) => s.pageSize);
  const setPage = useProjectStore((s) => s.setPage);
  const selectedIssueIds = useProjectStore((s) => s.selectedIssueIds);
  const toggleIssueSelection = useProjectStore((s) => s.toggleIssueSelection);
  const selectAllIssues = useProjectStore((s) => s.selectAllIssues);
  const clearSelection = useProjectStore((s) => s.clearSelection);

  // Debounced search
  const [searchInput, setSearchInput] = useState(filters.search);
  const debounceRef = useRef<ReturnType<typeof setTimeout>>();

  useEffect(() => {
    debounceRef.current = setTimeout(() => {
      setFilter("search", searchInput);
    }, 300);
    return () => clearTimeout(debounceRef.current);
  }, [searchInput, setFilter]);

  const { data, isLoading, error } = useIssues(selectedProjectId, {
    status: filters.status || undefined,
    level: filters.level || undefined,
    environment: filters.environment || undefined,
    search: filters.search || undefined,
    sort: filters.sort,
    dir: filters.dir,
    limit: pageSize,
    offset: page * pageSize,
  });

  const resolveMutation = useResolveIssue();
  const ignoreMutation = useIgnoreIssue();

  const totalPages = data ? Math.ceil(data.meta.total / data.meta.limit) : 0;

  const handleBulkResolve = useCallback(() => {
    for (const id of selectedIssueIds) {
      resolveMutation.mutate(id);
    }
    clearSelection();
  }, [selectedIssueIds, resolveMutation, clearSelection]);

  const handleBulkIgnore = useCallback(() => {
    for (const id of selectedIssueIds) {
      ignoreMutation.mutate(id);
    }
    clearSelection();
  }, [selectedIssueIds, ignoreMutation, clearSelection]);

  const allSelected =
    data && data.issues.length > 0 && selectedIssueIds.size === data.issues.length;

  // No project selected
  if (!selectedProjectId) {
    return (
      <div className="p-6">
        <h2 className="text-2xl font-bold mb-4">Issues</h2>
        <Card>
          <CardBody className="text-center py-16">
            <h3 className="text-lg font-semibold mb-2">
              Select a project first
            </h3>
            <p className="text-sm text-default-400 mb-4">
              Use the project selector in the sidebar to pick a project, or go
              to the Projects page.
            </p>
            <Button color="primary" variant="flat" onPress={() => navigate("/")}>
              Go to Projects
            </Button>
          </CardBody>
        </Card>
      </div>
    );
  }

  return (
    <div className="p-6">
      {/* Header */}
      <div className="flex items-center justify-between mb-5">
        <h2 className="text-2xl font-bold text-foreground">Issues</h2>
        {data && (
          <span className="text-sm text-default-400">
            {data.meta.total.toLocaleString()} total
          </span>
        )}
      </div>

      {/* Filters bar */}
      <div className="flex flex-wrap items-end gap-3 mb-4">
        <Input
          placeholder="Search issues..."
          value={searchInput}
          onValueChange={setSearchInput}
          variant="bordered"
          size="sm"
          className="w-64"
          isClearable
          onClear={() => {
            setSearchInput("");
            setFilter("search", "");
          }}
        />

        <Select
          label="Status"
          size="sm"
          variant="bordered"
          selectedKeys={[filters.status]}
          onChange={(e) =>
            setFilter("status", e.target.value as typeof filters.status)
          }
          className="w-40"
        >
          {STATUS_OPTIONS.map((opt) => (
            <SelectItem key={opt.key}>{opt.label}</SelectItem>
          ))}
        </Select>

        <Select
          label="Level"
          size="sm"
          variant="bordered"
          selectedKeys={[filters.level]}
          onChange={(e) =>
            setFilter("level", e.target.value as typeof filters.level)
          }
          className="w-36"
        >
          {LEVEL_OPTIONS.map((opt) => (
            <SelectItem key={opt.key}>{opt.label}</SelectItem>
          ))}
        </Select>

        <Select
          label="Sort by"
          size="sm"
          variant="bordered"
          selectedKeys={[filters.sort]}
          onChange={(e) => setFilter("sort", e.target.value)}
          className="w-36"
        >
          {SORT_OPTIONS.map((opt) => (
            <SelectItem key={opt.key}>{opt.label}</SelectItem>
          ))}
        </Select>

        <Button
          size="sm"
          variant="flat"
          onPress={() => {
            useProjectStore.getState().resetFilters();
            setSearchInput("");
          }}
        >
          Reset
        </Button>
      </div>

      {/* Bulk actions */}
      {selectedIssueIds.size > 0 && (
        <div className="flex items-center gap-3 mb-3 p-3 rounded-lg bg-primary-50 dark:bg-primary-500/10 border border-primary-200 dark:border-primary-500/20">
          <Chip size="sm" color="primary" variant="flat">
            {selectedIssueIds.size} selected
          </Chip>
          <Button
            size="sm"
            color="success"
            variant="flat"
            onPress={handleBulkResolve}
            isLoading={resolveMutation.isPending}
          >
            Resolve all
          </Button>
          <Button
            size="sm"
            variant="flat"
            onPress={handleBulkIgnore}
            isLoading={ignoreMutation.isPending}
          >
            Ignore all
          </Button>
          <Button size="sm" variant="light" onPress={clearSelection}>
            Clear
          </Button>
        </div>
      )}

      {/* Error state */}
      {error && (
        <Card className="mb-4">
          <CardBody className="text-center py-8">
            <p className="text-danger mb-1">Failed to load issues</p>
            <p className="text-sm text-default-400">
              {error instanceof Error ? error.message : "Unknown error"}
            </p>
          </CardBody>
        </Card>
      )}

      {/* Loading */}
      {isLoading && (
        <div className="space-y-2">
          {Array.from({ length: 5 }).map((_, i) => (
            <Skeleton key={i} className="h-14 rounded-lg" />
          ))}
        </div>
      )}

      {/* Table */}
      {data && !isLoading && (
        <>
          {data.issues.length === 0 ? (
            <Card>
              <CardBody className="text-center py-16">
                <h3 className="text-lg font-semibold mb-2">No issues found</h3>
                <p className="text-sm text-default-400">
                  {filters.search || filters.status || filters.level
                    ? "Try adjusting your filters."
                    : "No errors have been reported for this project yet."}
                </p>
              </CardBody>
            </Card>
          ) : (
            <Table
              aria-label="Issues table"
              isStriped
              classNames={{
                wrapper: "rounded-lg border border-default-200",
                th: "bg-default-50 dark:bg-default-50/50",
              }}
            >
              <TableHeader>
                <TableColumn width={40}>
                  <Checkbox
                    isSelected={allSelected}
                    isIndeterminate={
                      selectedIssueIds.size > 0 && !allSelected
                    }
                    onChange={() => {
                      if (allSelected) {
                        clearSelection();
                      } else {
                        selectAllIssues(data.issues.map((i) => i.id));
                      }
                    }}
                    size="sm"
                  />
                </TableColumn>
                <TableColumn>Issue</TableColumn>
                <TableColumn width={90}>Level</TableColumn>
                <TableColumn width={90}>Events</TableColumn>
                <TableColumn width={120}>Last Seen</TableColumn>
                <TableColumn width={110}>Status</TableColumn>
              </TableHeader>
              <TableBody>
                {data.issues.map((issue) => (
                  <TableRow
                    key={issue.id}
                    className="cursor-pointer hover:bg-default-50"
                    onClick={() => navigate(`/issues/${issue.id}`)}
                  >
                    <TableCell onClick={(e) => e.stopPropagation()}>
                      <Checkbox
                        isSelected={selectedIssueIds.has(issue.id)}
                        onChange={() => toggleIssueSelection(issue.id)}
                        size="sm"
                      />
                    </TableCell>
                    <TableCell>
                      <div className="flex flex-col gap-0.5">
                        <span className="font-medium text-sm text-foreground line-clamp-1">
                          {issue.title}
                        </span>
                        {issue.culprit && (
                          <span className="text-xs text-default-400 font-mono line-clamp-1">
                            {issue.culprit}
                          </span>
                        )}
                      </div>
                    </TableCell>
                    <TableCell>
                      <IssueBadge level={issue.level} />
                    </TableCell>
                    <TableCell>
                      <EventCount count={issue.count} />
                    </TableCell>
                    <TableCell>
                      <TimeAgo
                        date={issue.last_seen}
                        className="text-sm text-default-500"
                      />
                    </TableCell>
                    <TableCell>
                      <StatusChip status={issue.status} />
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}

          {/* Pagination */}
          {totalPages > 1 && (
            <div className="flex justify-center mt-4">
              <Pagination
                total={totalPages}
                page={page + 1}
                onChange={(p) => setPage(p - 1)}
                showControls
                size="sm"
              />
            </div>
          )}
        </>
      )}
    </div>
  );
}
