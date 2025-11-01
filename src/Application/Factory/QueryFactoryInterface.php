<?php

declare(strict_types=1);

namespace MicroModule\Base\Application\Factory;

use MicroModule\Base\Application\Dto\DtoInterface;
use MicroModule\Base\Application\Query\QueryInterface as BaseQueryInterface;

interface QueryFactoryInterface
{
    /**
     * Check if query is allowed for current factory
     */
    public function isQueryAllowed(string $queryType): bool;

    /**
     * Make command by command constant.
     */
    public function makeQueryInstanceByTypeFromDto(string $queryType, DtoInterface $dto): BaseQueryInterface;
}
