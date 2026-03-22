"use client"

import { AlertCircleIcon, RefreshCwIcon } from "lucide-react"
import {
  Alert,
  AlertTitle,
  AlertDescription,
} from "@/components/ui/alert"
import { Button } from "@/components/ui/button"
import { cn } from "@/lib/utils"

export type ErrorStateProps = {
  title?: string
  message?: string
  onRetry?: () => void
  className?: string
}

export function ErrorState({
  title = "Something went wrong",
  message = "An unexpected error occurred. Please try again.",
  onRetry,
  className,
}: ErrorStateProps) {
  return (
    <Alert variant="destructive" className={cn(className)}>
      <AlertCircleIcon />
      <AlertTitle>{title}</AlertTitle>
      <AlertDescription>
        <div className="flex flex-col gap-3">
          <p>{message}</p>
          {onRetry && (
            <Button
              variant="destructive"
              size="sm"
              onClick={onRetry}
              className="w-fit"
            >
              <RefreshCwIcon data-icon="inline-start" />
              Retry
            </Button>
          )}
        </div>
      </AlertDescription>
    </Alert>
  )
}
