"use client";

import { useEffect, useState } from "react";

export function TimeSince({ timestamp }: { timestamp: number }) {
  const [now, setNow] = useState(() => Math.floor(Date.now() / 1000));

  useEffect(() => {
    const interval = setInterval(() => {
      setNow(Math.floor(Date.now() / 1000));
    }, 1000);
    return () => clearInterval(interval);
  }, []);

  const diff = Math.max(0, now - timestamp);

  if (diff < 5)
    return (
      <span className="text-emerald-600 dark:text-emerald-400">just now</span>
    );
  if (diff < 60) return <span>{diff}s ago</span>;
  if (diff < 3600) return <span>{Math.floor(diff / 60)}m ago</span>;
  return (
    <span className="text-amber-600 dark:text-amber-400">
      {Math.floor(diff / 3600)}h ago
    </span>
  );
}
