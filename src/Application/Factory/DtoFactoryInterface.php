<?php

declare(strict_types=1);

namespace MicroModule\Base\Application\Factory;

use MicroModule\Base\Application\Dto\DtoInterface;

interface DtoFactoryInterface
{
    /**
     * Make command by command constant.
     */
    public function makeDtoByType(...$args): DtoInterface;
}
