<?php

// Norme de code du projet : PSR-12 complété par les règles Symfony.
// Application :  php-cs-fixer fix
// Vérification : php-cs-fixer check --diff   (exécutée par la chaîne d'intégration)

$finder = (new PhpCsFixer\Finder())
    ->in([__DIR__ . '/src', __DIR__ . '/tests'])
    ->exclude('var');

return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true,
        '@PSR12' => true,
        'declare_strict_types' => false,
        'phpdoc_align' => false,
        'yoda_style' => false,
        'concat_space' => ['spacing' => 'none'],
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'no_unused_imports' => true,
        'trailing_comma_in_multiline' => ['elements' => ['arrays', 'arguments', 'parameters']],
    ])
    ->setRiskyAllowed(false)
    ->setFinder($finder);
