import type {
  Stats,
  TimeSeriesMetrics,
  Job,
  FailedJob,
  Worker,
  JobDetail,
} from "./schemas";

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/** Epoch seconds for "now" at the time the module is first evaluated. */
const NOW = Math.floor(Date.now() / 1000);

/** Generate a simple UUID-v4-ish string. */
function fakeId(): string {
  const hex = () =>
    Math.floor(Math.random() * 0xffff)
      .toString(16)
      .padStart(4, "0");
  return `${hex()}${hex()}-${hex()}-4${hex().slice(1)}-${hex()}-${hex()}${hex()}${hex()}`;
}

/** Return a past timestamp (epoch seconds) relative to NOW. */
function ago(seconds: number): number {
  return NOW - seconds;
}

/** ISO-8601 string for a past offset. */
function isoAgo(seconds: number): string {
  return new Date(ago(seconds) * 1000).toISOString();
}

// ---------------------------------------------------------------------------
// Stats
// ---------------------------------------------------------------------------

export const demoStats: Stats = {
  total_processed: 12847,
  total_failed: 23,
  processed_last_hour: 342,
  failed_last_hour: 1,
  throughput_per_minute: 5.7,
  avg_runtime_ms: 1250,
  avg_wait_ms: 340,
  active_workers: 4,
  queue_sizes: {
    default: 12,
    emails: 5,
    reports: 3,
    notifications: 8,
  },
};

// ---------------------------------------------------------------------------
// Time-series metrics (20 points over the last hour)
// ---------------------------------------------------------------------------

function buildThroughput(): TimeSeriesMetrics["data"]["throughput"] {
  const points: TimeSeriesMetrics["data"]["throughput"] = [];
  for (let i = 19; i >= 0; i--) {
    points.push({
      timestamp: ago(i * 180), // every 3 minutes over ~1 hour
      count: Math.round(4 + Math.random() * 6), // 4–10 jobs/min
    });
  }
  return points;
}

function buildRuntime(): TimeSeriesMetrics["data"]["runtime"] {
  const points: TimeSeriesMetrics["data"]["runtime"] = [];
  for (let i = 19; i >= 0; i--) {
    points.push({
      timestamp: ago(i * 180),
      avg_runtime_ms: Math.round(800 + Math.random() * 900), // 800–1700 ms
    });
  }
  return points;
}

export const demoMetrics: TimeSeriesMetrics = {
  data: {
    throughput: buildThroughput(),
    runtime: buildRuntime(),
  },
  meta: {
    period: "1h",
    queue: null,
  },
};

// ---------------------------------------------------------------------------
// Workers
// ---------------------------------------------------------------------------

export const demoWorkers: Worker[] = [
  {
    id: "worker-a1b2c3",
    queue: "default",
    status: "running",
    pid: 48201,
    memory_mb: 67.4,
    uptime: 86400,
    jobs_processed: 3012,
    last_heartbeat: ago(4),
  },
  {
    id: "worker-d4e5f6",
    queue: "emails",
    status: "running",
    pid: 48215,
    memory_mb: 45.2,
    uptime: 72000,
    jobs_processed: 1847,
    last_heartbeat: ago(2),
  },
  {
    id: "worker-g7h8i9",
    queue: "reports",
    status: "running",
    pid: 49102,
    memory_mb: 118.7,
    uptime: 43200,
    jobs_processed: 624,
    last_heartbeat: ago(7),
  },
  {
    id: "worker-j0k1l2",
    queue: "notifications",
    status: "running",
    pid: 49330,
    memory_mb: 52.1,
    uptime: 36000,
    jobs_processed: 2205,
    last_heartbeat: ago(1),
  },
];

// ---------------------------------------------------------------------------
// Recent jobs
// ---------------------------------------------------------------------------

const jobClasses = [
  "App\\Jobs\\SendEmailJob",
  "App\\Jobs\\GenerateReportJob",
  "App\\Jobs\\ProcessPaymentJob",
  "App\\Jobs\\SyncInventoryJob",
  "App\\Jobs\\SendNotificationJob",
];

const queues = ["default", "emails", "reports", "notifications"];

export const demoRecentJobs: Job[] = [
  { id: fakeId(), class: jobClasses[0], queue: "emails", status: "completed", runtime_ms: 320, attempts: 1, created_at: isoAgo(45), completed_at: isoAgo(44) },
  { id: fakeId(), class: jobClasses[4], queue: "notifications", status: "completed", runtime_ms: 112, attempts: 1, created_at: isoAgo(78), completed_at: isoAgo(77) },
  { id: fakeId(), class: jobClasses[2], queue: "default", status: "processing", runtime_ms: null, attempts: 1, created_at: isoAgo(12), completed_at: null },
  { id: fakeId(), class: jobClasses[1], queue: "reports", status: "completed", runtime_ms: 4520, attempts: 1, created_at: isoAgo(130), completed_at: isoAgo(125) },
  { id: fakeId(), class: jobClasses[3], queue: "default", status: "completed", runtime_ms: 890, attempts: 1, created_at: isoAgo(200), completed_at: isoAgo(199) },
  { id: fakeId(), class: jobClasses[0], queue: "emails", status: "completed", runtime_ms: 285, attempts: 1, created_at: isoAgo(260), completed_at: isoAgo(259) },
  { id: fakeId(), class: jobClasses[4], queue: "notifications", status: "pending", runtime_ms: null, attempts: 0, created_at: isoAgo(5), completed_at: null },
  { id: fakeId(), class: jobClasses[2], queue: "default", status: "completed", runtime_ms: 1730, attempts: 2, created_at: isoAgo(340), completed_at: isoAgo(337) },
  { id: fakeId(), class: jobClasses[1], queue: "reports", status: "completed", runtime_ms: 5200, attempts: 1, created_at: isoAgo(410), completed_at: isoAgo(405) },
  { id: fakeId(), class: jobClasses[3], queue: "default", status: "completed", runtime_ms: 945, attempts: 1, created_at: isoAgo(480), completed_at: isoAgo(479) },
  { id: fakeId(), class: jobClasses[0], queue: "emails", status: "completed", runtime_ms: 310, attempts: 1, created_at: isoAgo(550), completed_at: isoAgo(549) },
  { id: fakeId(), class: jobClasses[4], queue: "notifications", status: "completed", runtime_ms: 98, attempts: 1, created_at: isoAgo(620), completed_at: isoAgo(619) },
  { id: fakeId(), class: jobClasses[2], queue: "default", status: "pending", runtime_ms: null, attempts: 0, created_at: isoAgo(8), completed_at: null },
  { id: fakeId(), class: jobClasses[1], queue: "reports", status: "completed", runtime_ms: 3800, attempts: 1, created_at: isoAgo(780), completed_at: isoAgo(776) },
  { id: fakeId(), class: jobClasses[3], queue: "default", status: "completed", runtime_ms: 1020, attempts: 1, created_at: isoAgo(900), completed_at: isoAgo(899) },
];

// ---------------------------------------------------------------------------
// Failed jobs
// ---------------------------------------------------------------------------

export const demoFailedJobs: FailedJob[] = [
  {
    id: fakeId(),
    class: "App\\Jobs\\ProcessPaymentJob",
    queue: "default",
    status: "failed",
    runtime_ms: 3200,
    attempts: 3,
    created_at: isoAgo(1800),
    completed_at: null,
    failed_at: isoAgo(1750),
    exception_class: "Stripe\\Exception\\CardException",
    exception_message: "Your card was declined. The card has insufficient funds.",
    exception_trace: [
      { file: "app/Jobs/ProcessPaymentJob.php", line: 42, function: "charge", class: "App\\Services\\PaymentGateway" },
      { file: "vendor/stripe/stripe-php/lib/ApiRequestor.php", line: 217, function: "request", class: "Stripe\\ApiRequestor" },
    ],
  },
  {
    id: fakeId(),
    class: "App\\Jobs\\GenerateReportJob",
    queue: "reports",
    status: "failed",
    runtime_ms: 30000,
    attempts: 2,
    created_at: isoAgo(3200),
    completed_at: null,
    failed_at: isoAgo(3100),
    exception_class: "Illuminate\\Database\\QueryException",
    exception_message: "SQLSTATE[HY000]: General error: 1205 Lock wait timeout exceeded; try restarting transaction",
    exception_trace: [
      { file: "app/Jobs/GenerateReportJob.php", line: 78, function: "aggregate", class: "App\\Reports\\MonthlyRevenue" },
      { file: "vendor/laravel/framework/src/Illuminate/Database/Connection.php", line: 795, function: "run", class: "Illuminate\\Database\\Connection" },
    ],
  },
  {
    id: fakeId(),
    class: "App\\Jobs\\SyncInventoryJob",
    queue: "default",
    status: "failed",
    runtime_ms: 5400,
    attempts: 3,
    created_at: isoAgo(5400),
    completed_at: null,
    failed_at: isoAgo(5300),
    exception_class: "GuzzleHttp\\Exception\\ConnectException",
    exception_message: "cURL error 28: Connection timed out after 10000 milliseconds (see https://curl.haxx.se/libcurl/c/libcurl-errors.html) for https://inventory-api.example.com/v2/sync",
    exception_trace: [
      { file: "app/Jobs/SyncInventoryJob.php", line: 55, function: "pushUpdates", class: "App\\Services\\InventorySync" },
      { file: "vendor/guzzlehttp/guzzle/src/Handler/CurlHandler.php", line: 98, function: "__invoke", class: "GuzzleHttp\\Handler\\CurlHandler" },
    ],
  },
];

// ---------------------------------------------------------------------------
// Job detail (for /jobs/:id pages)
// ---------------------------------------------------------------------------

export const demoJobDetail: JobDetail = {
  id: demoRecentJobs[0].id,
  class: "App\\Jobs\\SendEmailJob",
  queue: "emails",
  status: "completed",
  runtime_ms: 320,
  attempts: 1,
  created_at: demoRecentJobs[0].created_at,
  completed_at: demoRecentJobs[0].completed_at,
  connection: "redis",
  max_attempts: 3,
  timeout: 60,
  payload: {
    to: "user@example.com",
    subject: "Your invoice is ready",
    template: "invoice-ready",
  },
};

// ---------------------------------------------------------------------------
// Route-based demo response resolver
// ---------------------------------------------------------------------------

/**
 * Given an API path, returns demo data that matches the expected response
 * shape. This allows the frontend to render beautifully even when the backend
 * is unreachable.
 */
export function getDemoResponse<T>(path: string): T {
  // Normalize: strip query string, trim slashes
  const clean = path.split("?")[0].replace(/^\/+|\/+$/g, "");

  if (clean === "stats") {
    return demoStats as unknown as T;
  }

  if (clean === "metrics") {
    return demoMetrics as unknown as T;
  }

  if (clean === "workers") {
    return { data: demoWorkers } as unknown as T;
  }

  if (clean === "jobs/recent") {
    return { data: demoRecentJobs, meta: { page: 1, per_page: 15 } } as unknown as T;
  }

  if (clean === "jobs/failed") {
    return { data: demoFailedJobs, meta: { page: 1, per_page: 15 } } as unknown as T;
  }

  // /jobs/:id — return the detail wrapped as the API would
  if (clean.startsWith("jobs/")) {
    return { data: demoJobDetail } as unknown as T;
  }

  // Mutation endpoints — return safe no-op responses matching Zod schemas
  if (clean.includes("retry-all")) {
    return { status: "retried", count: 0 } as unknown as T;
  }
  if (clean.includes("retry")) {
    return { status: "retried", job_id: clean.split("/")[1] ?? "demo" } as unknown as T;
  }

  return { status: "deleted" } as unknown as T;
}
