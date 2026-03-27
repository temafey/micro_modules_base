<?php

declare(strict_types=1);

namespace MicroModule\Base\Tests\Unit\Security;

use MicroModule\Base\Security\RedisNonceStore;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RedisNonceStore::class)]
final class RedisNonceStoreTest extends TestCase
{
    private \Redis&MockInterface $redis;
    private RedisNonceStore $store;

    protected function setUp(): void
    {
        $this->redis = Mockery::mock(\Redis::class);
        $this->store = new RedisNonceStore($this->redis);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    #[Test]
    public function isUsedReturnsFalseForNewNonce(): void
    {
        $this->redis->shouldReceive('exists')
            ->once()
            ->with('message_nonce:test-nonce')
            ->andReturn(0);

        self::assertFalse($this->store->isUsed('test-nonce'));
    }

    #[Test]
    public function isUsedReturnsTrueForExistingNonce(): void
    {
        $this->redis->shouldReceive('exists')
            ->once()
            ->with('message_nonce:existing-nonce')
            ->andReturn(1);

        self::assertTrue($this->store->isUsed('existing-nonce'));
    }

    #[Test]
    public function markUsedReturnsTrueForNewNonce(): void
    {
        $this->redis->shouldReceive('set')
            ->once()
            ->with('message_nonce:new-nonce', Mockery::type('string'), Mockery::type('array'))
            ->andReturn(true);

        self::assertTrue($this->store->markUsed('new-nonce'));
    }

    #[Test]
    public function markUsedReturnsFalseForDuplicateNonce(): void
    {
        $this->redis->shouldReceive('set')
            ->once()
            ->andReturn(false);

        self::assertFalse($this->store->markUsed('duplicate-nonce'));
    }

    #[Test]
    public function countReturnsCorrectCount(): void
    {
        $this->redis->shouldReceive('keys')
            ->once()
            ->with('message_nonce:*')
            ->andReturn(['message_nonce:a', 'message_nonce:b']);

        self::assertSame(2, $this->store->count());
    }

    #[Test]
    public function isUsedReturnsFalseOnRedisException(): void
    {
        $this->redis->shouldReceive('exists')
            ->once()
            ->andThrow(new \RedisException('Connection refused'));

        self::assertFalse($this->store->isUsed('test'));
    }

    #[Test]
    public function markUsedReturnsTrueOnRedisException(): void
    {
        $this->redis->shouldReceive('set')
            ->once()
            ->andThrow(new \RedisException('Connection refused'));

        self::assertTrue($this->store->markUsed('test'));
    }
}
