<?php

declare(strict_types=1);

namespace Lattice\DevTools\Console;

final class Output
{
    /** @var list<string> */
    private array $buffer = [];

    public function info(string $message): void
    {
        $this->buffer[] = "[INFO] {$message}";
    }

    public function error(string $message): void
    {
        $this->buffer[] = "[ERROR] {$message}";
    }

    public function success(string $message): void
    {
        $this->buffer[] = "[SUCCESS] {$message}";
    }

    public function warning(string $message): void
    {
        $this->buffer[] = "[WARNING] {$message}";
    }

    /**
     * @param string[] $headers
     * @param array<int, array<int, string>> $rows
     */
    public function table(array $headers, array $rows): void
    {
        $allRows = array_merge([$headers], $rows);
        $colWidths = [];

        foreach ($allRows as $row) {
            foreach ($row as $i => $cell) {
                $colWidths[$i] = max($colWidths[$i] ?? 0, mb_strlen($cell));
            }
        }

        $separator = '+' . implode('+', array_map(
            fn (int $w): string => str_repeat('-', $w + 2),
            $colWidths,
        )) . '+';

        $this->buffer[] = $separator;

        foreach ($allRows as $index => $row) {
            $cells = [];
            foreach ($row as $i => $cell) {
                $cells[] = ' ' . str_pad($cell, $colWidths[$i]) . ' ';
            }
            $this->buffer[] = '|' . implode('|', $cells) . '|';

            if ($index === 0) {
                $this->buffer[] = $separator;
            }
        }

        $this->buffer[] = $separator;
    }

    public function line(string $message): void
    {
        $this->buffer[] = $message;
    }

    /** @return list<string> */
    public function getBuffer(): array
    {
        return $this->buffer;
    }

    public function flush(): string
    {
        $output = implode(PHP_EOL, $this->buffer);
        $this->buffer = [];
        return $output;
    }
}
