<?php

$finder = PhpCsFixer\Finder::create()
->in(__DIR__ . '/src');

return (new PhpCsFixer\Config())
    ->setParallelConfig(PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect())
    ->setRules([
        '@Symfony' => true,
        '@PSR12' => true,
        'array_indentation' => true,
        'array_syntax' => ['syntax' => 'short'],
        'combine_consecutive_unsets' => true,
        'single_quote' => true,
        'ordered_imports' => [
            'sort_algorithm' => 'alpha',
            'imports_order' => ['class', 'function', 'const'],
        ],
        'no_useless_else' => true,
        'phpdoc_order' => true,
        'no_null_property_initialization' => true,
        'no_superfluous_phpdoc_tags' => true
    ])
    ->setFinder($finder);
