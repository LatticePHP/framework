"use client"

import * as React from "react"
import type { Table } from "@tanstack/react-table"
import { XIcon, SlidersHorizontalIcon } from "lucide-react"
import { Input } from "@/components/ui/input"
import { Button } from "@/components/ui/button"
import {
  DropdownMenu,
  DropdownMenuCheckboxItem,
  DropdownMenuContent,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import { cn } from "@/lib/utils"

export type FacetedFilter = {
  columnId: string
  title: string
  options: { label: string; value: string }[]
}

export type DataTableToolbarProps<TData> = {
  table: Table<TData>
  /** Column ID to use for the global search filter */
  searchColumn?: string
  /** Placeholder text for search */
  searchPlaceholder?: string
  /** Faceted filter definitions */
  facetedFilters?: FacetedFilter[]
  /** Additional actions to show on the right */
  actions?: React.ReactNode
  className?: string
}

export function DataTableToolbar<TData>({
  table,
  searchColumn,
  searchPlaceholder = "Search...",
  facetedFilters,
  actions,
  className,
}: DataTableToolbarProps<TData>) {
  const isFiltered = table.getState().columnFilters.length > 0

  return (
    <div className={cn("flex items-center justify-between gap-2", className)}>
      <div className="flex flex-1 items-center gap-2">
        {searchColumn && (
          <Input
            placeholder={searchPlaceholder}
            value={
              (table
                .getColumn(searchColumn)
                ?.getFilterValue() as string) ?? ""
            }
            onChange={(event) =>
              table
                .getColumn(searchColumn)
                ?.setFilterValue(event.target.value)
            }
            className="h-8 w-40 lg:w-64"
          />
        )}
        {facetedFilters?.map((filter) => {
          const column = table.getColumn(filter.columnId)
          if (!column) return null

          const selectedValues = new Set(
            (column.getFilterValue() as string[]) ?? []
          )

          return (
            <DropdownMenu key={filter.columnId}>
              <DropdownMenuTrigger
                render={
                  <Button variant="outline" size="sm" className="border-dashed" />
                }
              >
                {filter.title}
                {selectedValues.size > 0 && (
                  <span className="ml-1 rounded-md bg-muted px-1.5 py-0.5 text-xs font-medium">
                    {selectedValues.size}
                  </span>
                )}
              </DropdownMenuTrigger>
              <DropdownMenuContent align="start">
                <DropdownMenuLabel>{filter.title}</DropdownMenuLabel>
                <DropdownMenuSeparator />
                {filter.options.map((option) => {
                  const isSelected = selectedValues.has(option.value)
                  return (
                    <DropdownMenuCheckboxItem
                      key={option.value}
                      checked={isSelected}
                      onCheckedChange={() => {
                        const next = new Set(selectedValues)
                        if (isSelected) {
                          next.delete(option.value)
                        } else {
                          next.add(option.value)
                        }
                        const filterValues = Array.from(next)
                        column.setFilterValue(
                          filterValues.length > 0 ? filterValues : undefined
                        )
                      }}
                    >
                      {option.label}
                    </DropdownMenuCheckboxItem>
                  )
                })}
              </DropdownMenuContent>
            </DropdownMenu>
          )
        })}
        {isFiltered && (
          <Button
            variant="ghost"
            size="sm"
            onClick={() => table.resetColumnFilters()}
          >
            Reset
            <XIcon data-icon="inline-end" />
          </Button>
        )}
      </div>
      <div className="flex items-center gap-2">
        {actions}
        <DropdownMenu>
          <DropdownMenuTrigger
            render={<Button variant="outline" size="sm" />}
          >
            <SlidersHorizontalIcon data-icon="inline-start" />
            Columns
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            <DropdownMenuLabel>Toggle columns</DropdownMenuLabel>
            <DropdownMenuSeparator />
            {table
              .getAllColumns()
              .filter(
                (column) =>
                  typeof column.accessorFn !== "undefined" &&
                  column.getCanHide()
              )
              .map((column) => (
                <DropdownMenuCheckboxItem
                  key={column.id}
                  checked={column.getIsVisible()}
                  onCheckedChange={(value) =>
                    column.toggleVisibility(!!value)
                  }
                >
                  {typeof column.columnDef.header === "string"
                    ? column.columnDef.header
                    : column.id}
                </DropdownMenuCheckboxItem>
              ))}
          </DropdownMenuContent>
        </DropdownMenu>
      </div>
    </div>
  )
}
