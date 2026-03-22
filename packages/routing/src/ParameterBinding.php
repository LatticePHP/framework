<?php

declare(strict_types=1);

namespace Lattice\Routing;

final readonly class ParameterBinding
{
    /**
     * @param string $type One of: body, query, param, header, current_user
     * @param string $parameterName The PHP parameter name
     * @param string|null $name The binding name (query key, param name, header name)
     * @param string|null $parameterType The PHP type hint (e.g. 'int', 'string', or FQCN)
     * @param bool $hasDefault Whether the parameter has a default value
     * @param mixed $defaultValue The default value if any
     */
    public function __construct(
        public string $type,
        public string $parameterName,
        public ?string $name = null,
        public ?string $parameterType = null,
        public bool $hasDefault = false,
        public mixed $defaultValue = null,
    ) {}
}
