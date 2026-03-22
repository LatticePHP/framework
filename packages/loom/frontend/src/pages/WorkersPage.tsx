import { useEffect, useState } from 'react';
import {
  Table,
  TableHeader,
  TableColumn,
  TableBody,
  TableRow,
  TableCell,
  Chip,
  Skeleton,
  Spinner,
} from '@nextui-org/react';
import { useWorkerList } from '@/api/metrics';

const statusColors: Record<string, 'success' | 'default' | 'danger'> = {
  active: 'success',
  inactive: 'default',
  stale: 'danger',
};

function formatUptime(seconds: number): string {
  if (seconds < 60) return `${seconds}s`;
  if (seconds < 3600) return `${Math.floor(seconds / 60)}m ${seconds % 60}s`;
  const hours = Math.floor(seconds / 3600);
  const minutes = Math.floor((seconds % 3600) / 60);
  return `${hours}h ${minutes}m`;
}

function formatMemory(mb: number): string {
  if (mb >= 1024) return `${(mb / 1024).toFixed(1)} GB`;
  return `${mb.toFixed(1)} MB`;
}

function TimeSince({ timestamp }: { timestamp: number }) {
  const [now, setNow] = useState(() => Math.floor(Date.now() / 1000));

  useEffect(() => {
    const interval = setInterval(() => {
      setNow(Math.floor(Date.now() / 1000));
    }, 1000);
    return () => clearInterval(interval);
  }, []);

  const diff = Math.max(0, now - timestamp);

  if (diff < 5) return <span className="text-success">just now</span>;
  if (diff < 60) return <span>{diff}s ago</span>;
  if (diff < 3600) return <span>{Math.floor(diff / 60)}m ago</span>;
  return <span className="text-warning">{Math.floor(diff / 3600)}h ago</span>;
}

export default function WorkersPage() {
  const { data: workers, isLoading, isFetching } = useWorkerList();

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h2 className="text-xl font-semibold">Workers</h2>
        {isFetching && !isLoading && <Spinner size="sm" />}
      </div>

      {isLoading ? (
        <div className="space-y-3">
          {Array.from({ length: 3 }).map((_, i) => (
            <Skeleton key={i} className="w-full h-12 rounded-lg" />
          ))}
        </div>
      ) : (
        <Table aria-label="Workers table">
          <TableHeader>
            <TableColumn>Worker ID</TableColumn>
            <TableColumn>Queue</TableColumn>
            <TableColumn>Status</TableColumn>
            <TableColumn>PID</TableColumn>
            <TableColumn>Memory</TableColumn>
            <TableColumn>Uptime</TableColumn>
            <TableColumn>Jobs Processed</TableColumn>
            <TableColumn>Last Heartbeat</TableColumn>
          </TableHeader>
          <TableBody
            items={workers ?? []}
            emptyContent="No workers running"
          >
            {(worker) => (
              <TableRow key={worker.id}>
                <TableCell>
                  <span className="font-mono text-xs">{worker.id}</span>
                </TableCell>
                <TableCell>
                  <Chip size="sm" variant="bordered">{worker.queue}</Chip>
                </TableCell>
                <TableCell>
                  <Chip
                    size="sm"
                    variant="flat"
                    color={statusColors[worker.status] ?? 'default'}
                  >
                    {worker.status}
                  </Chip>
                </TableCell>
                <TableCell>
                  <span className="font-mono text-sm">{worker.pid}</span>
                </TableCell>
                <TableCell>
                  <span className="text-sm">{formatMemory(worker.memory_mb)}</span>
                </TableCell>
                <TableCell>
                  <span className="text-sm">{formatUptime(worker.uptime)}</span>
                </TableCell>
                <TableCell>
                  <span className="text-sm font-mono">
                    {worker.jobs_processed.toLocaleString()}
                  </span>
                </TableCell>
                <TableCell>
                  <span className="text-xs text-default-500">
                    <TimeSince timestamp={worker.last_heartbeat} />
                  </span>
                </TableCell>
              </TableRow>
            )}
          </TableBody>
        </Table>
      )}
    </div>
  );
}
