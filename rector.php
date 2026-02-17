<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Exception\Configuration\InvalidConfigurationException;

try {
    return RectorConfig::configure()
        ->withPaths([
            __DIR__.'/src',
            __DIR__.'/tests',
        ])
        ->withPhpSets()
        ->withImportNames()
        ->withPreparedSets(
            deadCode: true,
            typeDeclarations: true,
            privatization: true,
            instanceOf: true,
            codeQuality: true,
            codingStyle: true,
        );
} catch (InvalidConfigurationException $e) {
}
