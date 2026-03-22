"use client";

import { ReactNode } from "react";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { useFiltersStore } from "@/lib/store";
import type { BaseEntry, PaginatedResponse } from "@/lib/schemas";
import { Search, ChevronLeft, ChevronRight, Loader2 } from "lucide-react";

export interface ColumnDef<T = BaseEntry> {
  key: string;
  label: string;
  width?: string;
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
  searchPlaceholder = "Search...",
  showSearch = true,
}: EntryTableProps) {
  const { search, setSearch, page, setPage, pageSize } = useFiltersStore();

  const totalPages = data ? Math.max(1, Math.ceil(data.total / pageSize)) : 1;

  return (
    <div className="flex flex-col gap-4">
      {showSearch && (
        <div className="flex gap-3 items-center">
          <div className="relative max-w-xs">
            <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
            <Input
              placeholder={searchPlaceholder}
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="pl-8 h-9"
            />
          </div>
        </div>
      )}

      <div className="rounded-md border">
        <Table>
          <TableHeader>
            <TableRow>
              {columns.map((col) => (
                <TableHead key={col.key} style={col.width ? { width: col.width } : undefined}>
                  {col.label}
                </TableHead>
              ))}
            </TableRow>
          </TableHeader>
          <TableBody>
            {isLoading ? (
              <TableRow>
                <TableCell colSpan={columns.length} className="h-24 text-center">
                  <div className="flex items-center justify-center gap-2">
                    <Loader2 className="h-4 w-4 animate-spin" />
                    <span className="text-muted-foreground">Loading...</span>
                  </div>
                </TableCell>
              </TableRow>
            ) : !data?.data.length ? (
              <TableRow>
                <TableCell colSpan={columns.length} className="h-24 text-center text-muted-foreground">
                  No entries found.
                </TableCell>
              </TableRow>
            ) : (
              data.data.map((item) => (
                <TableRow
                  key={item.uuid}
                  className={onRowClick ? "cursor-pointer" : undefined}
                  onClick={() => onRowClick?.(item)}
                >
                  {columns.map((col) => (
                    <TableCell key={col.key}>{col.render(item)}</TableCell>
                  ))}
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </div>

      {totalPages > 1 && (
        <div className="flex items-center justify-center gap-2">
          <Button
            variant="outline"
            size="sm"
            onClick={() => setPage(Math.max(0, page - 1))}
            disabled={page === 0}
          >
            <ChevronLeft className="h-4 w-4" />
          </Button>
          <span className="text-sm text-muted-foreground">
            Page {page + 1} of {totalPages}
          </span>
          <Button
            variant="outline"
            size="sm"
            onClick={() => setPage(Math.min(totalPages - 1, page + 1))}
            disabled={page >= totalPages - 1}
          >
            <ChevronRight className="h-4 w-4" />
          </Button>
        </div>
      )}
    </div>
  );
}
