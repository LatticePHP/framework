<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/packages/*/src')
    ->in(__DIR__ . '/packages/*/tests');

return (new PhpCsFixer\Config())
    ->setRules([
        '@PER-CS' => true,
        '@PHP84Migration' => true,
        'strict_param' => true,
        'declare_strict_types' => true,
        'final_class' => true,
        'no_unused_imports' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'single_quote' => true,
        'trailing_comma_in_multiline' => true,
        'array_syntax' => ['syntax' => 'short'],
    ])
    ->setFinder($finder)
    ->setRiskyAllowed(true);
