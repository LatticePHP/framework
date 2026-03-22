import { type ZodType } from "zod"

export class ApiError extends Error {
  constructor(
    public status: number,
    public statusText: string,
    public body: unknown
  ) {
    super(`API Error ${status}: ${statusText}`)
    this.name = "ApiError"
  }
}

export type RequestOptions<T = unknown> = {
  method?: "GET" | "POST" | "PUT" | "PATCH" | "DELETE"
  headers?: Record<string, string>
  body?: unknown
  schema?: ZodType<T>
  signal?: AbortSignal
  params?: Record<string, string | number | boolean | undefined>
}

function buildUrl(
  base: string,
  params?: Record<string, string | number | boolean | undefined>
): string {
  if (!params) return base
  const searchParams = new URLSearchParams()
  for (const [key, value] of Object.entries(params)) {
    if (value !== undefined) {
      searchParams.set(key, String(value))
    }
  }
  const qs = searchParams.toString()
  return qs ? `${base}?${qs}` : base
}

/**
 * Typed fetch wrapper with error handling and optional Zod validation.
 *
 * @example
 * ```ts
 * const data = await apiClient<User[]>("/api/users", {
 *   schema: z.array(userSchema),
 *   params: { page: 1, limit: 10 },
 * })
 * ```
 */
export async function apiClient<T = unknown>(
  url: string,
  options: RequestOptions<T> = {}
): Promise<T> {
  const { method = "GET", headers, body, schema, signal, params } = options

  const fullUrl = buildUrl(url, params)

  const fetchOptions: RequestInit = {
    method,
    signal,
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
      ...headers,
    },
  }

  if (body !== undefined && method !== "GET") {
    fetchOptions.body = JSON.stringify(body)
  }

  const response = await fetch(fullUrl, fetchOptions)

  if (!response.ok) {
    let errorBody: unknown
    try {
      errorBody = await response.json()
    } catch {
      errorBody = await response.text().catch(() => null)
    }
    throw new ApiError(response.status, response.statusText, errorBody)
  }

  const contentType = response.headers.get("content-type")
  if (
    response.status === 204 ||
    !contentType?.includes("application/json")
  ) {
    return undefined as T
  }

  const data = await response.json()

  if (schema) {
    return schema.parse(data)
  }

  return data as T
}
