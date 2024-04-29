<?php

declare(strict_types=1);

namespace MicroModule\Base\Infrastructure\Repository;

use MicroModule\Base\Utils\LoggerTrait;
use MicroModule\Base\Domain\Repository\QueryLiteRepositoryInterface;
use MicroModule\Base\Domain\Repository\ReadModelStoreInterface;
use MicroModule\Base\Domain\ValueObject\FindCriteria;
use MicroModule\Base\Domain\ValueObject\Uuid;
use MicroModule\Base\Infrastructure\Repository\Exception\NotFoundException;

class QueryLiteRepository implements QueryLiteRepositoryInterface
{
    use LoggerTrait;

    public function __construct(
        protected ReadModelStoreInterface $readModelStore
    ) {
    }

    public function findByUuid(Uuid $uuid): ?array
    {
        try {
            return $this->readModelStore->findOne($uuid->toNative());
        } catch (NotFoundException) {
            return null;
        }
    }

    public function findByCriteria(FindCriteria $findCriteria): ?array
    {
        try {
            return $this->readModelStore->findBy($findCriteria->toNative());
        } catch (NotFoundException) {
            return null;
        }
    }
}
