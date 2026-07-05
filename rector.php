<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\ValueObject\PhpVersion;
use Rector\Php53\Rector\Ternary\TernaryToElvisRector;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;


return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/config',
        __DIR__.'/src',
        __DIR__.'/cms-bundle/src',
    ])
    ->withSkipPath(__DIR__.'/config/reference.php')
    ->withPhpSets()
    ->withImportNames()
    ->withPhpVersion(PhpVersion::PHP_85)
    ->withSkip([
        TernaryToElvisRector::class,
        ClassPropertyAssignToConstructorPromotionRector::class => [
            __DIR__ . '/cms-bundle/src/Entity',
        ],
    ])
    ->withImportNames(removeUnusedImports: true, importShortClasses: false)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        instanceOf: true,
        earlyReturn: true,
        privatization: true,
        // naming: true,
    );
