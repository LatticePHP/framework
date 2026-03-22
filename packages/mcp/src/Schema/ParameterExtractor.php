<?php

declare(strict_types=1);

namespace Lattice\Mcp\Schema;

use Lattice\Mcp\Attributes\PromptArgument;
use Lattice\Mcp\Attributes\ToolParam;

final class ParameterExtractor
{
    /**
     * Extract parameter metadata from a reflection method.
     *
     * @return list<ParameterInfo>
     */
    public function extract(\ReflectionMethod $method): array
    {
        $params = [];
        $docParams = $this->parseDocBlock($method);

        foreach ($method->getParameters() as $param) {
            $description = '';

            // Check for #[ToolParam] or #[PromptArgument] attribute
            $toolParamAttrs = $param->getAttributes(ToolParam::class);
            $promptArgAttrs = $param->getAttributes(PromptArgument::class);

            if ($toolParamAttrs !== []) {
                $toolParam = $toolParamAttrs[0]->newInstance();
                $description = $toolParam->description;
            } elseif ($promptArgAttrs !== []) {
                $promptArg = $promptArgAttrs[0]->newInstance();
                $description = $promptArg->description;
            } elseif (isset($docParams[$param->getName()])) {
                $description = $docParams[$param->getName()];
            }

            $params[] = new ParameterInfo(
                name: $param->getName(),
                type: $param->getType(),
                hasDefault: $param->isDefaultValueAvailable(),
                defaultValue: $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null,
                nullable: $param->getType() instanceof \ReflectionNamedType && $param->getType()->allowsNull(),
                description: $description,
            );
        }

        return $params;
    }

    /**
     * Parse @param tags from a method's docblock.
     *
     * @return array<string, string>
     */
    private function parseDocBlock(\ReflectionMethod $method): array
    {
        $doc = $method->getDocComment();

        if ($doc === false) {
            return [];
        }

        $params = [];

        if (preg_match_all('/@param\s+\S+\s+\$(\w+)\s+(.+)$/m', $doc, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $params[$match[1]] = trim($match[2]);
            }
        }

        return $params;
    }
}
