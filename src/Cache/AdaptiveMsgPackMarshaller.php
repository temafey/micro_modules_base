<?php

declare(strict_types=1);

namespace MicroModule\Base\Cache;

use Symfony\Component\Cache\Marshaller\DefaultMarshaller;
use Symfony\Component\Cache\Marshaller\MarshallerInterface;
use Symfony\Component\DependencyInjection\Attribute\Lazy;

/**
 * Adaptive Marshaller with MsgPack support and automatic fallback.
 *
 * Provides:
 * - MsgPack binary serialization when extension is available (~40% faster)
 * - Automatic fallback to PHP native serialization otherwise
 * - Seamless switching without configuration changes
 */
#[Lazy]
final readonly class AdaptiveMsgPackMarshaller implements MarshallerInterface
{
    private MarshallerInterface $delegate;

    private bool $useMsgPack;

    public function __construct()
    {
        $this->useMsgPack = \extension_loaded('msgpack');
        $this->delegate = $this->useMsgPack
            ? new MsgPackMarshaller()
            : new DefaultMarshaller();
    }

    public function isMsgPackEnabled(): bool
    {
        return $this->useMsgPack;
    }

    public function marshall(array $values, ?array &$failed): array
    {
        return $this->delegate->marshall($values, $failed);
    }

    public function unmarshall(string $value): mixed
    {
        return $this->delegate->unmarshall($value);
    }
}
