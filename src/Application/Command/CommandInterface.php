<?php

declare(strict_types=1);

namespace MicroModule\Base\Application\Command;

use MicroModule\Base\Domain\ValueObject\Payload;
use MicroModule\Base\Domain\ValueObject\ProcessUuid;
use Ramsey\Uuid\UuidInterface;

interface CommandInterface
{
    /**
     * Return ProcessUuid value object.
     */
    public function getProcessUuid(): ?ProcessUuid;

    /**
     * Return Uuid value object.
     */
    public function getUuid(): ?UuidInterface;

    /**
     * Return Payload value object.
     */
    public function getPayload(): ?Payload;
}
