<?php

declare(strict_types=1);

namespace Lattice\Validation;

final class FieldError
{
    public function __construct(
        public readonly string $field,
        public readonly string $message,
        public readonly string $rule,
        public readonly mixed $value = null,
    ) {}

    /**
     * @return array{field: string, message: string, rule: string}
     */
    public function toArray(): array
    {
        return [
            'field' => $this->field,
            'message' => $this->message,
            'rule' => $this->rule,
        ];
    }
}
