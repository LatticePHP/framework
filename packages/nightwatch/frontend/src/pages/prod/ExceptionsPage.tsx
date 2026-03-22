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
import { useExceptionCounts } from '@/api/metrics';

const trendColors: Record<string, 'danger' | 'success' | 'default'> = {
  increasing: 'danger',
  decreasing: 'success',
  stable: 'default',
};

const trendIcons: Record<string, string> = {
  increasing: '\u2191',
  decreasing: '\u2193',
  stable: '\u2192',
};

export default function ExceptionsPage() {
  const { data, isLoading, error } = useExceptionCounts();

  if (isLoading) {
    return (
      <div className="flex justify-center items-center h-64">
        <Spinner label="Loading exception data..." size="lg" />
      </div>
    );
  }

  if (error) {
    return (
      <div className="text-center py-12 text-danger">
        Failed to load exception data
      </div>
    );
  }

  const items = data?.data ?? [];

  const shortClass = (cls: string) => {
    const parts = cls.split('\\');
    return parts[parts.length - 1] ?? cls;
  };

  const formatTime = (ts: string | null) => {
    if (!ts) return 'N/A';
    return new Date(ts).toLocaleString();
  };

  return (
    <div>
      <div className="flex flex-wrap items-center gap-3 mb-6">
        <h1 className="text-xl font-bold flex-1">Exception Frequency</h1>
        {data && (
          <Chip size="sm" color="danger" variant="flat">
            {data.total_exceptions.toLocaleString()} total
          </Chip>
        )}
      </div>

      <Table
        aria-label="Exception counts"
        isStriped
        isHeaderSticky
        classNames={{
          wrapper: 'max-h-[calc(100vh-200px)] overflow-auto',
        }}
      >
        <TableHeader>
          <TableColumn>Exception Class</TableColumn>
          <TableColumn width={100}>Count</TableColumn>
          <TableColumn width={100}>Trend</TableColumn>
          <TableColumn width={160}>First Seen</TableColumn>
          <TableColumn width={160}>Last Seen</TableColumn>
        </TableHeader>
        <TableBody
          items={items}
          isLoading={isLoading}
          loadingContent={<Spinner />}
          emptyContent="No exceptions recorded."
        >
          {(item) => (
            <TableRow key={item.class}>
              <TableCell>
                <div>
                  <span className="text-sm font-semibold text-danger">
                    {shortClass(item.class)}
                  </span>
                  <p className="text-xs text-default-400 font-mono truncate max-w-md">
                    {item.class}
                  </p>
                </div>
              </TableCell>
              <TableCell>
                <Chip
                  size="sm"
                  variant="flat"
                  color={item.count > 100 ? 'danger' : item.count > 10 ? 'warning' : 'default'}
                >
                  {item.count.toLocaleString()}
                </Chip>
              </TableCell>
              <TableCell>
                <Chip
                  size="sm"
                  variant="flat"
                  color={trendColors[item.trend] ?? 'default'}
                  startContent={
                    <span>{trendIcons[item.trend] ?? ''}</span>
                  }
                >
                  {item.trend}
                </Chip>
              </TableCell>
              <TableCell>
                <span className="text-xs text-default-400">
                  {formatTime(item.first_seen)}
                </span>
              </TableCell>
              <TableCell>
                <span className="text-xs text-default-400">
                  {formatTime(item.last_seen)}
                </span>
              </TableCell>
            </TableRow>
          )}
        </TableBody>
      </Table>
    </div>
  );
}
