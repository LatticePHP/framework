"use client";

import { useExceptionCounts } from "@/lib/hooks";
import { Badge } from "@/components/ui/badge";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { Loader2, ArrowUp, ArrowDown, ArrowRight } from "lucide-react";

const trendVariants: Record<string, "danger" | "success" | "default"> = {
  increasing: "danger",
  decreasing: "success",
  stable: "default",
};

function TrendIcon({ trend }: { trend: string }) {
  if (trend === "increasing") return <ArrowUp className="h-3 w-3 inline" />;
  if (trend === "decreasing") return <ArrowDown className="h-3 w-3 inline" />;
  return <ArrowRight className="h-3 w-3 inline" />;
}

export default function ProdExceptionsPage() {
  const { data, isLoading, error } = useExceptionCounts();

  if (isLoading) {
    return (
      <div className="flex justify-center items-center h-64">
        <div className="flex items-center gap-2 text-muted-foreground">
          <Loader2 className="h-5 w-5 animate-spin" />
          <span>Loading exception data...</span>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="text-center py-12 text-red-500 dark:text-red-400">
        Failed to load exception data
      </div>
    );
  }

  const items = data?.data ?? [];

  const shortClass = (cls: string) => {
    const parts = cls.split("\\");
    return parts[parts.length - 1] ?? cls;
  };

  const formatTime = (ts: string | null) => {
    if (!ts) return "N/A";
    return new Date(ts).toLocaleString();
  };

  return (
    <div>
      <div className="flex flex-wrap items-center gap-3 mb-6">
        <h1 className="text-xl font-bold flex-1">Exception Frequency</h1>
        {data && (
          <Badge variant="danger">
            {data.total_exceptions.toLocaleString()} total
          </Badge>
        )}
      </div>

      <div className="rounded-md border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Exception Class</TableHead>
              <TableHead style={{ width: "100px" }}>Count</TableHead>
              <TableHead style={{ width: "120px" }}>Trend</TableHead>
              <TableHead style={{ width: "160px" }}>First Seen</TableHead>
              <TableHead style={{ width: "160px" }}>Last Seen</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {items.length === 0 ? (
              <TableRow>
                <TableCell
                  colSpan={5}
                  className="h-24 text-center text-muted-foreground"
                >
                  No exceptions recorded.
                </TableCell>
              </TableRow>
            ) : (
              items.map((item) => (
                <TableRow key={item.class}>
                  <TableCell>
                    <div>
                      <span className="text-sm font-semibold text-red-500 dark:text-red-400">
                        {shortClass(item.class)}
                      </span>
                      <p className="text-xs text-muted-foreground font-mono truncate max-w-md">
                        {item.class}
                      </p>
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
                      {item.count.toLocaleString()}
                    </Badge>
                  </TableCell>
                  <TableCell>
                    <Badge
                      variant={trendVariants[item.trend] ?? "default"}
                    >
                      <TrendIcon trend={item.trend} />
                      <span className="ml-1">{item.trend}</span>
                    </Badge>
                  </TableCell>
                  <TableCell>
                    <span className="text-xs text-muted-foreground">
                      {formatTime(item.first_seen)}
                    </span>
                  </TableCell>
                  <TableCell>
                    <span className="text-xs text-muted-foreground">
                      {formatTime(item.last_seen)}
                    </span>
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
