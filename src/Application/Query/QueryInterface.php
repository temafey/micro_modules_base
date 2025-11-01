<?php

declare(strict_types=1);

namespace MicroModule\Base\Application\Query;

use MicroModule\Base\Domain\ValueObject\ProcessUuid;

interface QueryInterface
{
    /**
     * Get Process Uuid
     */
    public function getProcessUuid(): ProcessUuid;
}
