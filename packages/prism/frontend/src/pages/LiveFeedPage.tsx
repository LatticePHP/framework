import { useState, useEffect, useRef, useCallback } from "react";
import { useNavigate } from "react-router-dom";
import {
  Button,
  Card,
  CardBody,
  Chip,
  Select,
  SelectItem,
  Switch,
} from "@nextui-org/react";
import { useProjectStore } from "@/stores/project";
import { IssueBadge } from "@/components/IssueBadge";
import { LiveSignalSchema, type LiveSignal } from "@/schemas/issue";

const MAX_ENTRIES = 500;

const LEVEL_FILTER_OPTIONS = [
  { key: "", label: "All levels" },
  { key: "fatal", label: "Fatal" },
  { key: "error", label: "Error" },
  { key: "warning", label: "Warning" },
  { key: "info", label: "Info" },
];

type ConnectionStatus = "connected" | "connecting" | "disconnected";

const statusColors: Record<ConnectionStatus, "success" | "warning" | "danger"> =
  {
    connected: "success",
    connecting: "warning",
    disconnected: "danger",
  };

export function LiveFeedPage() {
  const navigate = useNavigate();
  const selectedProjectId = useProjectStore((s) => s.selectedProjectId);
  const liveFeedPaused = useProjectStore((s) => s.liveFeedPaused);
  const toggleLiveFeedPause = useProjectStore((s) => s.toggleLiveFeedPause);
  const liveFeedSoundEnabled = useProjectStore((s) => s.liveFeedSoundEnabled);
  const toggleLiveFeedSound = useProjectStore((s) => s.toggleLiveFeedSound);

  const [entries, setEntries] = useState<LiveSignal[]>([]);
  const [levelFilter, setLevelFilter] = useState<string>("");
  const [connectionStatus, setConnectionStatus] =
    useState<ConnectionStatus>("disconnected");

  const feedRef = useRef<HTMLDivElement>(null);
  const wsRef = useRef<WebSocket | null>(null);
  const reconnectTimeoutRef = useRef<ReturnType<typeof setTimeout>>();

  // WebSocket connection
  const connect = useCallback(() => {
    if (!selectedProjectId) return;

    setConnectionStatus("connecting");

    const protocol = window.location.protocol === "https:" ? "wss:" : "ws:";
    const wsUrl = `${protocol}//${window.location.host}/ws/prism/live?project_id=${selectedProjectId}`;

    try {
      const ws = new WebSocket(wsUrl);
      wsRef.current = ws;

      ws.onopen = () => {
        setConnectionStatus("connected");
      };

      ws.onmessage = (event) => {
        try {
          const parsed = JSON.parse(event.data as string) as unknown;
          const signal = LiveSignalSchema.parse(parsed);

          setEntries((prev) => {
            const next = [signal, ...prev];
            return next.length > MAX_ENTRIES ? next.slice(0, MAX_ENTRIES) : next;
          });
        } catch {
          // Ignore malformed messages
        }
      };

      ws.onclose = () => {
        setConnectionStatus("disconnected");
        wsRef.current = null;
        // Reconnect after 3 seconds
        reconnectTimeoutRef.current = setTimeout(connect, 3000);
      };

      ws.onerror = () => {
        ws.close();
      };
    } catch {
      setConnectionStatus("disconnected");
      reconnectTimeoutRef.current = setTimeout(connect, 3000);
    }
  }, [selectedProjectId]);

  useEffect(() => {
    connect();
    return () => {
      wsRef.current?.close();
      clearTimeout(reconnectTimeoutRef.current);
    };
  }, [connect]);

  // Auto-scroll
  useEffect(() => {
    if (!liveFeedPaused && feedRef.current) {
      feedRef.current.scrollTop = 0;
    }
  }, [entries, liveFeedPaused]);

  const filteredEntries = levelFilter
    ? entries.filter((e) => e.level === levelFilter)
    : entries;

  // No project selected
  if (!selectedProjectId) {
    return (
      <div className="p-6">
        <h2 className="text-2xl font-bold mb-4">Live Feed</h2>
        <Card>
          <CardBody className="text-center py-16">
            <h3 className="text-lg font-semibold mb-2">
              Select a project first
            </h3>
            <p className="text-sm text-default-400 mb-4">
              Use the project selector in the sidebar to pick a project.
            </p>
            <Button
              color="primary"
              variant="flat"
              onPress={() => navigate("/")}
            >
              Go to Projects
            </Button>
          </CardBody>
        </Card>
      </div>
    );
  }

  return (
    <div className="p-6 flex flex-col h-full">
      {/* Header */}
      <div className="flex items-center justify-between mb-4 flex-shrink-0">
        <div className="flex items-center gap-3">
          <h2 className="text-2xl font-bold text-foreground">Live Feed</h2>
          <Chip
            color={statusColors[connectionStatus]}
            variant="dot"
            size="sm"
          >
            {connectionStatus}
          </Chip>
        </div>

        <div className="flex items-center gap-4">
          <Select
            label="Level"
            size="sm"
            variant="bordered"
            selectedKeys={[levelFilter]}
            onChange={(e) => setLevelFilter(e.target.value)}
            className="w-32"
          >
            {LEVEL_FILTER_OPTIONS.map((opt) => (
              <SelectItem key={opt.key}>{opt.label}</SelectItem>
            ))}
          </Select>

          <Switch
            size="sm"
            isSelected={liveFeedSoundEnabled}
            onValueChange={toggleLiveFeedSound}
            classNames={{ label: "text-xs text-default-500" }}
          >
            Sound
          </Switch>

          <Button
            size="sm"
            color={liveFeedPaused ? "primary" : "default"}
            variant={liveFeedPaused ? "solid" : "flat"}
            onPress={toggleLiveFeedPause}
          >
            {liveFeedPaused ? "Resume" : "Pause"}
          </Button>

          <Button
            size="sm"
            variant="flat"
            onPress={() => setEntries([])}
          >
            Clear
          </Button>
        </div>
      </div>

      {/* Paused indicator */}
      {liveFeedPaused && (
        <div className="mb-3 p-2 rounded-lg bg-warning-50 dark:bg-warning-500/10 border border-warning-200 dark:border-warning-500/20 text-center text-sm text-warning-600 dark:text-warning-400 flex-shrink-0">
          Feed paused — new events are still being received but auto-scroll is
          disabled
        </div>
      )}

      {/* Feed */}
      <div
        ref={feedRef}
        className="flex-1 overflow-y-auto rounded-lg border border-default-200 bg-default-50 dark:bg-default-50/30"
      >
        {filteredEntries.length === 0 ? (
          <div className="flex items-center justify-center h-full text-default-400 text-sm">
            {connectionStatus === "connected"
              ? "Waiting for errors... (this is a good thing)"
              : "Connecting to live feed..."}
          </div>
        ) : (
          <div className="divide-y divide-default-100">
            {filteredEntries.map((entry, i) => (
              <button
                key={`${entry.event_id}-${i}`}
                type="button"
                onClick={() => navigate(`/issues/${entry.issue_id}`)}
                className={`
                  w-full flex items-center gap-3 px-4 py-3 text-left text-sm
                  hover:bg-default-100 dark:hover:bg-default-100/50 transition-colors
                  ${i === 0 && !liveFeedPaused ? "animate-pulse" : ""}
                `}
              >
                {/* Timestamp */}
                <span className="font-mono text-xs text-default-400 w-20 flex-shrink-0">
                  {entry.timestamp
                    ? new Date(entry.timestamp).toLocaleTimeString()
                    : "--:--:--"}
                </span>

                {/* Level badge */}
                <IssueBadge level={entry.level} />

                {/* Badges */}
                {entry.is_new_issue && (
                  <Chip size="sm" color="primary" variant="flat" className="text-[10px]">
                    NEW
                  </Chip>
                )}
                {entry.is_regression && (
                  <Chip size="sm" color="warning" variant="flat" className="text-[10px]">
                    REGRESSION
                  </Chip>
                )}

                {/* Title */}
                <span className="font-medium text-foreground truncate flex-1">
                  {entry.title}
                </span>

                {/* Environment */}
                {entry.environment && (
                  <span className="text-xs text-default-400 flex-shrink-0">
                    {entry.environment}
                  </span>
                )}
              </button>
            ))}
          </div>
        )}
      </div>

      {/* Footer status */}
      <div className="flex items-center justify-between mt-2 text-xs text-default-400 flex-shrink-0">
        <span>
          {filteredEntries.length} {filteredEntries.length === 1 ? "entry" : "entries"}
          {levelFilter ? ` (filtered)` : ""}
        </span>
        <span>Max {MAX_ENTRIES} entries displayed</span>
      </div>
    </div>
  );
}
