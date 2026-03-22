"use client";

import { useEffect, useState } from "react";
import { formatRelativeTime } from "@/lib/formatters";
import { Tooltip, TooltipContent, TooltipTrigger } from "@/components/ui/tooltip";

interface TimeAgoProps {
  date: string;
  className?: string;
}

export function TimeAgo({ date, className }: TimeAgoProps) {
  const [relative, setRelative] = useState(() => formatRelativeTime(date));

  useEffect(() => {
    const interval = setInterval(() => {
      setRelative(formatRelativeTime(date));
    }, 30000);
    return () => clearInterval(interval);
  }, [date]);

  return (
    <Tooltip>
      <TooltipTrigger asChild>
        <span className={className}>{relative}</span>
      </TooltipTrigger>
      <TooltipContent>{new Date(date).toLocaleString()}</TooltipContent>
    </Tooltip>
  );
}
