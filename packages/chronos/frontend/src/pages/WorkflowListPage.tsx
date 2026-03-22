import { useCallback } from 'react';
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
  Pagination,
  Spinner,
  Button,
  Chip,
} from '@nextui-org/react';
import { useWorkflows } from '@/api/workflows';
import { useWorkflowStats } from '@/api/stats';
import { useFiltersStore } from '@/stores/filters';
import { StatusBadge } from '@/components/StatusBadge';
import type { WorkflowSummary, WorkflowStatus } from '@/schemas/workflow';

const STATUS_OPTIONS = [
  { key: 'running', label: 'Running' },
  { key: 'completed', label: 'Completed' },
  { key: 'failed', label: 'Failed' },
  { key: 'cancelled', label: 'Cancelled' },
  { key: 'terminated', label: 'Terminated' },
  { key: 'timed_out', label: 'Timed Out' },
];

const PER_PAGE_OPTIONS = [
  { key: '10', label: '10' },
  { key: '20', label: '20' },
  { key: '50', label: '50' },
];

function formatDuration(ms: number | null): string {
  if (ms == null) return '--';
  if (ms < 1000) return `${ms}ms`;
  if (ms < 60000) return `${(ms / 1000).toFixed(1)}s`;
  return `${(ms / 60000).toFixed(1)}m`;
}

function formatTimestamp(ts: string): string {
  return new Date(ts).toLocaleString(undefined, {
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function formatEventType(type: string | null | undefined): string {
  if (!type) return '--';
  return type
    .split('_')
    .map((w) => w.charAt(0).toUpperCase() + w.slice(1))
    .join(' ');
}

function truncateId(id: string): string {
  if (id.length <= 12) return id;
  return `${id.slice(0, 8)}...${id.slice(-4)}`;
}

function SearchIcon() {
  return (
    <svg
      width="16"
      height="16"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
    >
      <circle cx="11" cy="11" r="8" />
      <line x1="21" y1="21" x2="16.65" y2="16.65" />
    </svg>
  );
}

function StatsBar() {
  const { data: statsResponse, isLoading } = useWorkflowStats();

  if (isLoading || !statsResponse) {
    return (
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        {[...Array(4)].map((_, i) => (
          <div
            key={i}
            className="bg-content1 rounded-xl p-4 border border-divider animate-pulse"
          >
            <div className="h-3 w-16 bg-default-200 rounded mb-2" />
            <div className="h-8 w-12 bg-default-200 rounded" />
          </div>
        ))}
      </div>
    );
  }

  const stats = statsResponse.data;

  return (
    <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
      <div className="bg-content1 rounded-xl p-4 border border-divider">
        <p className="text-xs text-default-400 uppercase tracking-wider">Running</p>
        <p className="text-2xl font-bold text-primary mt-1">{stats.running}</p>
      </div>
      <div className="bg-content1 rounded-xl p-4 border border-divider">
        <p className="text-xs text-default-400 uppercase tracking-wider">Completed</p>
        <p className="text-2xl font-bold text-success mt-1">{stats.completed}</p>
      </div>
      <div className="bg-content1 rounded-xl p-4 border border-divider">
        <p className="text-xs text-default-400 uppercase tracking-wider">Failed</p>
        <p className="text-2xl font-bold text-danger mt-1">{stats.failed}</p>
      </div>
      <div className="bg-content1 rounded-xl p-4 border border-divider">
        <p className="text-xs text-default-400 uppercase tracking-wider">Avg Duration</p>
        <p className="text-2xl font-bold mt-1">
          {formatDuration(stats.avg_duration_ms)}
        </p>
      </div>
    </div>
  );
}

export function WorkflowListPage() {
  const {
    statusFilter,
    search,
    sort,
    order,
    page,
    perPage,
    setStatusFilter,
    setSearch,
    setSort,
    setOrder,
    setPage,
    setPerPage,
    resetFilters,
  } = useFiltersStore();

  const { data, isLoading, isError, error } = useWorkflows({
    status: statusFilter.length > 0 ? statusFilter.join(',') : undefined,
    search: search || undefined,
    sort,
    order,
    page,
    per_page: perPage,
  });

  const handleSortChange = useCallback(
    (column: string) => {
      if (sort === column) {
        setOrder(order === 'asc' ? 'desc' : 'asc');
      } else {
        setSort(column);
        setOrder('desc');
      }
    },
    [sort, order, setSort, setOrder],
  );

  const navigateToDetail = useCallback((id: string) => {
    window.location.hash = `/workflows/${id}`;
  }, []);

  const totalPages = data ? Math.max(1, Math.ceil(data.meta.total / data.meta.per_page)) : 1;
  const hasActiveFilters = statusFilter.length > 0 || search;

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <div>
          <h2 className="text-2xl font-bold">Workflows</h2>
          <p className="text-sm text-default-400 mt-1">
            Monitor and manage workflow executions
          </p>
        </div>
      </div>

      <StatsBar />

      {/* Filters */}
      <div className="flex flex-col sm:flex-row gap-3 mb-4">
        <Input
          placeholder="Search by workflow ID..."
          value={search}
          onValueChange={setSearch}
          startContent={<SearchIcon />}
          size="sm"
          className="sm:max-w-xs"
          isClearable
          onClear={() => setSearch('')}
        />

        <Select
          label="Status"
          placeholder="All statuses"
          selectionMode="multiple"
          size="sm"
          className="sm:max-w-xs"
          selectedKeys={new Set(statusFilter)}
          onSelectionChange={(keys) => {
            setStatusFilter(Array.from(keys) as string[]);
          }}
        >
          {STATUS_OPTIONS.map((opt) => (
            <SelectItem key={opt.key}>{opt.label}</SelectItem>
          ))}
        </Select>

        <Select
          label="Per page"
          size="sm"
          className="sm:max-w-[100px]"
          selectedKeys={new Set([String(perPage)])}
          onSelectionChange={(keys) => {
            const val = Array.from(keys)[0];
            if (typeof val === 'string') setPerPage(Number(val));
          }}
        >
          {PER_PAGE_OPTIONS.map((opt) => (
            <SelectItem key={opt.key}>{opt.label}</SelectItem>
          ))}
        </Select>

        {hasActiveFilters && (
          <Button size="sm" variant="flat" onPress={resetFilters} className="self-end">
            Clear Filters
          </Button>
        )}
      </div>

      {/* Active filter chips */}
      {hasActiveFilters && (
        <div className="flex gap-2 mb-4 flex-wrap">
          {statusFilter.map((s) => (
            <Chip
              key={s}
              onClose={() => setStatusFilter(statusFilter.filter((f) => f !== s))}
              variant="flat"
              size="sm"
            >
              {s}
            </Chip>
          ))}
          {search && (
            <Chip onClose={() => setSearch('')} variant="flat" size="sm">
              Search: {search}
            </Chip>
          )}
        </div>
      )}

      {/* Table */}
      <Table
        aria-label="Workflow list"
        isStriped
        isHeaderSticky
        classNames={{
          wrapper: 'max-h-[calc(100vh-420px)]',
        }}
        bottomContent={
          totalPages > 1 ? (
            <div className="flex w-full justify-center">
              <Pagination
                isCompact
                showControls
                showShadow
                color="primary"
                page={page}
                total={totalPages}
                onChange={setPage}
              />
            </div>
          ) : null
        }
      >
        <TableHeader>
          <TableColumn
            key="id"
            allowsSorting
            onClick={() => handleSortChange('id')}
          >
            ID
          </TableColumn>
          <TableColumn key="type" allowsSorting onClick={() => handleSortChange('type')}>
            Type
          </TableColumn>
          <TableColumn
            key="status"
            allowsSorting
            onClick={() => handleSortChange('status')}
          >
            Status
          </TableColumn>
          <TableColumn
            key="started_at"
            allowsSorting
            onClick={() => handleSortChange('started_at')}
          >
            Started
          </TableColumn>
          <TableColumn key="duration">Duration</TableColumn>
          <TableColumn key="last_event">Last Event</TableColumn>
        </TableHeader>
        <TableBody
          items={data?.data ?? []}
          isLoading={isLoading}
          loadingContent={<Spinner label="Loading workflows..." />}
          emptyContent={
            isError ? (
              <div className="text-danger">
                Error: {error instanceof Error ? error.message : 'Failed to load workflows'}
              </div>
            ) : (
              'No workflows found'
            )
          }
        >
          {(item: WorkflowSummary) => (
            <TableRow
              key={item.id}
              className="cursor-pointer hover:bg-default-100 transition-colors"
              onClick={() => navigateToDetail(item.id)}
            >
              <TableCell>
                <span className="font-mono text-xs" title={item.id}>
                  {truncateId(item.id)}
                </span>
              </TableCell>
              <TableCell>
                <span className="text-sm">{item.type}</span>
              </TableCell>
              <TableCell>
                <StatusBadge status={item.status as WorkflowStatus} />
              </TableCell>
              <TableCell>
                <span className="text-sm">{formatTimestamp(item.started_at)}</span>
              </TableCell>
              <TableCell>
                <span className="text-sm font-mono">
                  {formatDuration(item.duration_ms)}
                </span>
              </TableCell>
              <TableCell>
                <span className="text-xs text-default-400">
                  {formatEventType(item.last_event_type)}
                </span>
              </TableCell>
            </TableRow>
          )}
        </TableBody>
      </Table>

      {data && (
        <div className="flex justify-between items-center mt-3 text-xs text-default-400">
          <span>
            Showing {(page - 1) * perPage + 1} -{' '}
            {Math.min(page * perPage, data.meta.total)} of {data.meta.total}
          </span>
          <span>
            {sort} {order === 'asc' ? '\u2191' : '\u2193'}
          </span>
        </div>
      )}
    </div>
  );
}
