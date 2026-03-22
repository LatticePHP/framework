"use client";

import { useSlowQueries } from "@/lib/hooks";
import DurationBadge from "@/components/duration-badge";
import SqlHighlight from "@/components/sql-highlight";
import { Badge } from "@/components/ui/badge";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { Loader2 } from "lucide-react";

export default function SlowQueriesPage() {
  const { data, isLoading, error } = useSlowQueries();

  if (isLoading) {
    return (
      <div className="flex justify-center items-center h-64">
        <div className="flex items-center gap-2 text-muted-foreground">
          <Loader2 className="h-5 w-5 animate-spin" />
          <span>Loading slow queries...</span>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="text-center py-12 text-red-500 dark:text-red-400">
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
          <Badge variant="secondary">
            {data.total_queries.toLocaleString()} total queries
          </Badge>
        )}
      </div>

      <div className="rounded-md border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Normalized SQL</TableHead>
              <TableHead style={{ width: "100px" }}>Frequency</TableHead>
              <TableHead style={{ width: "100px" }}>AVG</TableHead>
              <TableHead style={{ width: "100px" }}>P95</TableHead>
              <TableHead style={{ width: "100px" }}>Max</TableHead>
              <TableHead style={{ width: "100px" }}>Total Time</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {items.length === 0 ? (
              <TableRow>
                <TableCell
                  colSpan={6}
                  className="h-24 text-center text-muted-foreground"
                >
                  No slow queries data.
                </TableCell>
              </TableRow>
            ) : (
              items.map((item) => (
                <TableRow key={item.sql}>
                  <TableCell>
                    <div className="max-w-lg overflow-hidden">
                      <SqlHighlight sql={item.sql} truncate={200} />
                    </div>
                  </TableCell>
                  <TableCell>
                    <Badge
                      variant={
                        item.count > 100
                          ? "danger"
                          : item.count > 10
                            ? "warning"
                            : "default"
                      }
                    >
                      {item.count.toLocaleString()}x
                    </Badge>
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
                      <span className="text-xs text-muted-foreground">
                        --
                      </span>
                    )}
                  </TableCell>
                  <TableCell>
                    {item.total_time != null ? (
                      <span className="text-xs font-mono">
                        {(item.total_time / 1000).toFixed(2)}s
                      </span>
                    ) : (
                      <span className="text-xs text-muted-foreground">
                        --
                      </span>
                    )}
                  </TableCell>
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </div>
    </div>
  );
}
