<?php

declare(strict_types=1);

namespace Lattice\Compiler\Discovery;

use Lattice\Compiler\Attributes\Controller;
use Lattice\Compiler\Attributes\GlobalModule;
use Lattice\Compiler\Attributes\Injectable;
use Lattice\Compiler\Attributes\Module;
use ReflectionClass;

final class AttributeScanner
{
    /**
     * Scan a directory for PHP files containing framework attributes.
     *
     * @return array<AttributeMetadata>
     */
    public function scanDirectory(string $path): array
    {
        $results = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $className = $this->extractClassName($file->getPathname());

            if ($className === null) {
                continue;
            }

            $metadata = $this->scanClass($className);

            if ($metadata !== null) {
                $results[] = $metadata;
            }
        }

        return $results;
    }

    /**
     * Scan a single class for framework attributes.
     */
    public function scanClass(string $className): ?AttributeMetadata
    {
        if (!class_exists($className)) {
            return null;
        }

        $reflection = new ReflectionClass($className);

        $isModule = false;
        $isController = false;
        $isInjectable = false;
        $isGlobal = false;
        $imports = [];
        $providers = [];
        $controllers = [];
        $exports = [];
        $controllerPrefix = '';

        $hasAnyAttribute = false;

        // Check for Module attribute
        $moduleAttrs = $reflection->getAttributes(Module::class);
        if (!empty($moduleAttrs)) {
            $hasAnyAttribute = true;
            $isModule = true;
            $module = $moduleAttrs[0]->newInstance();
            $imports = $module->imports;
            $providers = $module->providers;
            $controllers = $module->controllers;
            $exports = $module->exports;
            $isGlobal = $module->global;
        }

        // Check for Controller attribute
        $controllerAttrs = $reflection->getAttributes(Controller::class);
        if (!empty($controllerAttrs)) {
            $hasAnyAttribute = true;
            $isController = true;
            $controller = $controllerAttrs[0]->newInstance();
            $controllerPrefix = $controller->prefix;
        }

        // Check for Injectable attribute
        $injectableAttrs = $reflection->getAttributes(Injectable::class);
        if (!empty($injectableAttrs)) {
            $hasAnyAttribute = true;
            $isInjectable = true;
        }

        // Check for GlobalModule attribute
        $globalAttrs = $reflection->getAttributes(GlobalModule::class);
        if (!empty($globalAttrs)) {
            $hasAnyAttribute = true;
            $isGlobal = true;
        }

        if (!$hasAnyAttribute) {
            return null;
        }

        return new AttributeMetadata(
            className: $className,
            isModule: $isModule,
            isController: $isController,
            isInjectable: $isInjectable,
            isGlobal: $isGlobal,
            imports: $imports,
            providers: $providers,
            controllers: $controllers,
            exports: $exports,
            controllerPrefix: $controllerPrefix,
        );
    }

    /**
     * Extract the fully-qualified class name from a PHP file.
     */
    private function extractClassName(string $filePath): ?string
    {
        $contents = file_get_contents($filePath);

        if ($contents === false) {
            return null;
        }

        $namespace = null;
        $class = null;

        $tokens = token_get_all($contents);
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            if (!is_array($tokens[$i])) {
                continue;
            }

            if ($tokens[$i][0] === T_NAMESPACE) {
                $namespace = $this->extractAfterToken($tokens, $i, $count);
            }

            if ($tokens[$i][0] === T_CLASS) {
                // Make sure it's not "::class"
                if ($i > 0 && is_array($tokens[$i - 1]) && $tokens[$i - 1][0] === T_DOUBLE_COLON) {
                    continue;
                }

                $class = $this->extractNameToken($tokens, $i, $count);
                break;
            }
        }

        if ($class === null) {
            return null;
        }

        return $namespace !== null ? $namespace . '\\' . $class : $class;
    }

    private function extractAfterToken(array $tokens, int &$index, int $count): string
    {
        $result = '';
        $index++;

        while ($index < $count) {
            if (is_array($tokens[$index])) {
                if ($tokens[$index][0] === T_WHITESPACE) {
                    $index++;
                    continue;
                }
                if ($tokens[$index][0] === T_NAME_QUALIFIED || $tokens[$index][0] === T_STRING) {
                    $result .= $tokens[$index][1];
                }
            } elseif ($tokens[$index] === ';' || $tokens[$index] === '{') {
                break;
            }
            $index++;
        }

        return $result;
    }

    private function extractNameToken(array $tokens, int &$index, int $count): ?string
    {
        $index++;

        while ($index < $count) {
            if (is_array($tokens[$index])) {
                if ($tokens[$index][0] === T_WHITESPACE) {
                    $index++;
                    continue;
                }
                if ($tokens[$index][0] === T_STRING) {
                    return $tokens[$index][1];
                }
            }
            break;
        }

        return null;
    }
}
