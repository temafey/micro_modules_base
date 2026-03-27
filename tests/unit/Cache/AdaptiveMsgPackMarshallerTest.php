<?php

declare(strict_types=1);

namespace MicroModule\Base\Tests\Unit\Cache;

use MicroModule\Base\Cache\AdaptiveMsgPackMarshaller;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AdaptiveMsgPackMarshaller::class)]
final class AdaptiveMsgPackMarshallerTest extends TestCase
{
    #[Test]
    public function isMsgPackEnabledReturnsBool(): void
    {
        $marshaller = new AdaptiveMsgPackMarshaller();

        self::assertIsBool($marshaller->isMsgPackEnabled());
    }

    #[Test]
    public function marshallAndUnmarshallRoundTrip(): void
    {
        $marshaller = new AdaptiveMsgPackMarshaller();
        $values = ['key1' => 'value1', 'key2' => 42, 'key3' => ['nested' => true]];
        $failed = null;

        $serialized = $marshaller->marshall($values, $failed);

        self::assertEmpty($failed);
        self::assertCount(3, $serialized);

        foreach ($serialized as $id => $packed) {
            $unpacked = $marshaller->unmarshall($packed);
            self::assertSame($values[$id], $unpacked);
        }
    }

    #[Test]
    public function marshallHandlesEmptyArray(): void
    {
        $marshaller = new AdaptiveMsgPackMarshaller();
        $failed = null;

        $result = $marshaller->marshall([], $failed);

        self::assertEmpty($result);
        self::assertEmpty($failed);
    }
}
