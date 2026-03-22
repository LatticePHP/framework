import { useParams, useNavigate } from "react-router-dom";
import {
  Button,
  Card,
  CardBody,
  CardHeader,
  Skeleton,
  Divider,
  Chip,
  Tabs,
  Tab,
  Snippet,
} from "@nextui-org/react";
import { useIssue } from "@/api/issues";
import {
  useResolveIssue,
  useIgnoreIssue,
  useUnresolveIssue,
} from "@/api/mutations";
import { IssueBadge } from "@/components/IssueBadge";
import { StatusChip } from "@/components/StatusChip";
import { EventCount } from "@/components/EventCount";
import { TimeAgo } from "@/components/TimeAgo";
import { StackTraceViewer } from "@/components/StackTraceViewer";
import type { ErrorEvent } from "@/schemas/issue";

// --- Breadcrumbs viewer for sample events ---

function BreadcrumbsViewer({ event }: { event: ErrorEvent }) {
  const breadcrumbs = (
    event.context?.breadcrumbs as
      | Array<{
          timestamp?: string;
          category?: string;
          message?: string;
          level?: string;
        }>
      | undefined
  );

  if (!breadcrumbs || breadcrumbs.length === 0) return null;

  return (
    <div className="space-y-1">
      {breadcrumbs.map((crumb, i) => (
        <div
          key={i}
          className="flex items-center gap-3 text-xs py-1.5 px-2 rounded hover:bg-default-50"
        >
          {crumb.timestamp && (
            <span className="text-default-400 font-mono flex-shrink-0 w-20">
              {new Date(crumb.timestamp).toLocaleTimeString()}
            </span>
          )}
          {crumb.category && (
            <Chip size="sm" variant="flat" className="text-[10px] h-5">
              {crumb.category}
            </Chip>
          )}
          <span className="text-default-600 truncate">
            {crumb.message ?? "(no message)"}
          </span>
        </div>
      ))}
    </div>
  );
}

// --- Tags viewer ---

function TagsViewer({ tags }: { tags: Record<string, string> }) {
  const entries = Object.entries(tags);
  if (entries.length === 0) return null;

  return (
    <div className="flex flex-wrap gap-2">
      {entries.map(([key, value]) => (
        <Chip key={key} size="sm" variant="bordered">
          <span className="text-default-400">{key}:</span> {value}
        </Chip>
      ))}
    </div>
  );
}

// --- Context viewer ---

function ContextViewer({ context }: { context: Record<string, unknown> }) {
  // Filter out breadcrumbs since we show them separately
  const filtered = Object.fromEntries(
    Object.entries(context).filter(([k]) => k !== "breadcrumbs"),
  );

  if (Object.keys(filtered).length === 0) return null;

  return (
    <div className="space-y-3">
      {Object.entries(filtered).map(([section, data]) => (
        <div key={section}>
          <h4 className="text-xs font-semibold text-default-500 uppercase tracking-wider mb-1.5">
            {section}
          </h4>
          <pre className="text-xs font-mono bg-default-50 dark:bg-default-50/50 rounded-lg p-3 overflow-x-auto text-default-600">
            {JSON.stringify(data, null, 2)}
          </pre>
        </div>
      ))}
    </div>
  );
}

// --- Sample event card ---

function SampleEventCard({ event, index }: { event: ErrorEvent; index: number }) {
  return (
    <Card className="border border-default-200">
      <CardHeader className="pb-2">
        <div className="flex items-center justify-between w-full">
          <div className="flex items-center gap-2">
            <span className="text-xs font-mono text-default-400">
              Event #{index + 1}
            </span>
            <IssueBadge level={event.level} size="sm" />
          </div>
          <div className="flex items-center gap-3 text-xs text-default-400">
            <span>{event.environment}</span>
            <span>{event.platform}</span>
            <TimeAgo date={event.timestamp} />
          </div>
        </div>
      </CardHeader>
      <CardBody className="pt-0">
        <Tabs size="sm" variant="underlined">
          {/* Stacktrace */}
          {event.exception?.stacktrace &&
            event.exception.stacktrace.length > 0 && (
              <Tab key="stacktrace" title="Stack Trace">
                <StackTraceViewer
                  frames={event.exception.stacktrace}
                  className="mt-2"
                />
              </Tab>
            )}

          {/* Tags */}
          {event.tags && Object.keys(event.tags).length > 0 && (
            <Tab key="tags" title="Tags">
              <div className="mt-2">
                <TagsViewer tags={event.tags} />
              </div>
            </Tab>
          )}

          {/* Context */}
          {event.context && Object.keys(event.context).length > 0 && (
            <Tab key="context" title="Context">
              <div className="mt-2">
                <ContextViewer context={event.context} />
              </div>
            </Tab>
          )}

          {/* Breadcrumbs */}
          <Tab key="breadcrumbs" title="Breadcrumbs">
            <div className="mt-2">
              <BreadcrumbsViewer event={event} />
              {(!event.context?.breadcrumbs ||
                (event.context.breadcrumbs as unknown[]).length === 0) && (
                <p className="text-sm text-default-400 py-4 text-center">
                  No breadcrumbs recorded for this event
                </p>
              )}
            </div>
          </Tab>

          {/* Raw JSON */}
          <Tab key="raw" title="Raw">
            <pre className="mt-2 text-xs font-mono bg-default-50 dark:bg-default-50/50 rounded-lg p-3 overflow-x-auto max-h-96 text-default-600">
              {JSON.stringify(event, null, 2)}
            </pre>
          </Tab>
        </Tabs>
      </CardBody>
    </Card>
  );
}

// === Main Component ===

export function IssueDetailPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();

  const { data, isLoading, error } = useIssue(id);
  const resolveMutation = useResolveIssue();
  const ignoreMutation = useIgnoreIssue();
  const unresolveMutation = useUnresolveIssue();

  // Loading
  if (isLoading) {
    return (
      <div className="p-6 space-y-4">
        <Skeleton className="h-8 w-64 rounded-lg" />
        <Skeleton className="h-24 rounded-xl" />
        <Skeleton className="h-64 rounded-xl" />
      </div>
    );
  }

  // Error
  if (error || !data) {
    return (
      <div className="p-6">
        <Card>
          <CardBody className="text-center py-12">
            <p className="text-danger mb-2">
              {error ? "Failed to load issue" : "Issue not found"}
            </p>
            <p className="text-sm text-default-400 mb-4">
              {error instanceof Error ? error.message : "The requested issue could not be found."}
            </p>
            <Button variant="flat" onPress={() => navigate("/issues")}>
              Back to Issues
            </Button>
          </CardBody>
        </Card>
      </div>
    );
  }

  const { issue, sample_events } = data;

  // Parse exception type and message from title
  const colonIndex = issue.title.indexOf(":");
  const exceptionType =
    colonIndex > 0 ? issue.title.slice(0, colonIndex).trim() : issue.title;
  const exceptionMessage =
    colonIndex > 0 ? issue.title.slice(colonIndex + 1).trim() : "";

  const copyUrl = () => {
    void navigator.clipboard.writeText(window.location.href);
  };

  return (
    <div className="p-6 max-w-6xl">
      {/* Back link */}
      <Button
        variant="light"
        size="sm"
        className="mb-4 -ml-2"
        onPress={() => navigate("/issues")}
      >
        &larr; Back to Issues
      </Button>

      {/* Header card */}
      <Card className="border border-default-200 mb-6">
        <CardBody className="p-6">
          <div className="flex items-start justify-between gap-4">
            {/* Title area */}
            <div className="flex-1 min-w-0">
              <div className="flex items-center gap-2 mb-2 flex-wrap">
                <IssueBadge level={issue.level} size="md" />
                <StatusChip status={issue.status} size="md" />
                {issue.environment && (
                  <Chip size="sm" variant="flat">
                    {issue.environment}
                  </Chip>
                )}
                {issue.platform && (
                  <Chip size="sm" variant="bordered">
                    {issue.platform}
                  </Chip>
                )}
              </div>

              <h1 className="text-xl font-bold text-foreground mb-1 font-mono">
                {exceptionType}
              </h1>
              {exceptionMessage && (
                <p className="text-sm text-default-500 mb-3">
                  {exceptionMessage}
                </p>
              )}

              {issue.culprit && (
                <p className="text-xs text-default-400 font-mono">
                  {issue.culprit}
                </p>
              )}
            </div>

            {/* Action buttons */}
            <div className="flex flex-col gap-2 flex-shrink-0">
              {issue.status === "unresolved" && (
                <>
                  <Button
                    color="success"
                    size="sm"
                    onPress={() => resolveMutation.mutate(issue.id)}
                    isLoading={resolveMutation.isPending}
                  >
                    Resolve
                  </Button>
                  <Button
                    variant="flat"
                    size="sm"
                    onPress={() => ignoreMutation.mutate(issue.id)}
                    isLoading={ignoreMutation.isPending}
                  >
                    Ignore
                  </Button>
                </>
              )}
              {issue.status === "resolved" && (
                <Button
                  color="warning"
                  variant="flat"
                  size="sm"
                  onPress={() => unresolveMutation.mutate(issue.id)}
                  isLoading={unresolveMutation.isPending}
                >
                  Unresolve
                </Button>
              )}
              {issue.status === "ignored" && (
                <Button
                  color="warning"
                  variant="flat"
                  size="sm"
                  onPress={() => unresolveMutation.mutate(issue.id)}
                  isLoading={unresolveMutation.isPending}
                >
                  Unresolve
                </Button>
              )}
              <Button size="sm" variant="bordered" onPress={copyUrl}>
                Copy URL
              </Button>
            </div>
          </div>

          <Divider className="my-4" />

          {/* Stats row */}
          <div className="flex items-center gap-8 text-sm">
            <div>
              <span className="text-default-400">Events</span>
              <div className="font-semibold mt-0.5">
                <EventCount count={issue.count} />
              </div>
            </div>
            <div>
              <span className="text-default-400">First seen</span>
              <div className="mt-0.5">
                <TimeAgo date={issue.first_seen} className="font-medium" />
              </div>
            </div>
            <div>
              <span className="text-default-400">Last seen</span>
              <div className="mt-0.5">
                <TimeAgo date={issue.last_seen} className="font-medium" />
              </div>
            </div>
            {issue.release && (
              <div>
                <span className="text-default-400">Release</span>
                <div className="mt-0.5">
                  <Snippet
                    size="sm"
                    variant="flat"
                    hideSymbol
                    className="text-xs"
                  >
                    {issue.release}
                  </Snippet>
                </div>
              </div>
            )}
            <div>
              <span className="text-default-400">Fingerprint</span>
              <div className="mt-0.5 font-mono text-xs text-default-500">
                {issue.fingerprint.slice(0, 16)}...
              </div>
            </div>
          </div>
        </CardBody>
      </Card>

      {/* Stacktrace from first sample event */}
      {sample_events.length > 0 &&
        sample_events[0]?.exception?.stacktrace &&
        sample_events[0].exception.stacktrace.length > 0 && (
          <div className="mb-6">
            <StackTraceViewer
              frames={sample_events[0].exception.stacktrace}
            />
          </div>
        )}

      {/* No stacktrace available */}
      {(sample_events.length === 0 ||
        !sample_events[0]?.exception?.stacktrace ||
        sample_events[0].exception.stacktrace.length === 0) && (
        <Card className="mb-6 border border-default-200">
          <CardBody className="text-center py-8">
            <p className="text-default-400">
              No stacktrace available for this issue
            </p>
          </CardBody>
        </Card>
      )}

      {/* Sample events */}
      {sample_events.length > 0 && (
        <div className="mb-6">
          <h2 className="text-lg font-semibold mb-3">
            Sample Events ({sample_events.length})
          </h2>
          <div className="space-y-4">
            {sample_events.map((event, i) => (
              <SampleEventCard key={event.event_id} event={event} index={i} />
            ))}
          </div>
        </div>
      )}

      {/* Tags from first event */}
      {sample_events.length > 0 &&
        sample_events[0]?.tags &&
        Object.keys(sample_events[0].tags).length > 0 && (
          <div className="mb-6">
            <h2 className="text-lg font-semibold mb-3">Tags</h2>
            <Card className="border border-default-200">
              <CardBody>
                <TagsViewer tags={sample_events[0].tags} />
              </CardBody>
            </Card>
          </div>
        )}
    </div>
  );
}
