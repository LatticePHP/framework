import type {
  BaseEntry,
  PaginatedResponse,
  StatusResponse,
  MetricsOverview,
  SlowRequestsResponse,
  SlowQueriesResponse,
  ExceptionCountsResponse,
} from "./schemas";

// ── Helpers ──

function uuid(): string {
  return "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0;
    const v = c === "x" ? r : (r & 0x3) | 0x8;
    return v.toString(16);
  });
}

function minutesAgo(m: number): string {
  return new Date(Date.now() - m * 60_000).toISOString();
}

const BATCH = "b-" + uuid().slice(0, 8);

// ── Request entries ──

const demoRequests: BaseEntry[] = [
  {
    uuid: "a1b2c3d4-e5f6-4a7b-8c9d-0e1f2a3b4c5d",
    type: "request",
    timestamp: minutesAgo(2),
    tags: ["api", "contacts"],
    batch_id: BATCH,
    data: {
      method: "GET",
      uri: "/api/contacts",
      route_name: "contacts.index",
      controller: "App\\Http\\Controllers\\ContactController@index",
      ip: "127.0.0.1",
      status: 200,
      response_size: 14280,
      content_type: "application/json",
      duration_ms: 45,
      middleware: ["auth:api", "throttle:60,1"],
    },
  },
  {
    uuid: "b2c3d4e5-f6a7-4b8c-9d0e-1f2a3b4c5d6e",
    type: "request",
    timestamp: minutesAgo(5),
    tags: ["api", "deals"],
    batch_id: BATCH,
    data: {
      method: "PUT",
      uri: "/api/deals/5",
      route_name: "deals.update",
      controller: "App\\Http\\Controllers\\DealController@update",
      ip: "127.0.0.1",
      status: 200,
      response_size: 1842,
      content_type: "application/json",
      duration_ms: 128,
      user_id: 1,
      middleware: ["auth:api", "throttle:60,1"],
    },
  },
  {
    uuid: "c3d4e5f6-a7b8-4c9d-0e1f-2a3b4c5d6e7f",
    type: "request",
    timestamp: minutesAgo(8),
    tags: ["api", "auth"],
    batch_id: BATCH,
    data: {
      method: "POST",
      uri: "/api/auth/login",
      route_name: "auth.login",
      controller: "App\\Http\\Controllers\\AuthController@login",
      ip: "192.168.1.42",
      status: 200,
      response_size: 512,
      content_type: "application/json",
      duration_ms: 210,
      middleware: ["throttle:5,1"],
    },
  },
  {
    uuid: "d4e5f6a7-b8c9-4d0e-1f2a-3b4c5d6e7f80",
    type: "request",
    timestamp: minutesAgo(12),
    tags: ["api", "dashboard"],
    batch_id: BATCH,
    data: {
      method: "GET",
      uri: "/api/dashboard/stats",
      route_name: "dashboard.stats",
      controller: "App\\Http\\Controllers\\DashboardController@stats",
      ip: "127.0.0.1",
      status: 200,
      response_size: 3420,
      content_type: "application/json",
      duration_ms: 312,
      user_id: 1,
      middleware: ["auth:api"],
    },
  },
  {
    uuid: "e5f6a7b8-c9d0-4e1f-2a3b-4c5d6e7f8091",
    type: "request",
    timestamp: minutesAgo(15),
    tags: ["api", "companies"],
    batch_id: BATCH,
    data: {
      method: "GET",
      uri: "/api/companies",
      route_name: "companies.index",
      controller: "App\\Http\\Controllers\\CompanyController@index",
      ip: "127.0.0.1",
      status: 200,
      response_size: 8920,
      content_type: "application/json",
      duration_ms: 67,
      user_id: 1,
      middleware: ["auth:api", "throttle:60,1"],
    },
  },
  {
    uuid: "f6a7b8c9-d0e1-4f2a-3b4c-5d6e7f809102",
    type: "request",
    timestamp: minutesAgo(20),
    tags: ["api", "activities"],
    batch_id: BATCH,
    data: {
      method: "POST",
      uri: "/api/activities",
      route_name: "activities.store",
      controller: "App\\Http\\Controllers\\ActivityController@store",
      ip: "127.0.0.1",
      status: 201,
      response_size: 624,
      content_type: "application/json",
      duration_ms: 89,
      user_id: 1,
      middleware: ["auth:api"],
    },
  },
  {
    uuid: "07b8c9d0-e1f2-4a3b-4c5d-6e7f80910213",
    type: "request",
    timestamp: minutesAgo(25),
    tags: ["api", "contacts"],
    batch_id: BATCH,
    data: {
      method: "GET",
      uri: "/api/contacts/999",
      route_name: "contacts.show",
      controller: "App\\Http\\Controllers\\ContactController@show",
      ip: "10.0.0.5",
      status: 404,
      response_size: 184,
      content_type: "application/json",
      duration_ms: 12,
      middleware: ["auth:api"],
    },
  },
  {
    uuid: "18c9d0e1-f2a3-4b4c-5d6e-7f8091021324",
    type: "request",
    timestamp: minutesAgo(30),
    tags: ["api", "reports", "error"],
    batch_id: BATCH,
    data: {
      method: "GET",
      uri: "/api/reports/export",
      route_name: "reports.export",
      controller: "App\\Http\\Controllers\\ReportController@export",
      ip: "127.0.0.1",
      status: 500,
      response_size: 340,
      content_type: "application/json",
      duration_ms: 450,
      user_id: 1,
      middleware: ["auth:api"],
    },
  },
];

// ── Query entries ──

const demoQueries: BaseEntry[] = [
  {
    uuid: "q1a2b3c4-d5e6-4f78-9a0b-c1d2e3f4a5b6",
    type: "query",
    timestamp: minutesAgo(2),
    tags: ["contacts", "select"],
    batch_id: BATCH,
    data: {
      sql: 'SELECT * FROM "contacts" WHERE "company_id" = ? AND "archived_at" IS NULL ORDER BY "last_name" ASC LIMIT 25 OFFSET 0',
      bindings: [42],
      duration_ms: 3.2,
      connection: "pgsql",
      caller: "App\\Http\\Controllers\\ContactController@index",
      slow: false,
      query_type: "select",
      n1_detected: false,
    },
  },
  {
    uuid: "q2b3c4d5-e6f7-4890-ab1c-d2e3f4a5b6c7",
    type: "query",
    timestamp: minutesAgo(5),
    tags: ["deals", "insert"],
    batch_id: BATCH,
    data: {
      sql: 'INSERT INTO "deals" ("title", "value", "stage", "contact_id", "user_id", "created_at", "updated_at") VALUES (?, ?, ?, ?, ?, ?, ?)',
      bindings: ["Enterprise License", 48000, "negotiation", 15, 1, "2026-03-22 10:30:00", "2026-03-22 10:30:00"],
      duration_ms: 1.8,
      connection: "pgsql",
      caller: "App\\Http\\Controllers\\DealController@store",
      slow: false,
      query_type: "insert",
      n1_detected: false,
    },
  },
  {
    uuid: "q3c4d5e6-f7a8-4901-bc2d-e3f4a5b6c7d8",
    type: "query",
    timestamp: minutesAgo(10),
    tags: ["activities", "update"],
    batch_id: BATCH,
    data: {
      sql: 'UPDATE "activities" SET "completed_at" = ?, "updated_at" = ? WHERE "id" = ? AND "user_id" = ?',
      bindings: ["2026-03-22 10:15:00", "2026-03-22 10:15:00", 237, 1],
      duration_ms: 0.9,
      connection: "pgsql",
      caller: "App\\Http\\Controllers\\ActivityController@complete",
      slow: false,
      query_type: "update",
      n1_detected: false,
    },
  },
  {
    uuid: "q4d5e6f7-a8b9-4012-cd3e-f4a5b6c7d8e9",
    type: "query",
    timestamp: minutesAgo(12),
    tags: ["dashboard", "aggregate"],
    batch_id: BATCH,
    data: {
      sql: 'SELECT COUNT(*) as "total", "stage", SUM("value") as "pipeline_value" FROM "deals" WHERE "closed_at" IS NULL GROUP BY "stage"',
      bindings: [],
      duration_ms: 85.4,
      connection: "pgsql",
      caller: "App\\Http\\Controllers\\DashboardController@stats",
      slow: true,
      query_type: "select",
      n1_detected: false,
    },
  },
  {
    uuid: "q5e6f7a8-b9c0-4123-de4f-a5b6c7d8e9f0",
    type: "query",
    timestamp: minutesAgo(18),
    tags: ["contacts", "select", "n+1"],
    batch_id: BATCH,
    data: {
      sql: 'SELECT * FROM "companies" WHERE "id" = ?',
      bindings: [7],
      duration_ms: 0.5,
      connection: "pgsql",
      caller: "App\\Models\\Contact::company",
      slow: false,
      query_type: "select",
      n1_detected: true,
    },
  },
  {
    uuid: "q6f7a8b9-c0d1-4234-ef50-b6c7d8e9f0a1",
    type: "query",
    timestamp: minutesAgo(22),
    tags: ["reports", "select"],
    batch_id: BATCH,
    data: {
      sql: 'SELECT "contacts"."id", "contacts"."first_name", "contacts"."last_name", "contacts"."email", "companies"."name" AS "company_name" FROM "contacts" LEFT JOIN "companies" ON "contacts"."company_id" = "companies"."id" WHERE "contacts"."created_at" >= ? ORDER BY "contacts"."created_at" DESC',
      bindings: ["2026-01-01 00:00:00"],
      duration_ms: 42.1,
      connection: "pgsql",
      caller: "App\\Http\\Controllers\\ReportController@export",
      slow: false,
      query_type: "select",
      n1_detected: false,
    },
  },
];

// ── Exception entries ──

const demoExceptions: BaseEntry[] = [
  {
    uuid: "ex1a2b3c-4d5e-4f6a-7b8c-9d0e1f2a3b4c",
    type: "exception",
    timestamp: minutesAgo(25),
    tags: ["404", "model"],
    batch_id: BATCH,
    data: {
      class: "Illuminate\\Database\\Eloquent\\ModelNotFoundException",
      message: "No query results for model [App\\Models\\Contact] 999",
      code: 0,
      file: "/var/www/app/Http/Controllers/ContactController.php",
      line: 47,
      trace: [
        { file: "/var/www/app/Http/Controllers/ContactController.php", line: 47, class: "App\\Http\\Controllers\\ContactController", function: "show", type: "->" },
        { file: "/var/www/vendor/lattice/routing/src/Router.php", line: 218, class: "Lattice\\Routing\\Router", function: "dispatch", type: "->" },
        { file: "/var/www/vendor/lattice/http/src/Kernel.php", line: 134, class: "Lattice\\Http\\Kernel", function: "handle", type: "->" },
      ],
      request_context: { method: "GET", uri: "/api/contacts/999", ip: "10.0.0.5" },
    },
  },
  {
    uuid: "ex2b3c4d-5e6f-4a7b-8c9d-0e1f2a3b4c5d",
    type: "exception",
    timestamp: minutesAgo(30),
    tags: ["422", "validation"],
    batch_id: BATCH,
    data: {
      class: "Lattice\\Validation\\ValidationException",
      message: "The given data was invalid.",
      code: 422,
      file: "/var/www/vendor/lattice/validation/src/Validator.php",
      line: 89,
      trace: [
        { file: "/var/www/vendor/lattice/validation/src/Validator.php", line: 89, class: "Lattice\\Validation\\Validator", function: "validate", type: "->" },
        { file: "/var/www/app/Http/Controllers/DealController.php", line: 62, class: "App\\Http\\Controllers\\DealController", function: "store", type: "->" },
        { file: "/var/www/vendor/lattice/routing/src/Router.php", line: 218, class: "Lattice\\Routing\\Router", function: "dispatch", type: "->" },
      ],
      request_context: { method: "POST", uri: "/api/deals", ip: "127.0.0.1" },
      custom_context: { errors: { title: ["The title field is required."], value: ["The value must be a number."] } },
    },
  },
  {
    uuid: "ex3c4d5e-6f7a-4b8c-9d0e-1f2a3b4c5d6e",
    type: "exception",
    timestamp: minutesAgo(30),
    tags: ["500", "database"],
    batch_id: BATCH,
    data: {
      class: "Illuminate\\Database\\QueryException",
      message: 'SQLSTATE[23505]: Unique violation: 7 ERROR: duplicate key value violates unique constraint "contacts_email_unique"',
      code: "23505",
      file: "/var/www/vendor/illuminate/database/Connection.php",
      line: 795,
      trace: [
        { file: "/var/www/vendor/illuminate/database/Connection.php", line: 795, class: "Illuminate\\Database\\Connection", function: "runQueryCallback", type: "->" },
        { file: "/var/www/app/Http/Controllers/ContactController.php", line: 34, class: "App\\Http\\Controllers\\ContactController", function: "store", type: "->" },
        { file: "/var/www/vendor/lattice/routing/src/Router.php", line: 218, class: "Lattice\\Routing\\Router", function: "dispatch", type: "->" },
      ],
      request_context: { method: "POST", uri: "/api/contacts", ip: "127.0.0.1" },
    },
  },
];

// ── Event entries ──

const demoEvents: BaseEntry[] = [
  {
    uuid: "ev1a2b3c-4d5e-4f6a-7b8c-9d0e1f2a3b40",
    type: "event",
    timestamp: minutesAgo(3),
    tags: ["contact", "created"],
    batch_id: BATCH,
    data: {
      event_class: "App\\Events\\ContactCreated",
      payload: { contact_id: 156, email: "sarah.chen@acme.io", source: "web_form" },
      listeners: ["App\\Listeners\\SendWelcomeEmail", "App\\Listeners\\SyncToCRM", "App\\Listeners\\LogActivity"],
      broadcast: false,
    },
  },
  {
    uuid: "ev2b3c4d-5e6f-4a7b-8c9d-0e1f2a3b4c51",
    type: "event",
    timestamp: minutesAgo(7),
    tags: ["deal", "stage-change"],
    batch_id: BATCH,
    data: {
      event_class: "App\\Events\\DealStageChanged",
      payload: { deal_id: 42, from: "qualification", to: "negotiation", value: 48000 },
      listeners: ["App\\Listeners\\NotifyDealOwner", "App\\Listeners\\UpdatePipelineMetrics"],
      broadcast: true,
    },
  },
  {
    uuid: "ev3c4d5e-6f7a-4b8c-9d0e-1f2a3b4c5d62",
    type: "event",
    timestamp: minutesAgo(14),
    tags: ["mail", "sent"],
    batch_id: BATCH,
    data: {
      event_class: "App\\Events\\EmailSent",
      payload: { to: "james@globex.com", template: "follow_up", contact_id: 89 },
      listeners: ["App\\Listeners\\TrackEmailOpen", "App\\Listeners\\LogActivity"],
      broadcast: false,
    },
  },
  {
    uuid: "ev4d5e6f-7a8b-4c9d-0e1f-2a3b4c5d6e73",
    type: "event",
    timestamp: minutesAgo(20),
    tags: ["auth", "login"],
    batch_id: BATCH,
    data: {
      event_class: "App\\Events\\UserLoggedIn",
      payload: { user_id: 1, ip: "192.168.1.42", user_agent: "Mozilla/5.0" },
      listeners: ["App\\Listeners\\RecordLoginActivity", "App\\Listeners\\UpdateLastSeen"],
      broadcast: false,
    },
  },
  {
    uuid: "ev5e6f7a-8b9c-4d0e-1f2a-3b4c5d6e7f84",
    type: "event",
    timestamp: minutesAgo(35),
    tags: ["payment", "processed"],
    batch_id: BATCH,
    data: {
      event_class: "App\\Events\\PaymentProcessed",
      payload: { invoice_id: 1024, amount: 12500, currency: "USD", gateway: "stripe" },
      listeners: ["App\\Listeners\\SendPaymentReceipt", "App\\Listeners\\UpdateInvoiceStatus", "App\\Listeners\\NotifyAccounting"],
      broadcast: true,
    },
  },
];

// ── Cache entries ──

const demoCache: BaseEntry[] = [
  {
    uuid: "ca1a2b3c-4d5e-4f6a-7b8c-9d0e1f2a3b41",
    type: "cache",
    timestamp: minutesAgo(2),
    tags: ["contacts", "hit"],
    batch_id: BATCH,
    data: {
      operation: "hit",
      key: "contacts:list:page:1",
      ttl: null,
      value_size: 14280,
      store: "redis",
      duration_ms: 0.8,
    },
  },
  {
    uuid: "ca2b3c4d-5e6f-4a7b-8c9d-0e1f2a3b4c52",
    type: "cache",
    timestamp: minutesAgo(12),
    tags: ["dashboard", "miss"],
    batch_id: BATCH,
    data: {
      operation: "miss",
      key: "dashboard:stats:2026-03-22",
      ttl: null,
      value_size: null,
      store: "redis",
      duration_ms: 0.3,
    },
  },
  {
    uuid: "ca3c4d5e-6f7a-4b8c-9d0e-1f2a3b4c5d63",
    type: "cache",
    timestamp: minutesAgo(12),
    tags: ["dashboard", "write"],
    batch_id: BATCH,
    data: {
      operation: "write",
      key: "dashboard:stats:2026-03-22",
      ttl: 300,
      value_size: 3420,
      store: "redis",
      duration_ms: 1.1,
    },
  },
  {
    uuid: "ca4d5e6f-7a8b-4c9d-0e1f-2a3b4c5d6e74",
    type: "cache",
    timestamp: minutesAgo(20),
    tags: ["user", "hit"],
    batch_id: BATCH,
    data: {
      operation: "hit",
      key: "user:1:profile",
      ttl: null,
      value_size: 892,
      store: "redis",
      duration_ms: 0.4,
    },
  },
];

// ── Job entries ──

const demoJobs: BaseEntry[] = [
  {
    uuid: "jo1a2b3c-4d5e-4f6a-7b8c-9d0e1f2a3b42",
    type: "job",
    timestamp: minutesAgo(3),
    tags: ["mail", "completed"],
    batch_id: BATCH,
    data: {
      job_class: "App\\Jobs\\SendWelcomeEmailJob",
      queue: "mail",
      connection: "redis",
      payload: { contact_id: 156, template: "welcome" },
      status: "completed",
      duration_ms: 1240,
      attempt: 1,
      max_tries: 3,
    },
  },
  {
    uuid: "jo2b3c4d-5e6f-4a7b-8c9d-0e1f2a3b4c53",
    type: "job",
    timestamp: minutesAgo(7),
    tags: ["sync", "processing"],
    batch_id: BATCH,
    data: {
      job_class: "App\\Jobs\\SyncContactToCRMJob",
      queue: "default",
      connection: "redis",
      payload: { contact_id: 156, provider: "hubspot" },
      status: "processing",
      duration_ms: null,
      attempt: 1,
      max_tries: 3,
    },
  },
  {
    uuid: "jo3c4d5e-6f7a-4b8c-9d0e-1f2a3b4c5d64",
    type: "job",
    timestamp: minutesAgo(15),
    tags: ["export", "queued"],
    batch_id: BATCH,
    data: {
      job_class: "App\\Jobs\\GenerateReportExportJob",
      queue: "long-running",
      connection: "redis",
      payload: { report_type: "contacts", format: "csv", user_id: 1 },
      status: "queued",
      duration_ms: null,
      attempt: 0,
      max_tries: 1,
    },
  },
  {
    uuid: "jo4d5e6f-7a8b-4c9d-0e1f-2a3b4c5d6e75",
    type: "job",
    timestamp: minutesAgo(40),
    tags: ["notification", "completed"],
    batch_id: BATCH,
    data: {
      job_class: "App\\Jobs\\SendDealNotificationJob",
      queue: "notifications",
      connection: "redis",
      payload: { deal_id: 42, event: "stage_changed", user_id: 3 },
      status: "completed",
      duration_ms: 320,
      attempt: 1,
      max_tries: 3,
    },
  },
];

// ── Mail entries ──

const demoMail: BaseEntry[] = [
  {
    uuid: "ma1a2b3c-4d5e-4f6a-7b8c-9d0e1f2a3b43",
    type: "mail",
    timestamp: minutesAgo(3),
    tags: ["welcome", "transactional"],
    batch_id: BATCH,
    data: {
      to: ["sarah.chen@acme.io"],
      cc: null,
      bcc: null,
      subject: "Welcome to Our Platform!",
      from: "noreply@app.example.com",
      mailable_class: "App\\Mail\\WelcomeEmail",
      attachments: [],
      queued: true,
    },
  },
  {
    uuid: "ma2b3c4d-5e6f-4a7b-8c9d-0e1f2a3b4c54",
    type: "mail",
    timestamp: minutesAgo(14),
    tags: ["follow-up", "sales"],
    batch_id: BATCH,
    data: {
      to: ["james@globex.com"],
      cc: ["manager@app.example.com"],
      bcc: null,
      subject: "Following Up on Our Conversation",
      from: "sales@app.example.com",
      mailable_class: "App\\Mail\\FollowUpEmail",
      attachments: ["proposal-v2.pdf"],
      queued: true,
    },
  },
  {
    uuid: "ma3c4d5e-6f7a-4b8c-9d0e-1f2a3b4c5d65",
    type: "mail",
    timestamp: minutesAgo(35),
    tags: ["receipt", "billing"],
    batch_id: BATCH,
    data: {
      to: ["billing@initech.com"],
      cc: null,
      bcc: ["accounting@app.example.com"],
      subject: "Payment Receipt - Invoice #1024",
      from: "billing@app.example.com",
      mailable_class: "App\\Mail\\PaymentReceiptEmail",
      attachments: ["receipt-1024.pdf"],
      queued: false,
    },
  },
];

// ── Log entries ──

const demoLogs: BaseEntry[] = [
  {
    uuid: "lo1a2b3c-4d5e-4f6a-7b8c-9d0e1f2a3b44",
    type: "log",
    timestamp: minutesAgo(2),
    tags: ["info", "auth"],
    batch_id: BATCH,
    data: {
      level: "info",
      message: "User authenticated successfully",
      context: { user_id: 1, ip: "192.168.1.42", method: "jwt" },
      channel: "auth",
    },
  },
  {
    uuid: "lo2b3c4d-5e6f-4a7b-8c9d-0e1f2a3b4c55",
    type: "log",
    timestamp: minutesAgo(12),
    tags: ["warning", "query"],
    batch_id: BATCH,
    data: {
      level: "warning",
      message: "Slow query detected: dashboard stats aggregation took 85ms (threshold: 50ms)",
      context: { query: "SELECT COUNT(*)...", duration_ms: 85.4, threshold_ms: 50 },
      channel: "database",
    },
  },
  {
    uuid: "lo3c4d5e-6f7a-4b8c-9d0e-1f2a3b4c5d66",
    type: "log",
    timestamp: minutesAgo(25),
    tags: ["error", "http"],
    batch_id: BATCH,
    data: {
      level: "error",
      message: "ModelNotFoundException: No query results for model [App\\Models\\Contact] 999",
      context: { uri: "/api/contacts/999", method: "GET", status: 404 },
      channel: "http",
    },
  },
  {
    uuid: "lo4d5e6f-7a8b-4c9d-0e1f-2a3b4c5d6e76",
    type: "log",
    timestamp: minutesAgo(30),
    tags: ["error", "database"],
    batch_id: BATCH,
    data: {
      level: "error",
      message: "Unique constraint violation on contacts.email — duplicate entry for sarah.chen@acme.io",
      context: { table: "contacts", constraint: "contacts_email_unique", sql_state: "23505" },
      channel: "database",
    },
  },
  {
    uuid: "lo5e6f7a-8b9c-4d0e-1f2a-3b4c5d6e7f85",
    type: "log",
    timestamp: minutesAgo(45),
    tags: ["info", "queue"],
    batch_id: BATCH,
    data: {
      level: "info",
      message: "Job App\\Jobs\\SendWelcomeEmailJob completed in 1240ms on queue [mail]",
      context: { job_class: "App\\Jobs\\SendWelcomeEmailJob", queue: "mail", duration_ms: 1240 },
      channel: "queue",
    },
  },
];

// ── Lookup map ──

const demoEntryMap: Record<string, BaseEntry[]> = {
  "/requests": demoRequests,
  "/queries": demoQueries,
  "/exceptions": demoExceptions,
  "/events": demoEvents,
  "/cache": demoCache,
  "/jobs": demoJobs,
  "/mail": demoMail,
  "/logs": demoLogs,
};

// ── Status response ──

const demoStatus: StatusResponse = {
  mode: "dev",
  enabled: true,
  storage_size: 245760,
  entry_counts: {
    request: demoRequests.length,
    query: demoQueries.length,
    exception: demoExceptions.length,
    event: demoEvents.length,
    cache: demoCache.length,
    job: demoJobs.length,
    mail: demoMail.length,
    log: demoLogs.length,
  },
};

// ── Metrics overview ──

const demoMetrics: MetricsOverview = {
  requests_per_minute: { label: "Requests/min", value: 24.3, unit: "rpm", trend: "stable", change_percent: 1.2 },
  avg_response_time: { label: "Avg Response", value: 127, unit: "ms", trend: "down", change_percent: -8.5 },
  p99_response_time: { label: "P99 Response", value: 450, unit: "ms", trend: "stable", change_percent: 2.1 },
  error_rate: { label: "Error Rate", value: 1.8, unit: "%", trend: "down", change_percent: -15.0 },
  slow_queries_count: { label: "Slow Queries", value: 3, unit: "queries", trend: "down", change_percent: -25.0 },
  cache_hit_ratio: { label: "Cache Hit Ratio", value: 87.5, unit: "%", trend: "up", change_percent: 3.2 },
  queue_throughput: { label: "Queue Throughput", value: 142, unit: "jobs/hr", trend: "up", change_percent: 12.0 },
  memory_usage: { label: "Memory", value: 64.2, unit: "MB", trend: "stable", change_percent: 0.5 },
};

// ── Slow requests ──

const demoSlowRequests: SlowRequestsResponse = {
  data: [
    { endpoint: "/api/reports/export", method: "GET", count: 12, avg: 420, p50: 390, p95: 510, p99: 580, min: 310, max: 620, status_codes: { "200": 10, "500": 2 } },
    { endpoint: "/api/dashboard/stats", method: "GET", count: 48, avg: 285, p50: 260, p95: 380, p99: 450, min: 180, max: 490, status_codes: { "200": 48 } },
    { endpoint: "/api/contacts", method: "GET", count: 156, avg: 52, p50: 45, p95: 95, p99: 130, min: 12, max: 180, status_codes: { "200": 152, "304": 4 } },
  ],
  period: "1h",
  total_requests: 892,
};

// ── Slow queries ──

const demoSlowQueries: SlowQueriesResponse = {
  data: [
    { sql: "SELECT COUNT(*), stage, SUM(value) FROM deals WHERE closed_at IS NULL GROUP BY stage", count: 48, avg_duration: 72.3, p95_duration: 95.1, max_duration: 112.0, total_time: 3470.4 },
    { sql: "SELECT contacts.*, companies.name FROM contacts LEFT JOIN companies ON ... WHERE created_at >= ?", count: 12, avg_duration: 38.5, p95_duration: 52.0, max_duration: 68.0, total_time: 462.0 },
  ],
  period: "1h",
  total_queries: 1240,
};

// ── Exception counts ──

const demoExceptionCounts: ExceptionCountsResponse = {
  data: [
    { class: "Illuminate\\Database\\Eloquent\\ModelNotFoundException", count: 8, trend: "stable", first_seen: minutesAgo(55), last_seen: minutesAgo(25) },
    { class: "Lattice\\Validation\\ValidationException", count: 5, trend: "decreasing", first_seen: minutesAgo(50), last_seen: minutesAgo(30) },
    { class: "Illuminate\\Database\\QueryException", count: 2, trend: "stable", first_seen: minutesAgo(40), last_seen: minutesAgo(30) },
  ],
  total_exceptions: 15,
};

// ── Public resolver ──

function paginate<T>(entries: T[]): PaginatedResponse<T> {
  return { data: entries, total: entries.length, limit: 50, offset: 0 };
}

export function getDemoResponse<T>(path: string): T {
  // Strip query params for matching
  const cleanPath = path.split("?")[0];

  if (cleanPath === "/status") return demoStatus as T;
  if (cleanPath === "/overview") return demoMetrics as T;
  if (cleanPath === "/slow-requests") return demoSlowRequests as T;
  if (cleanPath === "/slow-queries") return demoSlowQueries as T;
  if (cleanPath === "/exception-counts") return demoExceptionCounts as T;

  const entries = demoEntryMap[cleanPath];
  if (entries) return paginate(entries) as T;

  // Fallback: empty paginated response for unknown paths
  return paginate([]) as T;
}
