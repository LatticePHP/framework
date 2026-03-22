"use client"

import * as React from "react"
import { cn } from "@/lib/utils"

const SQL_KEYWORDS = [
  "SELECT",
  "FROM",
  "WHERE",
  "AND",
  "OR",
  "NOT",
  "IN",
  "IS",
  "NULL",
  "LIKE",
  "BETWEEN",
  "EXISTS",
  "INSERT",
  "INTO",
  "VALUES",
  "UPDATE",
  "SET",
  "DELETE",
  "CREATE",
  "ALTER",
  "DROP",
  "TABLE",
  "INDEX",
  "VIEW",
  "JOIN",
  "INNER",
  "LEFT",
  "RIGHT",
  "OUTER",
  "FULL",
  "CROSS",
  "ON",
  "AS",
  "ORDER",
  "BY",
  "GROUP",
  "HAVING",
  "LIMIT",
  "OFFSET",
  "UNION",
  "ALL",
  "DISTINCT",
  "CASE",
  "WHEN",
  "THEN",
  "ELSE",
  "END",
  "ASC",
  "DESC",
  "COUNT",
  "SUM",
  "AVG",
  "MIN",
  "MAX",
  "WITH",
  "RECURSIVE",
  "CASCADE",
  "CONSTRAINT",
  "PRIMARY",
  "KEY",
  "FOREIGN",
  "REFERENCES",
  "DEFAULT",
  "BEGIN",
  "COMMIT",
  "ROLLBACK",
  "TRANSACTION",
  "TRUE",
  "FALSE",
]

type Token = {
  type: "keyword" | "string" | "number" | "comment" | "operator" | "text"
  value: string
}

function tokenizeSql(sql: string): Token[] {
  const tokens: Token[] = []
  let i = 0

  while (i < sql.length) {
    // Single-line comment
    if (sql[i] === "-" && sql[i + 1] === "-") {
      const end = sql.indexOf("\n", i)
      const value = end === -1 ? sql.slice(i) : sql.slice(i, end)
      tokens.push({ type: "comment", value })
      i += value.length
      continue
    }

    // Multi-line comment
    if (sql[i] === "/" && sql[i + 1] === "*") {
      const end = sql.indexOf("*/", i + 2)
      const value = end === -1 ? sql.slice(i) : sql.slice(i, end + 2)
      tokens.push({ type: "comment", value })
      i += value.length
      continue
    }

    // String literal (single quote)
    if (sql[i] === "'") {
      let j = i + 1
      while (j < sql.length && sql[j] !== "'") {
        if (sql[j] === "\\") j++
        j++
      }
      const value = sql.slice(i, j + 1)
      tokens.push({ type: "string", value })
      i = j + 1
      continue
    }

    // Number
    if (/\d/.test(sql[i])) {
      let j = i
      while (j < sql.length && /[\d.]/.test(sql[j])) j++
      tokens.push({ type: "number", value: sql.slice(i, j) })
      i = j
      continue
    }

    // Word (keyword or identifier)
    if (/[a-zA-Z_]/.test(sql[i])) {
      let j = i
      while (j < sql.length && /[a-zA-Z0-9_]/.test(sql[j])) j++
      const word = sql.slice(i, j)
      const isKeyword = SQL_KEYWORDS.includes(word.toUpperCase())
      tokens.push({
        type: isKeyword ? "keyword" : "text",
        value: word,
      })
      i = j
      continue
    }

    // Operators
    if ("=<>!+*/%".includes(sql[i])) {
      tokens.push({ type: "operator", value: sql[i] })
      i++
      continue
    }

    // Everything else (whitespace, punctuation)
    tokens.push({ type: "text", value: sql[i] })
    i++
  }

  return tokens
}

export type SqlHighlightProps = {
  sql: string
  className?: string
}

export function SqlHighlight({ sql, className }: SqlHighlightProps) {
  const tokens = React.useMemo(() => tokenizeSql(sql), [sql])

  return (
    <code className={cn("font-mono text-sm", className)}>
      {tokens.map((token, i) => (
        <span
          key={i}
          className={cn({
            "font-bold text-primary": token.type === "keyword",
            "text-accent-foreground": token.type === "string",
            "text-primary": token.type === "number",
            "text-muted-foreground italic": token.type === "comment",
            "text-muted-foreground": token.type === "operator",
            "text-foreground": token.type === "text",
          })}
        >
          {token.value}
        </span>
      ))}
    </code>
  )
}
