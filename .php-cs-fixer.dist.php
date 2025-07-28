<?php

declare(strict_types=1);

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude([
        'var',
        'vendor',
        'public/uploads',
        'public/images',
        'frankenphp',
        'node_modules',
    ])
    ->name('*.php')
    ->notName('*.php.bak')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new PhpCsFixer\Config())
    ->setParallelConfig(new PhpCsFixer\Runner\Parallel\ParallelConfig(4, 20))
    ->setRiskyAllowed(true)
    ->setRules([
        // Base rule sets
        '@PSR12' => true,
        '@Symfony' => true,
        '@PhpCsFixer' => true,
        
        // Import handling - key requirement for removing unused imports
        'ordered_imports' => [
            'sort_algorithm' => 'alpha',
            'imports_order' => ['class', 'function', 'const'],
        ],
        'no_unused_imports' => true,
        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => false,
            'import_functions' => false,
        ],
        'single_line_after_imports' => true,
        'group_import' => false,
        
        // Array syntax
        'array_syntax' => ['syntax' => 'short'],
        'array_indentation' => true,
        'trim_array_spaces' => true,
        'no_whitespace_before_comma_in_array' => true,
        'whitespace_after_comma_in_array' => true,
        
        // String handling
        'single_quote' => ['strings_containing_single_quote_chars' => false],
        'simple_to_complex_string_variable' => true,
        
        // PHP 8+ features and strict typing
        'declare_strict_types' => true,
        'strict_comparison' => true,
        'strict_param' => true,
        'nullable_type_declaration_for_default_null_value' => true,
        'no_null_property_initialization' => true,
        
        // Documentation improvements
        'phpdoc_align' => ['align' => 'left'],
        'phpdoc_order' => true,
        'phpdoc_separation' => true,
        'phpdoc_trim' => true,
        'phpdoc_add_missing_param_annotation' => true,
        'phpdoc_annotation_without_dot' => true,
        'phpdoc_indent' => true,
        'phpdoc_inline_tag_normalizer' => true,
        'phpdoc_no_access' => true,
        'phpdoc_no_alias_tag' => true,
        'phpdoc_no_empty_return' => true,
        'phpdoc_no_package' => true,
        'phpdoc_no_useless_inheritdoc' => true,
        'phpdoc_order_by_value' => true,
        'phpdoc_return_self_reference' => true,
        'phpdoc_scalar' => true,
        'phpdoc_single_line_var_spacing' => true,
        'phpdoc_summary' => true,
        'phpdoc_tag_type' => true,
        'phpdoc_to_comment' => true,
        'phpdoc_trim_consecutive_blank_line_separation' => true,
        'phpdoc_types' => true,
        'phpdoc_types_order' => ['null_adjustment' => 'always_last'],
        'phpdoc_var_annotation_correct_order' => true,
        'phpdoc_var_without_name' => true,
        'comment_to_phpdoc' => true,
        
        // Control structures
        'yoda_style' => ['equal' => false, 'identical' => false, 'less_and_greater' => false],
        'no_superfluous_elseif' => true,
        'no_useless_else' => true,
        'switch_continue_to_break' => true,
        
        // Functions and lambdas
        'use_arrow_functions' => true,
        'static_lambda' => true,
        'lambda_not_used_import' => true,
        'function_declaration' => ['closure_function_spacing' => 'one'],
        
        // Modern PHP features
        'pow_to_exponentiation' => true,
        'random_api_migration' => true,
        'modernize_types_casting' => true,
        'modernize_strpos' => true,
        'ternary_to_null_coalescing' => true,
        'ternary_to_elvis_operator' => true,
        
        // Security and best practices
        'no_php4_constructor' => true,
        'no_unreachable_default_argument_value' => true,
        'psr_autoloading' => true,
        'self_accessor' => true,
        'self_static_accessor' => true,
        'no_mixed_echo_print' => ['use' => 'echo'],
        
        // Class organization
        'class_attributes_separation' => [
            'elements' => [
                'const' => 'one',
                'method' => 'one',
                'property' => 'one',
                'trait_import' => 'none',
                'case' => 'none',
            ],
        ],
        'method_argument_space' => [
            'on_multiline' => 'ensure_fully_multiline',
        ],
        'method_chaining_indentation' => true,
        'ordered_class_elements' => true,
        'protected_to_private' => true,
        'single_class_element_per_statement' => ['elements' => ['property']],
        
        // Doctrine annotations
        'doctrine_annotation_braces' => true,
        'doctrine_annotation_indentation' => true,
        'doctrine_annotation_spaces' => true,
        
        // Formatting and whitespace
        'concat_space' => ['spacing' => 'one'],
        'trailing_comma_in_multiline' => ['elements' => ['arrays', 'arguments', 'parameters']],
        'binary_operator_spaces' => [
            'default' => 'single_space',
            'operators' => [
                '=>' => 'single_space',
                '=' => 'single_space',
            ],
        ],
        'cast_spaces' => ['space' => 'single'],
        'increment_style' => ['style' => 'post'],
        'return_type_declaration' => ['space_before' => 'none'],
        'unary_operator_spaces' => true,
        'operator_linebreak' => ['only_booleans' => true],
        'standardize_not_equals' => true,
        'object_operator_without_whitespace' => true,
        'no_spaces_around_offset' => true,
        'no_trailing_comma_in_singleline' => true,
        'no_whitespace_in_blank_line' => true,
        'space_after_semicolon' => ['remove_in_empty_for_expressions' => true],
        'blank_line_before_statement' => [
            'statements' => [
                'case',
                'continue',
                'declare',
                'default',
                'exit',
                'goto',
                'include',
                'include_once',
                'phpdoc',
                'require',
                'require_once',
                'return',
                'switch',
                'throw',
                'try',
                'yield',
                'yield_from',
            ],
        ],
        'compact_nullable_type_declaration' => true,
        'no_blank_lines_after_class_opening' => true,
        'no_blank_lines_after_phpdoc' => true,
        'no_extra_blank_lines' => [
            'tokens' => [
                'attribute',
                'break',
                'case',
                'continue',
                'curly_brace_block',
                'default',
                'extra',
                'parenthesis_brace_block',
                'return',
                'square_brace_block',
                'switch',
                'throw',
                'use',
            ],
        ],
        
        // Clean namespace
        'clean_namespace' => true,
        
        // Override some potentially problematic rules
        'multiline_comment_opening_closing' => false,
        'php_unit_internal_class' => false,
        'php_unit_test_class_requires_covers' => false,
        'final_internal_class' => false,
        'date_time_immutable' => false,
        'mb_str_functions' => false,
        'header_comment' => false,
    ])
    ->setFinder($finder)
    ->setCacheFile(__DIR__ . '/var/.php-cs-fixer.cache');
