<?php

declare(strict_types=1);

namespace MicroModule\Base\Application\Processor;

use MicroModule\Task\Application\Processor\JobCommandBusProcessor;

class JobProgramProcessor extends JobCommandBusProcessor
{
    public static function getRoute(): string
    {
        return "task.command.run";
    }
}
