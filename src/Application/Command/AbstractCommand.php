<?php

declare(strict_types=1);

namespace MicroModule\Base\Application\Command;

use MicroModule\Base\Domain\ValueObject\Payload;
use MicroModule\Base\Domain\ValueObject\ProcessUuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @SuppressWarnings(PHPMD.NumberOfChildren)
 */
abstract class AbstractCommand implements CommandInterface
{
    public function __construct(
        protected ?ProcessUuid $processUuid,
        protected ?UuidInterface $uuid,
        protected ?Payload $payload
    ) {
    }

    public function getProcessUuid(): ?ProcessUuid
    {
        return $this->processUuid;
    }

    public function getUuid(): ?UuidInterface
    {
        return $this->uuid;
    }

    public function getPayload(): ?Payload
    {
        return $this->payload;
    }
}
