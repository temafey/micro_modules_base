<?php

declare(strict_types=1);

namespace MicroModule\Base\Domain\Exception;

use MicroModule\Base\Domain\Exception\CriticalException;

class MessageWasNotSentToQueueException extends CriticalException
{
}
