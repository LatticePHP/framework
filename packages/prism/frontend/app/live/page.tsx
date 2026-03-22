"use client";

import { useEffect, useRef, useState } from "react";
import { Radio, Pause, Play, Trash2 } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import LiveFeedEntry from "@/components/live-feed-entry";
import { usePrismStore } from "@/lib/store";
import type { ErrorEvent } from "@/lib/schemas";

// Demo events for when no SSE/WebSocket connection is available
const DEMO_LIVE_EVENTS: ErrorEvent[] = [
  {
    event_id: "live_001",
    timestamp: new Date().toISOString(),
    project_id: "proj_1",
    environment: "production",
    platform: "php",
    level: "error",
    exception: {
      type: "RuntimeException",
      value: "Connection refused to Redis at 127.0.0.1:6379",
    },
    server_name: "web-01",
  },
  {
    event_id: "live_002",
    timestamp: new Date(Date.now() - 5000).toISOString(),
    project_id: "proj_1",
    environment: "production",
    platform: "php",
    level: "warning",
    exception: {
      type: "DeprecationWarning",
      value: "Method getData() is deprecated, use toArray() instead",
    },
    server_name: "web-02",
  },
  {
    event_id: "live_003",
    timestamp: new Date(Date.now() - 12000).toISOString(),
    project_id: "proj_1",
    environment: "staging",
    platform: "php",
    level: "fatal",
    exception: {
      type: "OutOfMemoryError",
      value: "Allowed memory size of 134217728 bytes exhausted",
    },
    server_name: "worker-01",
  },
  {
    event_id: "live_004",
    timestamp: new Date(Date.now() - 30000).toISOString(),
    project_id: "proj_1",
    environment: "production",
    platform: "php",
    level: "info",
    exception: {
      type: "QueryLog",
      value: "Slow query detected: SELECT * FROM users WHERE email LIKE ... (2340ms)",
    },
    server_name: "web-01",
  },
  {
    event_id: "live_005",
    timestamp: new Date(Date.now() - 45000).toISOString(),
    project_id: "proj_1",
    environment: "production",
    platform: "php",
    level: "error",
    exception: {
      type: "ValidationException",
      value: "The email field must be a valid email address",
    },
    server_name: "web-03",
  },
];

export default function LiveFeedPage() {
  const selectedProject = usePrismStore((s) => s.selectedProject);
  const [events, setEvents] = useState<ErrorEvent[]>(DEMO_LIVE_EVENTS);
  const [paused, setPaused] = useState(false);
  const [connected, setConnected] = useState(false);
  const eventSourceRef = useRef<EventSource | null>(null);

  useEffect(() => {
    if (!selectedProject || paused) return;

    // Attempt SSE connection
    const apiBase = process.env.NEXT_PUBLIC_PRISM_API_URL ?? "/api/prism";
    const url = `${apiBase}/live?project_id=${encodeURIComponent(selectedProject)}`;

    try {
      const es = new EventSource(url);
      eventSourceRef.current = es;

      es.onopen = () => setConnected(true);

      es.onmessage = (msg) => {
        try {
          const event: ErrorEvent = JSON.parse(msg.data);
          setEvents((prev) => [event, ...prev].slice(0, 200));
        } catch {
          // ignore malformed messages
        }
      };

      es.onerror = () => {
        setConnected(false);
        es.close();
      };

      return () => {
        es.close();
        eventSourceRef.current = null;
        setConnected(false);
      };
    } catch {
      // SSE not available, use demo data
      setConnected(false);
    }
  }, [selectedProject, paused]);

  const clearFeed = () => setEvents([]);

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          <Radio className="h-5 w-5 text-primary" />
          <h1 className="text-xl font-bold">Live Feed</h1>
          {connected ? (
            <Badge variant="success" className="gap-1">
              <span className="h-2 w-2 rounded-full bg-emerald-500 animate-pulse" />
              Connected
            </Badge>
          ) : (
            <Badge variant="secondary">Demo Mode</Badge>
          )}
        </div>

        <div className="flex items-center gap-2">
          <Button
            variant="outline"
            size="sm"
            onClick={() => setPaused(!paused)}
          >
            {paused ? (
              <>
                <Play className="mr-1 h-4 w-4" /> Resume
              </>
            ) : (
              <>
                <Pause className="mr-1 h-4 w-4" /> Pause
              </>
            )}
          </Button>
          <Button variant="ghost" size="sm" onClick={clearFeed}>
            <Trash2 className="mr-1 h-4 w-4" /> Clear
          </Button>
        </div>
      </div>

      <p className="text-sm text-muted-foreground">
        {events.length} event{events.length !== 1 ? "s" : ""} in feed
        {paused && " (paused)"}
      </p>

      <div className="space-y-2">
        {events.length === 0 ? (
          <div className="text-center py-12 text-muted-foreground">
            <Radio className="h-12 w-12 mx-auto mb-3 opacity-30" />
            <p>Waiting for events...</p>
            <p className="text-sm mt-1">Errors will appear here in real-time.</p>
          </div>
        ) : (
          events.map((event) => (
            <LiveFeedEntry key={event.event_id} event={event} />
          ))
        )}
      </div>
    </div>
  );
}
