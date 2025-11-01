<?php

declare(strict_types=1);

namespace MicroModule\Base\Application\Factory;

use MicroModule\Base\Application\Command\CommandInterface;
use MicroModule\Base\Application\Dto\DtoInterface;
use MicroModule\Base\Domain\Exception\CommandFactoryNotFoundException;

class CommonCommandFactory implements CommandFactoryInterface
{
    protected const ALLOWED_COMMANDS = [];

    /**
     * Available Command factories
     *
     * @var array<CommandFactoryInterface>
     */
    protected array $factories;

    public function makeCommandInstanceByTypeFromDto(string $commandType, DtoInterface $dto): CommandInterface
    {
        $data = array_values($dto->normalize());

        return $this->makeCommandInstanceByType($commandType, ...$data);
    }

    public function makeCommandInstanceByType(...$args): CommandInterface
    {
        $commandType = (string) $args[array_key_first($args)];
        $factory = $this->getFactoryByCommandType($commandType);

        return $factory->makeCommandInstanceByType(...$args);
    }

    public function isCommandAllowed(string $commandType): bool
    {
        if (! static::ALLOWED_COMMANDS) {
            return true;
        }

        return in_array($commandType, static::ALLOWED_COMMANDS, true);
    }

    public function addFactory(CommandFactoryInterface $commandFactory): self
    {
        $this->factories[$commandFactory::class] = $commandFactory;

        return $this;
    }

    protected function getFactoryByCommandType(string $commandType): CommandFactoryInterface
    {
        foreach ($this->factories as $factory) {
            if ($factory->isCommandAllowed($commandType)) {
                return $factory;
            }
        }

        throw CommandFactoryNotFoundException::fromCommandType($commandType);
    }
}
