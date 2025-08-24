<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude(['vendor', 'var', 'tests/Fixtures'])
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);
;

return (new PhpCsFixer\Config())
    // ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        '@PHP82Migration' => true,
        '@PHP83Migration' => true,
        '@PHP84Migration' => true,

        // Common modernizers that are safe in Symfony apps
        'array_syntax' => ['syntax' => 'short'],
        'trailing_comma_in_multiline' => ['elements' => ['arrays', 'arguments', 'parameters', 'match', 'array_destructuring']],
        'ordered_imports' => ['imports_order' => ['class', 'function', 'const'], 'sort_algorithm' => 'alpha'],
        'no_unused_imports' => true,
        'native_type_declaration_casing' => true,
        'return_type_declaration' => ['space_before' => 'none'],
        'single_line_throw' => true,
        'method_argument_space' => ['on_multiline' => 'ensure_fully_multiline', 'keep_multiple_spaces_after_comma' => false],
        'global_namespace_import' => ['import_classes' => true, 'import_constants' => true, 'import_functions' => true],
        'blank_line_between_import_groups' => true,
        'no_superfluous_phpdoc_tags' => ['allow_mixed' => true, 'allow_unused_params' => true],
        'phpdoc_order' => true,
        'phpdoc_align' => ['align' => 'vertical'],
        'phpdoc_summary' => true,
        'binary_operator_spaces' => [
            'operators' => [
                '=>' => 'align_single_space_minimal',
                '='  => 'single_space',
            ],
        ],
        'concat_space' => ['spacing' => 'one'],
        // 'declare_strict_types' => true, // Set to true if your codebase supports it


        // PHPUnit-focused additions suitable for PHPUnit 12 style
        'php_unit_attributes' => true,
        'php_unit_method_casing' => ['case' => 'camel_case'],

        // PHPUnit-focused risky
        // 'php_unit_namespaced' => true,
        // 'php_unit_construct' => true,
        // 'php_unit_strict' => true,
        // 'php_unit_data_provider_static' => true,
        // 'php_unit_data_provider_return_type' => true,
        // 'php_unit_mock_short_will_return' => true,
        // 'php_unit_test_case_static_method_calls' => ['call_type' => 'self'], // or 'this'/'static' to taste
        // 'php_unit_no_expectation_annotation' => true,


    ])
    ->setFinder($finder)
;