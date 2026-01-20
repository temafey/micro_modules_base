<?php

declare(strict_types=1);

namespace MicroModule\Base\Domain\Factory;

use MicroModule\Base\Domain\ValueObject\CommandName;
use MicroModule\Base\Domain\ValueObject\Payload;
use MicroModule\Base\Domain\ValueObject\ProcessUuid;

interface CommandFactoryInterface
{
    /**
     * Create CommandName value object
     */
    public function makeCommandName(string $commandName): CommandName;

    /**
     * Create ProcessUuid for command execution
     */
    public function makeCommandProcessUuid(?string $processUuid = null): ProcessUuid;

    /**
     * Create Payload for command data
     */
    public function makeCommandPayload(array $payload): Payload;
}
