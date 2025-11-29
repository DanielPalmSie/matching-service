<?php

$finder = PhpCsFixer\Finder::create()
    ->in(['src']);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,
        'strict_param' => true,
    ])
    ->setFinder($finder);
