<?php

declare(strict_types=1);

namespace Lattice\Validation\Illuminate;

use Lattice\Contracts\Pipeline\PipeInterface;
use Lattice\Validation\Attributes\ArrayType;
use Lattice\Validation\Attributes\BooleanType;
use Lattice\Validation\Attributes\DateTimeType;
use Lattice\Validation\Attributes\Email;
use Lattice\Validation\Attributes\FloatType;
use Lattice\Validation\Attributes\InArray;
use Lattice\Validation\Attributes\IntegerType;
use Lattice\Validation\Attributes\Nullable;
use Lattice\Validation\Attributes\Required;
use Lattice\Validation\Attributes\StringType;
use Lattice\Validation\Attributes\Url;
use Lattice\Validation\Attributes\Uuid;
use Lattice\Validation\Exceptions\ValidationException;
use Lattice\Validation\FieldError;
use Lattice\Validation\ValidationResult;
use ReflectionClass;
use ReflectionProperty;

/**
 * A pipe that uses Illuminate's validator with LatticePHP's attribute-to-rules mapping.
 *
 * Reads validation attributes (#[Required], #[Email], etc.), maps them to Illuminate
 * validation rules ('required', 'email', etc.), runs Illuminate validation, and throws
 * our ValidationException on failure.
 */
final class IlluminateValidationPipe implements PipeInterface
{
    private readonly IlluminateValidator $validator;

    public function __construct(?IlluminateValidator $validator = null)
    {
        $this->validator = $validator ?? new IlluminateValidator();
    }

    public function transform(mixed $value, array $metadata = []): mixed
    {
        if (!is_object($value) || !$this->hasValidationAttributes($value)) {
            return $value;
        }

        $reflection = new ReflectionClass($value);
        $rules = [];
        $data = [];

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $fieldName = $property->getName();
            $data[$fieldName] = $property->getValue($value);
            $fieldRules = $this->mapAttributesToRules($property);

            if ($fieldRules !== []) {
                $rules[$fieldName] = $fieldRules;
            }
        }

        $errors = $this->validator->validate($data, $rules);

        if ($errors !== []) {
            $fieldErrors = [];
            foreach ($errors as $field => $messages) {
                foreach ($messages as $message) {
                    $fieldErrors[] = new FieldError(
                        field: $field,
                        message: $message,
                        rule: 'illuminate',
                        value: $data[$field] ?? null,
                    );
                }
            }

            throw new ValidationException(new ValidationResult($fieldErrors));
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private function mapAttributesToRules(ReflectionProperty $property): array
    {
        $rules = [];
        $attributes = $property->getAttributes();

        foreach ($attributes as $attribute) {
            $attrInstance = $attribute->newInstance();

            $mapped = match (true) {
                $attrInstance instanceof Required => ['required'],
                $attrInstance instanceof Nullable => ['nullable'],
                $attrInstance instanceof StringType => $this->mapStringType($attrInstance),
                $attrInstance instanceof IntegerType => $this->mapIntegerType($attrInstance),
                $attrInstance instanceof FloatType => $this->mapFloatType($attrInstance),
                $attrInstance instanceof BooleanType => ['boolean'],
                $attrInstance instanceof Email => ['email'],
                $attrInstance instanceof Url => ['url'],
                $attrInstance instanceof Uuid => ['uuid'],
                $attrInstance instanceof InArray => ['in:' . implode(',', $attrInstance->values)],
                $attrInstance instanceof ArrayType => $this->mapArrayType($attrInstance),
                $attrInstance instanceof DateTimeType => ['date_format:' . $attrInstance->format],
                default => [],
            };

            $rules = [...$rules, ...$mapped];
        }

        return $rules;
    }

    /**
     * @return list<string>
     */
    private function mapStringType(StringType $attr): array
    {
        $rules = ['string'];

        if ($attr->minLength !== null) {
            $rules[] = 'min:' . $attr->minLength;
        }

        if ($attr->maxLength !== null) {
            $rules[] = 'max:' . $attr->maxLength;
        }

        if ($attr->pattern !== null) {
            $rules[] = 'regex:' . $attr->pattern;
        }

        return $rules;
    }

    /**
     * @return list<string>
     */
    private function mapIntegerType(IntegerType $attr): array
    {
        $rules = ['integer'];

        if ($attr->min !== null) {
            $rules[] = 'min:' . $attr->min;
        }

        if ($attr->max !== null) {
            $rules[] = 'max:' . $attr->max;
        }

        return $rules;
    }

    /**
     * @return list<string>
     */
    private function mapFloatType(FloatType $attr): array
    {
        $rules = ['numeric'];

        if ($attr->min !== null) {
            $rules[] = 'min:' . $attr->min;
        }

        if ($attr->max !== null) {
            $rules[] = 'max:' . $attr->max;
        }

        return $rules;
    }

    /**
     * @return list<string>
     */
    private function mapArrayType(ArrayType $attr): array
    {
        $rules = ['array'];

        if ($attr->minItems !== null) {
            $rules[] = 'min:' . $attr->minItems;
        }

        if ($attr->maxItems !== null) {
            $rules[] = 'max:' . $attr->maxItems;
        }

        return $rules;
    }

    private function hasValidationAttributes(object $dto): bool
    {
        $reflection = new ReflectionClass($dto);
        $attributeNamespace = 'Lattice\\Validation\\Attributes\\';

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            foreach ($property->getAttributes() as $attribute) {
                if (str_starts_with($attribute->getName(), $attributeNamespace)) {
                    return true;
                }
            }
        }

        return false;
    }
}
