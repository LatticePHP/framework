"use client"

import { useState, useCallback } from "react"

export type RefreshInterval = 0 | 5000 | 15000 | 30000 | 60000

export const REFRESH_INTERVALS: { label: string; value: RefreshInterval }[] = [
  { label: "Off", value: 0 },
  { label: "5s", value: 5000 },
  { label: "15s", value: 15000 },
  { label: "30s", value: 30000 },
  { label: "60s", value: 60000 },
]

/**
 * Returns the current refresh interval and a setter.
 * Use `refetchInterval` with TanStack Query's refetchInterval option.
 *
 * @example
 * ```ts
 * const { refetchInterval, interval, setInterval } = useAutoRefresh()
 * const { data } = useQuery({ queryKey: ["data"], queryFn: fetchData, refetchInterval })
 * ```
 */
export function useAutoRefresh(defaultInterval: RefreshInterval = 0) {
  const [interval, setInterval] = useState<RefreshInterval>(defaultInterval)

  const handleSetInterval = useCallback((value: RefreshInterval) => {
    setInterval(value)
  }, [])

  return {
    /** Pass this to TanStack Query's refetchInterval. false means disabled. */
    refetchInterval: interval === 0 ? false : (interval as number),
    /** The raw interval value in ms (0 = off) */
    interval,
    /** Set the interval */
    setInterval: handleSetInterval,
    /** Whether auto-refresh is active */
    isActive: interval > 0,
  }
}
