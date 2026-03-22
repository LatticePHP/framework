import {
  Table,
  TableHeader,
  TableColumn,
  TableBody,
  TableRow,
  TableCell,
  Spinner,
  Chip,
} from '@nextui-org/react';
import { useSlowQueries } from '@/api/metrics';
import DurationBadge from '@/components/DurationBadge';
import SqlHighlight from '@/components/SqlHighlight';

export default function SlowQueriesPage() {
  const { data, isLoading, error } = useSlowQueries();

  if (isLoading) {
    return (
      <div className="flex justify-center items-center h-64">
        <Spinner label="Loading slow queries..." size="lg" />
      </div>
    );
  }

  if (error) {
    return (
      <div className="text-center py-12 text-danger">
        Failed to load slow queries data
      </div>
    );
  }

  const items = data?.data ?? [];

  return (
    <div>
      <div className="flex flex-wrap items-center gap-3 mb-6">
        <h1 className="text-xl font-bold flex-1">Slow Queries</h1>
        {data && (
          <Chip size="sm" variant="flat">
            {data.total_queries.toLocaleString()} total queries
          </Chip>
        )}
      </div>

      <Table
        aria-label="Slow queries"
        isStriped
        isHeaderSticky
        classNames={{
          wrapper: 'max-h-[calc(100vh-200px)] overflow-auto',
        }}
      >
        <TableHeader>
          <TableColumn>Normalized SQL</TableColumn>
          <TableColumn width={100}>Frequency</TableColumn>
          <TableColumn width={100}>AVG</TableColumn>
          <TableColumn width={100}>P95</TableColumn>
          <TableColumn width={100}>Max</TableColumn>
          <TableColumn width={100}>Total Time</TableColumn>
        </TableHeader>
        <TableBody
          items={items}
          isLoading={isLoading}
          loadingContent={<Spinner />}
          emptyContent="No slow queries data."
        >
          {(item) => (
            <TableRow key={item.sql}>
              <TableCell>
                <div className="max-w-lg overflow-hidden">
                  <SqlHighlight sql={item.sql} truncate={200} />
                </div>
              </TableCell>
              <TableCell>
                <Chip
                  size="sm"
                  variant="flat"
                  color={item.count > 100 ? 'danger' : item.count > 10 ? 'warning' : 'default'}
                >
                  {item.count.toLocaleString()}x
                </Chip>
              </TableCell>
              <TableCell>
                <DurationBadge ms={item.avg_duration} />
              </TableCell>
              <TableCell>
                <DurationBadge ms={item.p95_duration} />
              </TableCell>
              <TableCell>
                {item.max_duration != null ? (
                  <DurationBadge ms={item.max_duration} />
                ) : (
                  <span className="text-xs text-default-400">--</span>
                )}
              </TableCell>
              <TableCell>
                {item.total_time != null ? (
                  <span className="text-xs font-mono">
                    {(item.total_time / 1000).toFixed(2)}s
                  </span>
                ) : (
                  <span className="text-xs text-default-400">--</span>
                )}
              </TableCell>
            </TableRow>
          )}
        </TableBody>
      </Table>
    </div>
  );
}
