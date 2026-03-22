"use client"

import { useCallback, useState } from "react"
import { toast } from "sonner"

/**
 * Provides a function to copy text to clipboard with toast feedback.
 *
 * @example
 * ```tsx
 * const { copy, copied } = useCopyToClipboard()
 * <Button onClick={() => copy(someText)}>Copy</Button>
 * ```
 */
export function useCopyToClipboard(resetDelay = 2000) {
  const [copied, setCopied] = useState(false)

  const copy = useCallback(
    async (text: string) => {
      try {
        await navigator.clipboard.writeText(text)
        setCopied(true)
        toast.success("Copied to clipboard")
        setTimeout(() => setCopied(false), resetDelay)
      } catch {
        toast.error("Failed to copy to clipboard")
        setCopied(false)
      }
    },
    [resetDelay]
  )

  return { copy, copied }
}
