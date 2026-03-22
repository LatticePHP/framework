"use client";

import { useEffect, useState } from "react";
import { useParams, useRouter } from "next/navigation";
import { ArrowLeft, Check, EyeOff, AlertTriangle } from "lucide-react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { LevelBadge, StatusBadge } from "@/components/ui/status-badge";
import StackTrace from "@/components/ui/stack-trace";
import { fetchIssueDetail, resolveIssue } from "@/lib/api";
import { timeAgo } from "@/lib/utils";
import type { Issue, ErrorEvent, StackFrame } from "@/lib/schemas";

const DEMO_FRAMES: StackFrame[] = [
  {
    file: "app/Controllers/UserController.php",
    line: 45,
    class: "App\\Controllers\\UserController",
    function: "index",
    code_context: {
      pre: [
        "    public function index(Request $request): JsonResponse",
        "    {",
        "        $users = $this->userRepository->findAll();",
      ],
      line: "        return response()->json($users->map(fn($u) => $u->toArray()));",
      post: ["    }", "", "    public function show(string $id): JsonResponse"],
    },
  },
  {
    file: "app/Repositories/UserRepository.php",
    line: 23,
    class: "App\\Repositories\\UserRepository",
    function: "findAll",
  },
  {
    file: "vendor/illuminate/database/Query/Builder.php",
    line: 2856,
    class: "Illuminate\\Database\\Query\\Builder",
    function: "get",
  },
  {
    file: "vendor/illuminate/database/Connection.php",
    line: 753,
    class: "Illuminate\\Database\\Connection",
    function: "select",
  },
  {
    file: "vendor/illuminate/database/Connection.php",
    line: 392,
    class: "Illuminate\\Database\\Connection",
    function: "run",
  },
];

const DEMO_ISSUE: Issue = {
  id: "iss_1",
  project_id: "proj_1",
  fingerprint: "fp_001",
  title: "TypeError: Cannot read properties of undefined",
  level: "error",
  status: "unresolved",
  count: 142,
  first_seen: "2025-12-01T10:00:00Z",
  last_seen: "2025-12-15T14:23:00Z",
  culprit: "App\\Controllers\\UserController::index",
  platform: "php",
  environment: "production",
  release: "v1.4.2",
};

const DEMO_EVENTS: ErrorEvent[] = [
  {
    event_id: "evt_001",
    timestamp: "2025-12-15T14:23:00Z",
    project_id: "proj_1",
    environment: "production",
    platform: "php",
    level: "error",
    exception: {
      type: "TypeError",
      value: "Cannot read properties of undefined",
      stacktrace: DEMO_FRAMES,
    },
    tags: { browser: "Chrome 120", os: "Linux" },
    server_name: "web-01",
    release: "v1.4.2",
  },
  {
    event_id: "evt_002",
    timestamp: "2025-12-15T12:10:00Z",
    project_id: "proj_1",
    environment: "production",
    platform: "php",
    level: "error",
    exception: {
      type: "TypeError",
      value: "Cannot read properties of undefined",
      stacktrace: DEMO_FRAMES,
    },
    tags: { browser: "Firefox 121", os: "macOS" },
    server_name: "web-02",
    release: "v1.4.2",
  },
];

export default function IssueDetailPage() {
  const params = useParams();
  const router = useRouter();
  const id = params.id as string;

  const [issue, setIssue] = useState<Issue | null>(null);
  const [events, setEvents] = useState<ErrorEvent[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let cancelled = false;
    fetchIssueDetail(id)
      .then((data) => {
        if (cancelled) return;
        setIssue(data.issue);
        setEvents(data.sample_events);
      })
      .catch(() => {
        if (cancelled) return;
        setIssue(DEMO_ISSUE);
        setEvents(DEMO_EVENTS);
      })
      .finally(() => !cancelled && setLoading(false));
    return () => {
      cancelled = true;
    };
  }, [id]);

  const handleStatusChange = async (status: string) => {
    if (!issue) return;
    try {
      const updated = await resolveIssue(issue.id, status);
      setIssue(updated);
    } catch {
      setIssue({ ...issue, status: status as Issue["status"] });
    }
  };

  if (loading) {
    return (
      <div className="space-y-6">
        <div className="h-8 w-48 bg-muted rounded animate-pulse" />
        <div className="h-64 bg-muted rounded animate-pulse" />
      </div>
    );
  }

  if (!issue) {
    return (
      <div className="flex flex-col items-center justify-center py-12 space-y-4">
        <AlertTriangle className="h-12 w-12 text-muted-foreground" />
        <p className="text-lg text-muted-foreground">Issue not found.</p>
        <Button variant="outline" onClick={() => router.push("/issues")}>
          Back to Issues
        </Button>
      </div>
    );
  }

  const stackFrames: StackFrame[] =
    events.find((e) => e.exception?.stacktrace?.length)?.exception?.stacktrace ?? [];

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-start gap-4">
        <Button variant="ghost" size="icon" onClick={() => router.push("/issues")}>
          <ArrowLeft className="h-4 w-4" />
        </Button>

        <div className="flex-1 min-w-0 space-y-2">
          <div className="flex items-center gap-3 flex-wrap">
            <LevelBadge level={issue.level} />
            <StatusBadge status={issue.status} />
            {issue.environment && <Badge variant="outline">{issue.environment}</Badge>}
            {issue.platform && <Badge variant="outline">{issue.platform}</Badge>}
          </div>
          <h1 className="text-xl font-bold break-words">{issue.title}</h1>
          {issue.culprit && (
            <p className="text-sm text-muted-foreground font-mono">{issue.culprit}</p>
          )}
        </div>
      </div>

      {/* Meta cards */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
        <Card>
          <CardContent className="p-4">
            <p className="text-xs text-muted-foreground uppercase tracking-wider">Events</p>
            <p className="text-2xl font-bold mt-1">{issue.count.toLocaleString()}</p>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="p-4">
            <p className="text-xs text-muted-foreground uppercase tracking-wider">First Seen</p>
            <p className="text-sm font-medium mt-1">{timeAgo(issue.first_seen)}</p>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="p-4">
            <p className="text-xs text-muted-foreground uppercase tracking-wider">Last Seen</p>
            <p className="text-sm font-medium mt-1">{timeAgo(issue.last_seen)}</p>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="p-4">
            <p className="text-xs text-muted-foreground uppercase tracking-wider">Release</p>
            <p className="text-sm font-medium mt-1 font-mono">{issue.release ?? "N/A"}</p>
          </CardContent>
        </Card>
      </div>

      {/* Actions */}
      <div className="flex gap-2">
        {issue.status !== "resolved" && (
          <Button size="sm" onClick={() => handleStatusChange("resolved")}>
            <Check className="mr-2 h-4 w-4" />
            Resolve
          </Button>
        )}
        {issue.status !== "ignored" && (
          <Button variant="secondary" size="sm" onClick={() => handleStatusChange("ignored")}>
            <EyeOff className="mr-2 h-4 w-4" />
            Ignore
          </Button>
        )}
        {issue.status !== "unresolved" && (
          <Button variant="outline" size="sm" onClick={() => handleStatusChange("unresolved")}>
            Unresolve
          </Button>
        )}
      </div>

      {/* Stack Trace - THE KEY COMPONENT */}
      <Card>
        <CardHeader>
          <CardTitle className="text-base">Stack Trace</CardTitle>
        </CardHeader>
        <CardContent>
          <StackTrace frames={stackFrames} maxVisible={15} />
        </CardContent>
      </Card>

      {/* Sample Events Tabs */}
      {events.length > 0 && (
        <Card>
          <CardHeader>
            <CardTitle className="text-base">Sample Events</CardTitle>
          </CardHeader>
          <CardContent>
            <Tabs defaultValue={events[0].event_id}>
              <TabsList>
                {events.map((event, i) => (
                  <TabsTrigger key={event.event_id} value={event.event_id}>
                    Event {i + 1}
                  </TabsTrigger>
                ))}
              </TabsList>

              {events.map((event) => (
                <TabsContent key={event.event_id} value={event.event_id}>
                  <div className="space-y-4">
                    <div className="grid grid-cols-2 md:grid-cols-3 gap-3 text-sm">
                      <div>
                        <span className="text-muted-foreground">Event ID:</span>
                        <p className="font-mono text-xs mt-0.5 truncate">{event.event_id}</p>
                      </div>
                      <div>
                        <span className="text-muted-foreground">Timestamp:</span>
                        <p className="mt-0.5">{new Date(event.timestamp).toLocaleString()}</p>
                      </div>
                      <div>
                        <span className="text-muted-foreground">Server:</span>
                        <p className="mt-0.5">{event.server_name ?? "N/A"}</p>
                      </div>
                    </div>

                    {event.exception && (
                      <div className="rounded-md border p-3 space-y-1">
                        <p className="font-semibold text-sm">
                          {event.exception.type ?? "Exception"}
                        </p>
                        <p className="text-sm text-muted-foreground">
                          {event.exception.value ?? "No message"}
                        </p>
                      </div>
                    )}

                    {event.tags && Object.keys(event.tags).length > 0 && (
                      <div>
                        <p className="text-sm text-muted-foreground mb-2">Tags</p>
                        <div className="flex flex-wrap gap-1.5">
                          {Object.entries(event.tags).map(([k, v]) => (
                            <Badge key={k} variant="outline" className="text-xs">
                              {k}: {v}
                            </Badge>
                          ))}
                        </div>
                      </div>
                    )}

                    {event.exception?.stacktrace && event.exception.stacktrace.length > 0 && (
                      <div>
                        <p className="text-sm text-muted-foreground mb-2">Stack Trace</p>
                        <StackTrace frames={event.exception.stacktrace} maxVisible={8} />
                      </div>
                    )}

                    {event.context && Object.keys(event.context).length > 0 && (
                      <div>
                        <p className="text-sm text-muted-foreground mb-2">Context</p>
                        <pre className="rounded-md border bg-muted/30 p-3 text-xs overflow-x-auto font-mono">
                          {JSON.stringify(event.context, null, 2)}
                        </pre>
                      </div>
                    )}
                  </div>
                </TabsContent>
              ))}
            </Tabs>
          </CardContent>
        </Card>
      )}
    </div>
  );
}
