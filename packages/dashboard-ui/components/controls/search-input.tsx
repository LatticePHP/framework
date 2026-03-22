"use client"

import * as React from "react"
import { SearchIcon, XIcon } from "lucide-react"
import { Input } from "@/components/ui/input"
import { Button } from "@/components/ui/button"
import { useDebounce } from "@/hooks/use-debounce"
import { cn } from "@/lib/utils"

export type SearchInputProps = {
  value?: string
  onChange: (value: string) => void
  placeholder?: string
  /** Debounce delay in ms (default: 300) */
  debounce?: number
  className?: string
}

export function SearchInput({
  value: controlledValue,
  onChange,
  placeholder = "Search...",
  debounce = 300,
  className,
}: SearchInputProps) {
  const [internalValue, setInternalValue] = React.useState(
    controlledValue ?? ""
  )
  const debouncedValue = useDebounce(internalValue, debounce)
  const isControlled = controlledValue !== undefined

  // Sync controlled value
  React.useEffect(() => {
    if (isControlled) {
      setInternalValue(controlledValue)
    }
  }, [controlledValue, isControlled])

  // Emit debounced value
  React.useEffect(() => {
    onChange(debouncedValue)
  }, [debouncedValue, onChange])

  const handleClear = React.useCallback(() => {
    setInternalValue("")
    onChange("")
  }, [onChange])

  return (
    <div className={cn("relative flex items-center", className)}>
      <SearchIcon className="absolute left-2.5 size-4 text-muted-foreground" />
      <Input
        value={internalValue}
        onChange={(e) => setInternalValue(e.target.value)}
        placeholder={placeholder}
        className="pl-8 pr-8"
      />
      {internalValue && (
        <Button
          variant="ghost"
          size="icon-xs"
          className="absolute right-1"
          onClick={handleClear}
        >
          <XIcon className="size-3" />
          <span className="sr-only">Clear search</span>
        </Button>
      )}
    </div>
  )
}
