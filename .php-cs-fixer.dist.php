<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->notPath([
        'classes/KI.php',
    ]);

return (new PhpCsFixer\Config())
    ->setRules([
        '@PER' => true,
    ])
    ->setFinder($finder);