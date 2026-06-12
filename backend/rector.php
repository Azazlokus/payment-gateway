<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\ClassMethod\NarrowObjectReturnTypeRector;
use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;
use Rector\ValueObject\PhpVersion;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/app',
        __DIR__.'/tests',
    ])
    ->withSkip([
        __DIR__.'/app/Http',           // фреймворк-скелет
        __DIR__.'/app/Console/Kernel.php',

        // guardInterface() использует @template T — Rector теряет generic return type
        NarrowObjectReturnTypeRector::class => [
            __DIR__.'/app/Contexts/Payments/Infrastructure/CircuitBreaker/CircuitBreakerProviderProxy.php',
        ],
    ])
    ->withPhpVersion(PhpVersion::PHP_84)
    ->withPhpSets(php84: true)
    ->withSets([
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        SetList::TYPE_DECLARATION,
    ])
    ->withImportNames(importShortClasses: false);
