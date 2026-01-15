<?php

use PhpCsFixer\Config;
use PhpCsFixer\Finder;
use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;

$finder = (new Finder())
    ->in(__DIR__ . '/yii')
    ->exclude([
        'assets',
        'runtime',
        'vendor',
        'views',
        'web',
        'tests/fixtures',
        'tests/_support',
        'tests/_output',
    ])
    ->notName(['c3.php']);

return (new Config())
    ->setFinder($finder)
    ->setParallelConfig(ParallelConfigFactory::detect())
    ->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
        'function_declaration' => ['closure_fn_spacing' => 'none'],
        'no_extra_blank_lines' => true,
        'statement_indentation' => [
            'stick_comment_to_next_continuous_control_statement' => true,
        ],
        'no_break_comment' => false,
        'fully_qualified_strict_types' => ['import_symbols' => true],
        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => true,
            'import_functions' => true,
        ],
        'no_unneeded_import_alias' => true,
        'no_unused_imports' => true,
        'no_leading_import_slash' => true,
    ]);
