const BASE_URL = process.env.NEXT_PUBLIC_CHRONOS_API_URL ?? "/api/chronos";

export class ApiRequestError extends Error {
  constructor(
    public readonly status: number,
    public readonly detail: string,
    public readonly title: string = "Error"
  ) {
    super(detail);
    this.name = "ApiRequestError";
  }
}

export async function apiFetch<T>(
  path: string,
  options: RequestInit = {}
): Promise<T> {
  const url = `${BASE_URL}${path}`;

  const response = await fetch(url, {
    ...options,
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
      ...options.headers,
    },
  });

  if (!response.ok) {
    let detail = `Request failed with status ${response.status}`;
    let title = "Error";
    try {
      const body = (await response.json()) as Record<string, unknown>;
      if (typeof body.detail === "string") detail = body.detail;
      if (typeof body.title === "string") title = body.title;
    } catch {
      // ignore parse errors
    }
    throw new ApiRequestError(response.status, detail, title);
  }

  return (await response.json()) as T;
}

export function buildQueryString(
  params: Record<string, string | number | boolean | undefined | null>
): string {
  const parts: string[] = [];
  for (const [key, value] of Object.entries(params)) {
    if (value !== undefined && value !== null && value !== "") {
      parts.push(
        `${encodeURIComponent(key)}=${encodeURIComponent(String(value))}`
      );
    }
  }
  return parts.length > 0 ? `?${parts.join("&")}` : "";
}
