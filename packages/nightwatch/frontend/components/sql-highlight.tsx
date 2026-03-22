import React from "react";
import { cn } from "@/lib/utils";

interface SqlHighlightProps {
  sql: string;
  truncate?: number;
  className?: string;
}

const SQL_KEYWORDS = [
  "SELECT", "FROM", "WHERE", "AND", "OR", "INSERT", "INTO", "VALUES",
  "UPDATE", "SET", "DELETE", "JOIN", "LEFT", "RIGHT", "INNER", "OUTER",
  "ON", "AS", "IN", "NOT", "NULL", "IS", "LIKE", "BETWEEN", "EXISTS",
  "HAVING", "GROUP", "BY", "ORDER", "LIMIT", "OFFSET", "UNION", "ALL",
  "DISTINCT", "COUNT", "SUM", "AVG", "MIN", "MAX", "CREATE", "TABLE",
  "ALTER", "DROP", "INDEX", "PRIMARY", "KEY", "FOREIGN", "REFERENCES",
  "CASCADE", "ASC", "DESC", "CASE", "WHEN", "THEN", "ELSE", "END",
  "WITH", "RECURSIVE", "RETURNING",
];

function highlightSql(sql: string): React.ReactNode[] {
  const parts: React.ReactNode[] = [];
  const pattern = new RegExp(
    `(${SQL_KEYWORDS.join("|")})|('(?:[^'\\\\]|\\\\.)*')|(-?\\d+(?:\\.\\d+)?)|(--.*)`,
    "gi",
  );

  let lastIndex = 0;
  let match: RegExpExecArray | null;

  while ((match = pattern.exec(sql)) !== null) {
    if (match.index > lastIndex) {
      parts.push(sql.slice(lastIndex, match.index));
    }

    const text = match[0]!;

    if (match[1]) {
      parts.push(
        <span key={match.index} className="sql-keyword">{text}</span>,
      );
    } else if (match[2]) {
      parts.push(
        <span key={match.index} className="sql-string">{text}</span>,
      );
    } else if (match[3]) {
      parts.push(
        <span key={match.index} className="sql-number">{text}</span>,
      );
    } else if (match[4]) {
      parts.push(
        <span key={match.index} className="sql-comment">{text}</span>,
      );
    }

    lastIndex = match.index + text.length;
  }

  if (lastIndex < sql.length) {
    parts.push(sql.slice(lastIndex));
  }

  return parts;
}

export default function SqlHighlight({ sql, truncate, className }: SqlHighlightProps) {
  const displaySql =
    truncate && sql.length > truncate ? sql.slice(0, truncate) + "..." : sql;

  return (
    <code
      className={cn(
        "font-mono text-xs whitespace-pre-wrap break-all",
        className,
      )}
    >
      {highlightSql(displaySql)}
    </code>
  );
}
