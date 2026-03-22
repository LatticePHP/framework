<?php

declare(strict_types=1);

namespace Lattice\Http;

use Lattice\Contracts\Context\PrincipalInterface;
use Lattice\Http\Exception\BadRequestException;
use Lattice\Http\Exception\NotFoundException;
use Lattice\Http\Exception\UnauthorizedException;
use Lattice\Routing\ParameterBinding;
use Lattice\Routing\RouteDefinition;
use Lattice\Validation\DtoMapper;
use Lattice\Validation\Exceptions\MappingException;
use Lattice\Validation\Exceptions\ValidationException;
use Lattice\Validation\FieldError;
use Lattice\Validation\ValidationResult;
use Lattice\Validation\Validator;

final class ParameterResolver
{
    private readonly DtoMapper $dtoMapper;
    private readonly Validator $validator;
    private readonly ModelResolver $modelResolver;

    public function __construct(
        ?DtoMapper $dtoMapper = null,
        ?Validator $validator = null,
        ?ModelResolver $modelResolver = null,
    ) {
        $this->dtoMapper = $dtoMapper ?? new DtoMapper();
        $this->validator = $validator ?? new Validator();
        $this->modelResolver = $modelResolver ?? new ModelResolver();
    }

    /**
     * Resolve method parameters using binding attributes.
     *
     * @return array<string, mixed> Map of parameter name to resolved value
     */
    public function resolve(Request $request, RouteDefinition $route, ?PrincipalInterface $principal): array
    {
        $params = [];

        // Enrich bindings with type info from reflection if missing
        $enrichedBindings = $this->enrichBindingsWithTypes($route);

        // Build a set of parameter names that have explicit bindings
        $boundNames = [];
        foreach ($enrichedBindings as $binding) {
            $boundNames[$binding->parameterName] = true;
            $params[$binding->parameterName] = match ($binding->type) {
                'body' => $this->resolveBody($request, $binding),
                'query' => $this->resolveQuery($request, $binding),
                'param' => $this->resolveParam($request, $binding),
                'header' => $this->resolveHeader($request, $binding),
                'current_user' => $this->resolveCurrentUser($principal),
                default => null,
            };
        }

        // Auto-inject unbound parameters typed as Request or PrincipalInterface
        if (class_exists($route->controllerClass)) {
            try {
                $ref = new \ReflectionMethod($route->controllerClass, $route->methodName);
                foreach ($ref->getParameters() as $refParam) {
                    $name = $refParam->getName();
                    if (isset($boundNames[$name])) {
                        continue;
                    }
                    $type = $refParam->getType();
                    if (!$type instanceof \ReflectionNamedType) {
                        continue;
                    }
                    $typeName = $type->getName();
                    if ($typeName === Request::class || $typeName === 'Lattice\\Http\\Request') {
                        $params[$name] = $request;
                    } elseif (is_a($typeName, PrincipalInterface::class, true)) {
                        $params[$name] = $principal;
                    }
                }
            } catch (\ReflectionException) {
                // ignore
            }
        }

        return $params;
    }

    /**
     * If parameterType is missing from bindings, use reflection to fill it in.
     */
    private function enrichBindingsWithTypes(RouteDefinition $route): array
    {
        if (!class_exists($route->controllerClass)) {
            return $route->parameterBindings;
        }

        try {
            $ref = new \ReflectionMethod($route->controllerClass, $route->methodName);
        } catch (\ReflectionException) {
            return $route->parameterBindings;
        }

        $refParams = [];
        foreach ($ref->getParameters() as $p) {
            $refParams[$p->getName()] = $p;
        }

        $enriched = [];
        foreach ($route->parameterBindings as $binding) {
            if (($binding->parameterType === null || $binding->parameterType === 'unknown') && isset($refParams[$binding->parameterName])) {
                $refParam = $refParams[$binding->parameterName];
                $type = $refParam->getType();
                $typeName = $type instanceof \ReflectionNamedType ? $type->getName() : null;
                $enriched[] = new ParameterBinding(
                    type: $binding->type,
                    parameterName: $binding->parameterName,
                    name: $binding->name,
                    parameterType: $typeName,
                    hasDefault: $refParam->isDefaultValueAvailable(),
                    defaultValue: $refParam->isDefaultValueAvailable() ? $refParam->getDefaultValue() : null,
                );
            } else {
                $enriched[] = $binding;
            }
        }

        return $enriched;
    }

    /**
     * Resolve a #[Body] parameter: deserialize JSON body to DTO and validate.
     */
    private function resolveBody(Request $request, ParameterBinding $binding): mixed
    {
        $body = $request->getBody();
        $type = $binding->parameterType;

        // No type hint or primitive array — return raw body
        if ($type === null || $type === 'array' || $type === 'mixed') {
            return $body;
        }

        // DTO class — map and validate
        if (class_exists($type)) {
            if (!is_array($body)) {
                throw new BadRequestException('Request body must be a JSON object');
            }

            try {
                $dto = $this->dtoMapper->map($body, $type);
            } catch (MappingException $e) {
                // Convert "Missing required field" errors to 422 validation errors
                if (preg_match("/Missing required field '(\w+)'/", $e->getMessage(), $matches)) {
                    $fieldName = $matches[1];
                    $result = new ValidationResult([
                        new FieldError(
                            field: $fieldName,
                            message: "The {$fieldName} field is required.",
                            rule: 'required',
                        ),
                    ]);
                    throw new ValidationException($result);
                }
                throw new BadRequestException('Failed to map request body: ' . $e->getMessage(), $e);
            }

            $result = $this->validator->validate($dto);

            if (!$result->isValid()) {
                throw new ValidationException($result);
            }

            return $dto;
        }

        return $body;
    }

    /**
     * Resolve a #[Query] parameter: type-coerce primitives or map to DTO.
     */
    private function resolveQuery(Request $request, ParameterBinding $binding): mixed
    {
        $key = $binding->name ?? $binding->parameterName;
        $type = $binding->parameterType;

        // DTO class — map all query params to DTO constructor
        if ($type !== null && class_exists($type)) {
            try {
                $dto = $this->dtoMapper->map($request->query, $type);
            } catch (MappingException $e) {
                throw new BadRequestException('Failed to map query parameters: ' . $e->getMessage(), $e);
            }

            $result = $this->validator->validate($dto);

            if (!$result->isValid()) {
                throw new ValidationException($result);
            }

            return $dto;
        }

        $value = $request->getQuery($key);

        // If null and has default, return default
        if ($value === null && $binding->hasDefault) {
            return $binding->defaultValue;
        }

        if ($value === null) {
            return null;
        }

        return $this->coercePrimitive($value, $type);
    }

    /**
     * Resolve a #[Param] parameter: cast to primitive or resolve Eloquent model.
     */
    private function resolveParam(Request $request, ParameterBinding $binding): mixed
    {
        $key = $binding->name ?? $binding->parameterName;
        $value = $request->getParam($key);
        $type = $binding->parameterType;

        if ($value === null) {
            if ($binding->hasDefault) {
                return $binding->defaultValue;
            }
            return null;
        }

        // Model binding: if the type is a class, try to resolve from DB
        if ($type !== null && class_exists($type) && !$this->isScalarType($type)) {
            return $this->modelResolver->resolve($type, $value);
        }

        return $this->coercePrimitive($value, $type);
    }

    /**
     * Resolve a #[Header] parameter.
     */
    private function resolveHeader(Request $request, ParameterBinding $binding): mixed
    {
        $value = $request->getHeader($binding->name);

        if ($value === null && $binding->hasDefault) {
            return $binding->defaultValue;
        }

        return $value;
    }

    /**
     * Resolve a #[CurrentUser] parameter.
     *
     * @throws UnauthorizedException if no principal is available
     */
    private function resolveCurrentUser(?PrincipalInterface $principal): PrincipalInterface
    {
        if ($principal === null) {
            throw new UnauthorizedException('Authentication required');
        }

        return $principal;
    }

    /**
     * Coerce a string value to the target primitive type.
     */
    private function coercePrimitive(mixed $value, ?string $type): mixed
    {
        if ($type === null) {
            return $value;
        }

        return match ($type) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $value,
            'string' => (string) $value,
            default => $value,
        };
    }

    /**
     * Check if the given type name is a scalar/built-in type.
     */
    private function isScalarType(string $type): bool
    {
        return in_array($type, ['int', 'float', 'bool', 'string', 'array', 'mixed', 'null', 'void'], true);
    }
}
