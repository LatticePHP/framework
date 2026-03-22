<?php

declare(strict_types=1);

namespace Lattice\Prism\Event;

final class StackFrame
{
    /**
     * @param array{pre: list<string>, line: string, post: list<string>}|null $codeContext
     */
    public function __construct(
        public readonly string $file,
        public readonly int $line,
        public readonly ?string $function = null,
        public readonly ?string $class = null,
        public readonly ?string $module = null,
        public readonly ?int $column = null,
        public readonly ?array $codeContext = null,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            file: (string) ($data['file'] ?? ''),
            line: (int) ($data['line'] ?? 0),
            function: isset($data['function']) ? (string) $data['function'] : null,
            class: isset($data['class']) ? (string) $data['class'] : null,
            module: isset($data['module']) ? (string) $data['module'] : null,
            column: isset($data['column']) ? (int) $data['column'] : null,
            codeContext: isset($data['code_context']) && is_array($data['code_context']) ? $data['code_context'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'file' => $this->file,
            'line' => $this->line,
        ];

        if ($this->function !== null) {
            $data['function'] = $this->function;
        }

        if ($this->class !== null) {
            $data['class'] = $this->class;
        }

        if ($this->module !== null) {
            $data['module'] = $this->module;
        }

        if ($this->column !== null) {
            $data['column'] = $this->column;
        }

        if ($this->codeContext !== null) {
            $data['code_context'] = $this->codeContext;
        }

        return $data;
    }
}
