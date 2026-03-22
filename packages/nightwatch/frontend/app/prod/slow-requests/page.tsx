"use client";

import { useSlowRequests } from "@/lib/hooks";
import DurationBadge from "@/components/duration-badge";
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

export default function SlowRequestsPage() {
  const { data, isLoading, error } = useSlowRequests();

  if (isLoading) {
    return (
      <div className="flex justify-center items-center h-64">
        <div className="flex items-center gap-2 text-muted-foreground">
          <Loader2 className="h-5 w-5 animate-spin" />
          <span>Loading slow requests...</span>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="text-center py-12 text-red-500 dark:text-red-400">
        Failed to load slow requests data
      </div>
    );
  }

  const items = data?.data ?? [];

  return (
    <div>
      <div className="flex flex-wrap items-center gap-3 mb-6">
        <h1 className="text-xl font-bold flex-1">Slow Requests</h1>
        {data && (
          <Badge variant="secondary">
            {data.total_requests.toLocaleString()} total requests
          </Badge>
        )}
      </div>

      <div className="rounded-md border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Endpoint</TableHead>
              <TableHead style={{ width: "80px" }}>Method</TableHead>
              <TableHead style={{ width: "80px" }}>Count</TableHead>
              <TableHead style={{ width: "100px" }}>AVG</TableHead>
              <TableHead style={{ width: "100px" }}>P50</TableHead>
              <TableHead style={{ width: "100px" }}>P95</TableHead>
              <TableHead style={{ width: "100px" }}>P99</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {items.length === 0 ? (
              <TableRow>
                <TableCell
                  colSpan={7}
                  className="h-24 text-center text-muted-foreground"
                >
                  No slow requests data.
                </TableCell>
              </TableRow>
            ) : (
              items.map((item) => (
                <TableRow key={item.endpoint}>
                  <TableCell>
                    <span className="font-mono text-sm">
                      {item.endpoint}
                    </span>
                  </TableCell>
                  <TableCell>
                    <Badge variant="secondary">
                      {item.method ?? "ALL"}
                    </Badge>
                  </TableCell>
                  <TableCell>
                    <span className="text-sm">
                      {item.count.toLocaleString()}
                    </span>
                  </TableCell>
                  <TableCell>
                    <DurationBadge ms={item.avg} />
                  </TableCell>
                  <TableCell>
                    <DurationBadge ms={item.p50} />
                  </TableCell>
                  <TableCell>
                    <DurationBadge ms={item.p95} />
                  </TableCell>
                  <TableCell>
                    <DurationBadge ms={item.p99} />
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
