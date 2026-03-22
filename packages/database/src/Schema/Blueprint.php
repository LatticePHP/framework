<?php

declare(strict_types=1);

namespace Lattice\Database\Schema;

final class Blueprint
{
    /** @var array<int, array<string, mixed>> */
    private array $columns = [];

    /** @var array<int, array{column: string, references: string, on: string}> */
    private array $foreignKeys = [];

    /** Track current foreign key being built */
    private ?int $currentForeignKeyIndex = null;

    public function __construct(
        private readonly string $table,
    ) {}

    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * @return array<int, array{column: string, references: string, on: string}>
     */
    public function getForeignKeys(): array
    {
        return $this->foreignKeys;
    }

    public function id(string $column = 'id'): self
    {
        $this->columns[] = [
            'name' => $column,
            'type' => 'integer',
            'autoIncrement' => true,
            'primary' => true,
            'nullable' => false,
        ];

        return $this;
    }

    public function string(string $column, int $length = 255): self
    {
        $this->columns[] = [
            'name' => $column,
            'type' => 'string',
            'length' => $length,
            'nullable' => false,
        ];

        return $this;
    }

    public function text(string $column): self
    {
        $this->columns[] = [
            'name' => $column,
            'type' => 'text',
            'nullable' => false,
        ];

        return $this;
    }

    public function integer(string $column): self
    {
        $this->columns[] = [
            'name' => $column,
            'type' => 'integer',
            'nullable' => false,
        ];

        return $this;
    }

    public function bigInteger(string $column): self
    {
        $this->columns[] = [
            'name' => $column,
            'type' => 'bigInteger',
            'nullable' => false,
        ];

        return $this;
    }

    public function float(string $column): self
    {
        $this->columns[] = [
            'name' => $column,
            'type' => 'float',
            'nullable' => false,
        ];

        return $this;
    }

    public function boolean(string $column): self
    {
        $this->columns[] = [
            'name' => $column,
            'type' => 'boolean',
            'nullable' => false,
        ];

        return $this;
    }

    public function timestamp(string $column): self
    {
        $this->columns[] = [
            'name' => $column,
            'type' => 'timestamp',
            'nullable' => false,
        ];

        return $this;
    }

    public function timestamps(): self
    {
        $this->columns[] = [
            'name' => 'created_at',
            'type' => 'timestamp',
            'nullable' => true,
        ];

        $this->columns[] = [
            'name' => 'updated_at',
            'type' => 'timestamp',
            'nullable' => true,
        ];

        return $this;
    }

    public function json(string $column): self
    {
        $this->columns[] = [
            'name' => $column,
            'type' => 'json',
            'nullable' => false,
        ];

        return $this;
    }

    public function foreignId(string $column): self
    {
        $this->columns[] = [
            'name' => $column,
            'type' => 'bigInteger',
            'nullable' => false,
        ];

        $this->foreignKeys[] = [
            'column' => $column,
            'references' => '',
            'on' => '',
        ];

        $this->currentForeignKeyIndex = count($this->foreignKeys) - 1;

        return $this;
    }

    public function references(string $column): self
    {
        if ($this->currentForeignKeyIndex !== null) {
            $this->foreignKeys[$this->currentForeignKeyIndex]['references'] = $column;
        }

        return $this;
    }

    public function on(string $table): self
    {
        if ($this->currentForeignKeyIndex !== null) {
            $this->foreignKeys[$this->currentForeignKeyIndex]['on'] = $table;
        }

        return $this;
    }

    // Modifiers — apply to the last column added

    public function nullable(): self
    {
        $this->modifyLastColumn('nullable', true);
        return $this;
    }

    public function default(mixed $value): self
    {
        $this->modifyLastColumn('default', $value);
        return $this;
    }

    public function unique(): self
    {
        $this->modifyLastColumn('unique', true);
        return $this;
    }

    public function index(): self
    {
        $this->modifyLastColumn('index', true);
        return $this;
    }

    public function primary(): self
    {
        $this->modifyLastColumn('primary', true);
        return $this;
    }

    private function modifyLastColumn(string $key, mixed $value): void
    {
        $lastIndex = count($this->columns) - 1;
        if ($lastIndex >= 0) {
            $this->columns[$lastIndex][$key] = $value;
        }
    }
}
