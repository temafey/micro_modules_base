<?php

declare(strict_types=1);

namespace MicroModule\Base\Tests\Unit\Cache;

use MicroModule\Base\Cache\AbstractCachedRepository;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[CoversClass(AbstractCachedRepository::class)]
final class AbstractCachedRepositoryTest extends TestCase
{
    private TagAwareCacheInterface&MockInterface $cache;
    private LoggerInterface&MockInterface $logger;
    private AbstractCachedRepository $repository;

    protected function setUp(): void
    {
        $this->cache = Mockery::mock(TagAwareCacheInterface::class);
        $this->logger = Mockery::mock(LoggerInterface::class);

        $this->repository = new class ($this->cache, $this->logger) extends AbstractCachedRepository {
            public function callLogCacheMiss(string $key, string $context = ''): void
            {
                $this->logCacheMiss($key, $context);
            }

            public function callLogCacheHit(string $key, string $context = ''): void
            {
                $this->logCacheHit($key, $context);
            }

            public function callLogCacheInvalidation(array $tags): void
            {
                $this->logCacheInvalidation($tags);
            }
        };
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    #[Test]
    public function logCacheMissLogsDebugMessage(): void
    {
        $this->logger->shouldReceive('debug')
            ->once()
            ->with('Cache MISS', Mockery::on(function (array $context): bool {
                return $context['key'] === 'test-key'
                    && $context['context'] === 'findById';
            }));

        $this->repository->callLogCacheMiss('test-key', 'findById');
    }

    #[Test]
    public function logCacheHitLogsDebugMessage(): void
    {
        $this->logger->shouldReceive('debug')
            ->once()
            ->with('Cache HIT', Mockery::on(function (array $context): bool {
                return $context['key'] === 'hit-key'
                    && $context['context'] === 'findAll';
            }));

        $this->repository->callLogCacheHit('hit-key', 'findAll');
    }

    #[Test]
    public function logCacheInvalidationLogsDebugMessage(): void
    {
        $tags = ['news', 'news.123'];

        $this->logger->shouldReceive('debug')
            ->once()
            ->with('Cache invalidated', Mockery::on(function (array $context) use ($tags): bool {
                return $context['tags'] === $tags;
            }));

        $this->repository->callLogCacheInvalidation($tags);
    }
}
