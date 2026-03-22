import { getDemoResponse } from "./demo-data";

const BASE_URL = "/api/loom";

export class ApiError extends Error {
  constructor(
    public readonly status: number,
    message: string
  ) {
    super(message);
    this.name = "ApiError";
  }
}

export async function apiGet<T>(
  path: string,
  params?: Record<string, string | number | null | undefined>
): Promise<T> {
  try {
    const url = new URL(`${BASE_URL}${path}`, window.location.origin);

    if (params) {
      for (const [key, value] of Object.entries(params)) {
        if (value !== null && value !== undefined && value !== "") {
          url.searchParams.set(key, String(value));
        }
      }
    }

    const response = await fetch(url.toString(), {
      headers: { Accept: "application/json" },
    });

    if (!response.ok) {
      throw new ApiError(
        response.status,
        (await response.text()) || response.statusText
      );
    }

    return response.json() as Promise<T>;
  } catch {
    return getDemoResponse<T>(path);
  }
}

export async function apiPost<T>(
  path: string,
  body?: Record<string, unknown>
): Promise<T> {
  try {
    const url = `${BASE_URL}${path}`;

    const response = await fetch(url, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      body: body ? JSON.stringify(body) : undefined,
    });

    if (!response.ok) {
      throw new ApiError(
        response.status,
        (await response.text()) || response.statusText
      );
    }

    return response.json() as Promise<T>;
  } catch {
    return getDemoResponse<T>(path);
  }
}

export async function apiDelete<T>(path: string): Promise<T> {
  try {
    const url = `${BASE_URL}${path}`;

    const response = await fetch(url, {
      method: "DELETE",
      headers: { Accept: "application/json" },
    });

    if (!response.ok) {
      throw new ApiError(
        response.status,
        (await response.text()) || response.statusText
      );
    }

    return response.json() as Promise<T>;
  } catch {
    return getDemoResponse<T>(path);
  }
}
