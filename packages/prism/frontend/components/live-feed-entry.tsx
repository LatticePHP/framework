"use client";

import { LevelBadge } from "@/components/ui/status-badge";
import { timeAgo } from "@/lib/utils";
import type { ErrorEvent } from "@/lib/schemas";

interface LiveFeedEntryProps {
  event: ErrorEvent;
}

export default function LiveFeedEntry({ event }: LiveFeedEntryProps) {
  const exType = event.exception?.type ?? "Unknown Error";
  const exMessage = event.exception?.value ?? "No message";

  return (
    <div className="flex items-start gap-3 p-3 rounded-lg border bg-card hover:bg-accent/30 transition-colors">
      <div className="shrink-0 mt-0.5">
        <LevelBadge level={event.level} />
      </div>

      <div className="flex-1 min-w-0">
        <p className="font-medium text-sm truncate">{exType}</p>
        <p className="text-xs text-muted-foreground truncate mt-0.5">{exMessage}</p>
        <div className="flex items-center gap-3 mt-1.5 text-xs text-muted-foreground">
          <span>{event.environment}</span>
          <span>{event.platform}</span>
          {event.server_name && <span>{event.server_name}</span>}
        </div>
      </div>

      <div className="shrink-0 text-xs text-muted-foreground">
        {timeAgo(event.timestamp)}
      </div>
    </div>
  );
}
