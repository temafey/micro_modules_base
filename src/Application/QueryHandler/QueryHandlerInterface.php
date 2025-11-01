<?php

declare(strict_types=1);

namespace MicroModule\Base\Application\QueryHandler;

use MicroModule\Base\Application\Query\QueryInterface;

interface QueryHandlerInterface
{
    /**
     * Handle specific query
     */
    public function handle(QueryInterface $query);
}
