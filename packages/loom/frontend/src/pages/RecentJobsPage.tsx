import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  Table,
  TableHeader,
  TableColumn,
  TableBody,
  TableRow,
  TableCell,
  Pagination,
  Input,
  Select,
  SelectItem,
  Skeleton,
  Spinner,
} from '@nextui-org/react';
import { useRecentJobs } from '@/api/jobs';
import { useDashboardStats } from '@/api/stats';
import { useFiltersStore } from '@/stores/filters';
import JobStatusBadge from '@/components/JobStatusBadge';

function formatMs(ms: number | null | undefined): string {
  if (ms === null || ms === undefined) return '--';
  if (ms >= 1000) return `${(ms / 1000).toFixed(2)}s`;
  return `${ms.toFixed(0)}ms`;
}

function formatTime(iso: string | null | undefined): string {
  if (!iso) return '--';
  return new Date(iso).toLocaleString();
}

export default function RecentJobsPage() {
  const navigate = useNavigate();
  const [page, setPage] = useState(1);
  const [perPage] = useState(25);

  const searchTerm = useFiltersStore((s) => s.searchTerm);
  const setSearchTerm = useFiltersStore((s) => s.setSearchTerm);
  const selectedQueue = useFiltersStore((s) => s.selectedQueue);
  const setSelectedQueue = useFiltersStore((s) => s.setSelectedQueue);

  const { data, isLoading, isFetching } = useRecentJobs(page, perPage);
  const { data: stats } = useDashboardStats();

  const queues = stats?.queue_sizes ? Object.keys(stats.queue_sizes) : [];

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h2 className="text-xl font-semibold">Recent Jobs</h2>
        {isFetching && !isLoading && <Spinner size="sm" />}
      </div>

      {/* Filters */}
      <div className="flex flex-wrap gap-3">
        <Input
          size="sm"
          variant="bordered"
          placeholder="Search job class..."
          value={searchTerm}
          onValueChange={(val) => {
            setSearchTerm(val);
            setPage(1);
          }}
          className="w-64"
          isClearable
          onClear={() => {
            setSearchTerm('');
            setPage(1);
          }}
        />
        <Select
          size="sm"
          variant="bordered"
          placeholder="All queues"
          selectedKeys={selectedQueue ? [selectedQueue] : []}
          onSelectionChange={(keys) => {
            const selected = Array.from(keys)[0] as string | undefined;
            setSelectedQueue(selected ?? null);
            setPage(1);
          }}
          className="w-48"
          aria-label="Filter by queue"
        >
          {queues.map((q) => (
            <SelectItem key={q}>{q}</SelectItem>
          ))}
        </Select>
      </div>

      {/* Table */}
      {isLoading ? (
        <div className="space-y-3">
          {Array.from({ length: 5 }).map((_, i) => (
            <Skeleton key={i} className="w-full h-12 rounded-lg" />
          ))}
        </div>
      ) : (
        <>
          <Table
            aria-label="Recent jobs table"
            selectionMode="single"
            onRowAction={(key) => navigate(`/jobs/${key}`)}
            classNames={{
              tr: 'cursor-pointer hover:bg-default-100',
            }}
          >
            <TableHeader>
              <TableColumn>Status</TableColumn>
              <TableColumn>Job Class</TableColumn>
              <TableColumn>Queue</TableColumn>
              <TableColumn>Runtime</TableColumn>
              <TableColumn>Attempts</TableColumn>
              <TableColumn>Created</TableColumn>
            </TableHeader>
            <TableBody
              items={data?.data ?? []}
              emptyContent="No jobs found"
            >
              {(job) => (
                <TableRow key={job.id}>
                  <TableCell>
                    <JobStatusBadge status={job.status} />
                  </TableCell>
                  <TableCell>
                    <span className="font-mono text-sm">{job.class}</span>
                  </TableCell>
                  <TableCell>
                    <span className="text-sm text-default-500">{job.queue}</span>
                  </TableCell>
                  <TableCell>
                    <span className="text-sm font-mono">{formatMs(job.runtime_ms)}</span>
                  </TableCell>
                  <TableCell>
                    <span className="text-sm">{job.attempts}</span>
                  </TableCell>
                  <TableCell>
                    <span className="text-xs text-default-500">{formatTime(job.created_at)}</span>
                  </TableCell>
                </TableRow>
              )}
            </TableBody>
          </Table>

          {/* Pagination */}
          {data && data.data.length > 0 && (
            <div className="flex justify-center pt-2">
              <Pagination
                total={Math.max(1, Math.ceil(100 / perPage))}
                page={page}
                onChange={setPage}
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
