<?php

declare(strict_types=1);

namespace MicroModule\Base\Cache;

use Symfony\Component\Cache\Marshaller\MarshallerInterface;
use Symfony\Component\DependencyInjection\Attribute\Lazy;

/**
 * MsgPack Marshaller for Redis Cache.
 *
 * Provides binary serialization using MessagePack format for:
 * - Up to 40% faster serialization than PHP serialize()
 * - Up to 20% smaller storage footprint than JSON
 * - Type-safe and language-agnostic format
 *
 * Requires msgpack PHP extension (pecl install msgpack).
 *
 * @see https://msgpack.org/
 */
#[Lazy]
final readonly class MsgPackMarshaller implements MarshallerInterface
{
    /**
     * @param array<string,mixed>    $values Array of values to serialize keyed by cache ID
     * @param array<int,string>|null $failed Array of IDs that failed to serialize (output param)
     *
     * @return array<string,string> Array of serialized values keyed by cache ID
     */
    public function marshall(array $values, ?array &$failed): array
    {
        $serialized = [];
        $failed = [];

        foreach ($values as $id => $value) {
            try {
                $packed = \msgpack_pack($value);
                if ($packed === false) {
                    $failed[] = $id;
                    continue;
                }

                $serialized[$id] = $packed;
            } catch (\Throwable) {
                $failed[] = $id;
            }
        }

        return $serialized;
    }

    public function unmarshall(string $value): mixed
    {
        return \msgpack_unpack($value);
    }
}
