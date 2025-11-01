<?php

declare(strict_types=1);

namespace MicroModule\Base\Application\Query;

use MicroModule\Base\Domain\ValueObject\ProcessUuid;

/**
 * @SuppressWarnings(PHPMD.NumberOfChildren)
 */
abstract class AbstractQuery implements QueryInterface
{
    protected ProcessUuid $processUuid;

    public function __construct(ProcessUuid $processUuid)
    {
        $this->processUuid = $processUuid;
    }

    public function getProcessUuid(): ProcessUuid
    {
        return $this->processUuid;
    }
}
