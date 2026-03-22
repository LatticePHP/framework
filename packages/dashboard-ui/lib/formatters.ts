/**
 * Format a duration in milliseconds to a human-readable string.
 * Examples: "0.5ms", "123ms", "1.5s", "2m 30s", "1h 15m"
 */
export function formatDuration(ms: number): string {
  if (ms < 1) {
    return `${parseFloat(ms.toFixed(2))}ms`
  }
  if (ms < 1000) {
    return `${parseFloat(ms.toFixed(1))}ms`
  }
  const seconds = ms / 1000
  if (seconds < 60) {
    return `${parseFloat(seconds.toFixed(1))}s`
  }
  const minutes = Math.floor(seconds / 60)
  const remainingSeconds = Math.round(seconds % 60)
  if (minutes < 60) {
    return remainingSeconds > 0
      ? `${minutes}m ${remainingSeconds}s`
      : `${minutes}m`
  }
  const hours = Math.floor(minutes / 60)
  const remainingMinutes = minutes % 60
  return remainingMinutes > 0
    ? `${hours}h ${remainingMinutes}m`
    : `${hours}h`
}

/**
 * Format bytes to a human-readable string.
 * Examples: "0 B", "1.5 KB", "3.2 MB", "1.1 GB"
 */
export function formatBytes(bytes: number, decimals = 1): string {
  if (bytes === 0) return "0 B"
  const isNegative = bytes < 0
  const absBytes = Math.abs(bytes)
  const k = 1024
  const sizes = ["B", "KB", "MB", "GB", "TB", "PB"]
  const i = Math.floor(Math.log(absBytes) / Math.log(k))
  const value = absBytes / Math.pow(k, i)
  const formatted = `${parseFloat(value.toFixed(decimals))} ${sizes[i]}`
  return isNegative ? `-${formatted}` : formatted
}

/**
 * Format a number with K/M/B suffixes.
 * Examples: "999", "1.2K", "3.5M", "1.1B"
 */
export function formatNumber(num: number, decimals = 1): string {
  if (Math.abs(num) < 1000) {
    return num.toString()
  }
  const suffixes = ["", "K", "M", "B", "T"]
  const i = Math.floor(Math.log10(Math.abs(num)) / 3)
  const value = num / Math.pow(1000, i)
  return `${parseFloat(value.toFixed(decimals))}${suffixes[i]}`
}

/**
 * Format a date to a locale string.
 */
export function formatDate(
  date: Date | string | number,
  options?: Intl.DateTimeFormatOptions
): string {
  const d = date instanceof Date ? date : new Date(date)
  return d.toLocaleDateString(undefined, {
    year: "numeric",
    month: "short",
    day: "numeric",
    ...options,
  })
}

/**
 * Format a date to a locale datetime string.
 */
export function formatDateTime(
  date: Date | string | number,
  options?: Intl.DateTimeFormatOptions
): string {
  const d = date instanceof Date ? date : new Date(date)
  return d.toLocaleString(undefined, {
    year: "numeric",
    month: "short",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
    second: "2-digit",
    ...options,
  })
}

/**
 * Format a relative time string.
 * Examples: "just now", "5s ago", "3m ago", "2h ago", "5d ago"
 */
export function formatRelativeTime(date: Date | string | number): string {
  const d = date instanceof Date ? date : new Date(date)
  const now = new Date()
  const diffMs = now.getTime() - d.getTime()
  const absDiff = Math.abs(diffMs)
  const sign = diffMs < 0 ? "in " : ""
  const suffix = diffMs < 0 ? "" : " ago"

  if (absDiff < 5000) return "just now"
  if (absDiff < 60000) return `${sign}${Math.floor(absDiff / 1000)}s${suffix}`
  if (absDiff < 3600000)
    return `${sign}${Math.floor(absDiff / 60000)}m${suffix}`
  if (absDiff < 86400000)
    return `${sign}${Math.floor(absDiff / 3600000)}h${suffix}`
  if (absDiff < 2592000000)
    return `${sign}${Math.floor(absDiff / 86400000)}d${suffix}`
  return formatDate(d)
}
