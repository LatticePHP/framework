<?php

declare(strict_types=1);

namespace Lattice\DevTools\Template;

final class TemplateEngine
{
    /**
     * Render a template by replacing {{variableName}} placeholders with values.
     *
     * @param array<string, string> $variables
     */
    public function render(string $template, array $variables): string
    {
        $result = $template;

        foreach ($variables as $name => $value) {
            $result = str_replace('{{' . $name . '}}', $value, $result);
        }

        return $result;
    }
}
