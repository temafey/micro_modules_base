<?php

declare(strict_types=1);

namespace MicroModule\Base\Application\CommandHandler;

use MicroModule\Base\Application\Command\CommandInterface;

interface CommandHandlerInterface
{
    /**
     * Handle specific command
     */
    public function handle(CommandInterface $command);
}
