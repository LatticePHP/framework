import type {
  WorkflowListResponse,
  WorkflowDetail,
  WorkflowDetailResponse,
  StatsResponse,
  EventsListResponse,
  WorkflowSummary,
  WorkflowEvent,
} from "./schemas";

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function hoursAgo(h: number): string {
  return new Date(Date.now() - h * 3600_000).toISOString();
}

function minutesAgo(m: number): string {
  return new Date(Date.now() - m * 60_000).toISOString();
}

// ---------------------------------------------------------------------------
// Demo workflow summaries
// ---------------------------------------------------------------------------

const demoWorkflows: WorkflowSummary[] = [
  {
    id: "wf_a8f3b2c1",
    workflow_id: "OrderProcessing-9841",
    type: "OrderProcessing",
    status: "running",
    started_at: minutesAgo(4),
    duration_ms: null,
    last_event_type: "activity_started",
    last_event_at: minutesAgo(1),
  },
  {
    id: "wf_e7d4f690",
    workflow_id: "PaymentReconciliation-7823",
    type: "PaymentReconciliation",
    status: "running",
    started_at: minutesAgo(12),
    duration_ms: null,
    last_event_type: "activity_completed",
    last_event_at: minutesAgo(2),
  },
  {
    id: "wf_c3b19a45",
    workflow_id: "EmailCampaign-5510",
    type: "EmailCampaign",
    status: "running",
    started_at: minutesAgo(8),
    duration_ms: null,
    last_event_type: "child_workflow_started",
    last_event_at: minutesAgo(3),
  },
  {
    id: "wf_91f0d3e8",
    workflow_id: "DataSync-4402",
    type: "DataSync",
    status: "completed",
    started_at: hoursAgo(2),
    duration_ms: 3842,
    last_event_type: "workflow_completed",
    last_event_at: hoursAgo(1.9),
  },
  {
    id: "wf_d5c8e217",
    workflow_id: "ReportGeneration-3316",
    type: "ReportGeneration",
    status: "completed",
    started_at: hoursAgo(5),
    duration_ms: 12740,
    last_event_type: "workflow_completed",
    last_event_at: hoursAgo(4.8),
  },
  {
    id: "wf_b4a62f89",
    workflow_id: "InventoryAudit-2290",
    type: "InventoryAudit",
    status: "completed",
    started_at: hoursAgo(8),
    duration_ms: 6215,
    last_event_type: "workflow_completed",
    last_event_at: hoursAgo(7.5),
  },
  {
    id: "wf_f2e81c04",
    workflow_id: "UserOnboarding-1184",
    type: "UserOnboarding",
    status: "completed",
    started_at: hoursAgo(10),
    duration_ms: 1890,
    last_event_type: "workflow_completed",
    last_event_at: hoursAgo(9.8),
  },
  {
    id: "wf_07ab5d63",
    workflow_id: "OrderProcessing-9740",
    type: "OrderProcessing",
    status: "failed",
    started_at: hoursAgo(3),
    duration_ms: 8120,
    last_event_type: "activity_failed",
    last_event_at: hoursAgo(2.7),
  },
  {
    id: "wf_6c3d9f12",
    workflow_id: "PaymentReconciliation-7651",
    type: "PaymentReconciliation",
    status: "failed",
    started_at: hoursAgo(14),
    duration_ms: 45230,
    last_event_type: "workflow_failed",
    last_event_at: hoursAgo(13),
  },
  {
    id: "wf_8e47ca30",
    workflow_id: "DataSync-4398",
    type: "DataSync",
    status: "cancelled",
    started_at: hoursAgo(18),
    duration_ms: 2100,
    last_event_type: "workflow_cancelled",
    last_event_at: hoursAgo(17.5),
  },
];

// ---------------------------------------------------------------------------
// Demo stats
// ---------------------------------------------------------------------------

const demoStats: StatsResponse = {
  data: {
    running: 3,
    completed: 847,
    failed: 12,
    cancelled: 2,
    avg_duration_ms: 4520,
  },
};

// ---------------------------------------------------------------------------
// Demo workflow detail (used when fetching a single workflow)
// ---------------------------------------------------------------------------

function buildDemoDetail(workflowId: string): WorkflowDetailResponse {
  const summary =
    demoWorkflows.find(
      (w) => w.id === workflowId || w.workflow_id === workflowId
    ) ?? demoWorkflows[0];

  const events: WorkflowEvent[] = [
    {
      sequence: 1,
      type: "workflow_started",
      timestamp: summary.started_at,
      data: { input: { orderId: "ORD-29841", customer: "acme-corp" } },
      duration_ms: null,
    },
    {
      sequence: 2,
      type: "activity_scheduled",
      timestamp: minutesAgo(55),
      data: { activity: "ValidateOrder", taskQueue: "default" },
      duration_ms: null,
    },
    {
      sequence: 3,
      type: "activity_started",
      timestamp: minutesAgo(54),
      data: { activity: "ValidateOrder", worker: "worker-01" },
      duration_ms: null,
    },
    {
      sequence: 4,
      type: "activity_completed",
      timestamp: minutesAgo(53),
      data: { activity: "ValidateOrder", result: { valid: true } },
      duration_ms: 1230,
    },
    {
      sequence: 5,
      type: "timer_started",
      timestamp: minutesAgo(53),
      data: { timerId: "cooldown", duration: "30s" },
      duration_ms: null,
    },
    {
      sequence: 6,
      type: "timer_fired",
      timestamp: minutesAgo(52),
      data: { timerId: "cooldown" },
      duration_ms: 30000,
    },
    {
      sequence: 7,
      type: "activity_scheduled",
      timestamp: minutesAgo(52),
      data: { activity: "ChargePayment", taskQueue: "payments" },
      duration_ms: null,
    },
    {
      sequence: 8,
      type: "activity_started",
      timestamp: minutesAgo(51),
      data: { activity: "ChargePayment", worker: "worker-03" },
      duration_ms: null,
    },
    {
      sequence: 9,
      type: "activity_completed",
      timestamp: minutesAgo(50),
      data: {
        activity: "ChargePayment",
        result: { transactionId: "txn_8f3a91b2" },
      },
      duration_ms: 2410,
    },
    {
      sequence: 10,
      type: summary.status === "completed"
        ? "workflow_completed"
        : summary.status === "failed"
          ? "workflow_failed"
          : summary.status === "cancelled"
            ? "workflow_cancelled"
            : "activity_started",
      timestamp: summary.last_event_at ?? minutesAgo(1),
      data:
        summary.status === "failed"
          ? { error: "PaymentGatewayTimeout: upstream did not respond within 30s" }
          : summary.status === "completed"
            ? { result: { orderId: "ORD-29841", shipped: true } }
            : null,
      duration_ms: summary.duration_ms,
    },
  ];

  return {
    data: {
      id: summary.id,
      workflow_id: summary.workflow_id,
      type: summary.type,
      run_id: `run_${summary.id.slice(3)}`,
      status: summary.status,
      input: { orderId: "ORD-29841", customer: "acme-corp" },
      output:
        summary.status === "completed"
          ? { orderId: "ORD-29841", shipped: true }
          : null,
      started_at: summary.started_at,
      completed_at:
        summary.status === "running" ? null : (summary.last_event_at ?? null),
      duration_ms: summary.duration_ms,
      parent_workflow_id: null,
      events,
      has_more_events: false,
      total_events: events.length,
    },
  };
}

// ---------------------------------------------------------------------------
// Demo events list (paginated)
// ---------------------------------------------------------------------------

function buildDemoEvents(): EventsListResponse {
  const detail = buildDemoDetail(demoWorkflows[0].id);
  return {
    data: detail.data.events,
    meta: {
      page: 1,
      per_page: 50,
      total: detail.data.events.length,
      has_more: false,
    },
  };
}

// ---------------------------------------------------------------------------
// Router — maps API path to demo response
// ---------------------------------------------------------------------------

export function getDemoResponse<T>(path: string): T {
  // GET /workflows/:id/events
  if (/^\/workflows\/[^/]+\/events/.test(path)) {
    return buildDemoEvents() as T;
  }

  // GET /workflows/:id
  const detailMatch = path.match(/^\/workflows\/([^/?]+)/);
  if (detailMatch && !path.includes("?") && !/\/(signal|retry|cancel)$/.test(path)) {
    return buildDemoDetail(detailMatch[1]) as T;
  }

  // GET /stats
  if (path.startsWith("/stats")) {
    return demoStats as T;
  }

  // GET /workflows (list) — default
  const response: WorkflowListResponse = {
    data: demoWorkflows,
    meta: {
      page: 1,
      per_page: 20,
      total: demoWorkflows.length,
      has_more: false,
    },
  };
  return response as T;
}
