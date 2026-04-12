<?php

declare(strict_types=1);

use Symplify\EasyCodingStandard\Config\ECSConfig;

return ECSConfig::configure()
    ->withPaths([__DIR__ . '/src', __DIR__ . '/tests'])
    ->withSets([
        \Symplify\EasyCodingStandard\ValueObject\Set\SetList::PSR_12,
        \Symplify\EasyCodingStandard\ValueObject\Set\SetList::CLEAN_CODE,
    ]);
