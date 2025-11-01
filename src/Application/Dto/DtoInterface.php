<?php

declare(strict_types=1);

namespace MicroModule\Base\Application\Dto;

interface DtoInterface
{
    public const KEY_PROCESS_UUID = "process_uuid";

    public const KEY_UUID = "uuid";

    public const KEY_PAYLOAD = "payload";

    public const KEY_VERSION = "version";

    /**
     * Convert array to DTO object
     */
    public static function denormalize(array $data): static;

    /**
     * Convert object DTO to array
     */
    public function normalize(): array;
}
