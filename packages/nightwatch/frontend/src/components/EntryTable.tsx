import {
  Table,
  TableHeader,
  TableColumn,
  TableBody,
  TableRow,
  TableCell,
  Pagination,
  Spinner,
  Input,
} from '@nextui-org/react';
import type { BaseEntry } from '@/schemas/entry';
import type { PaginatedResponse } from '@/schemas/entry';
import { useFiltersStore } from '@/stores/filters';
import type { ReactNode } from 'react';

export interface ColumnDef<T = BaseEntry> {
  key: string;
  label: string;
  width?: number;
  render: (item: T) => ReactNode;
}

interface EntryTableProps {
  data: PaginatedResponse<BaseEntry> | undefined;
  columns: ColumnDef[];
  isLoading: boolean;
  onRowClick?: (entry: BaseEntry) => void;
  searchPlaceholder?: string;
  showSearch?: boolean;
}

export default function EntryTable({
  data,
  columns,
  isLoading,
  onRowClick,
  searchPlaceholder = 'Search...',
  showSearch = true,
}: EntryTableProps) {
  const { search, setSearch, page, setPage, pageSize } = useFiltersStore();

  const totalPages = data ? Math.max(1, Math.ceil(data.total / pageSize)) : 1;

  return (
    <div className="flex flex-col gap-4">
      {showSearch && (
        <div className="flex gap-3 items-center">
          <Input
            size="sm"
            placeholder={searchPlaceholder}
            value={search}
            onValueChange={setSearch}
            isClearable
            onClear={() => setSearch('')}
            className="max-w-xs"
            startContent={
              <span className="text-default-400 text-sm">{'\u{1F50D}'}</span>
            }
          />
        </div>
      )}

      <Table
        aria-label="Entry list"
        isStriped
        isHeaderSticky
        selectionMode={onRowClick ? 'single' : 'none'}
        onRowAction={onRowClick ? (key) => {
          const entry = data?.data.find((e) => e.uuid === key);
          if (entry) onRowClick(entry);
        } : undefined}
        classNames={{
          wrapper: 'max-h-[calc(100vh-220px)] overflow-auto',
        }}
        bottomContent={
          totalPages > 1 ? (
            <div className="flex w-full justify-center">
              <Pagination
                isCompact
                showControls
                showShadow
                color="primary"
                page={page + 1}
                total={totalPages}
                onChange={(p) => setPage(p - 1)}
              />
            </div>
          ) : null
        }
      >
        <TableHeader columns={columns}>
          {(column) => (
            <TableColumn key={column.key} width={column.width}>
              {column.label}
            </TableColumn>
          )}
        </TableHeader>
        <TableBody
          items={data?.data ?? []}
          isLoading={isLoading}
          loadingContent={<Spinner label="Loading..." />}
          emptyContent="No entries found."
        >
          {(item) => (
            <TableRow key={item.uuid} className="cursor-pointer">
              {(columnKey) => {
                const col = columns.find((c) => c.key === columnKey);
                return (
                  <TableCell>
                    {col ? col.render(item) : null}
                  </TableCell>
                );
              }}
            </TableRow>
          )}
        </TableBody>
      </Table>
    </div>
  );
}
