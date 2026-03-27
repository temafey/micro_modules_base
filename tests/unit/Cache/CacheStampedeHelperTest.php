<?php

declare(strict_types=1);

namespace MicroModule\Base\Tests\Unit\Cache;

use MicroModule\Base\Cache\CacheStampedeHelper;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[CoversClass(CacheStampedeHelper::class)]
final class CacheStampedeHelperTest extends TestCase
{
    private object $helper;

    protected function setUp(): void
    {
        $this->helper = new class () {
            use CacheStampedeHelper {
                fetchWithStampedePrevention as public;
                generateCacheKey as public;
                generateCacheTags as public;
                generateCriteriaHash as public;
            }
        };
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    #[Test]
    public function generateCacheKeyCreatesNamespacedKey(): void
    {
        $key = $this->helper->generateCacheKey('news.query', 'item', '123');

        self::assertSame('news.query.item.123', $key);
    }

    #[Test]
    public function generateCacheKeyWithSinglePart(): void
    {
        $key = $this->helper->generateCacheKey('prefix', 'only');

        self::assertSame('prefix.only', $key);
    }

    #[Test]
    public function generateCacheTagsWithoutId(): void
    {
        $tags = $this->helper->generateCacheTags('news');

        self::assertSame(['news'], $tags);
    }

    #[Test]
    public function generateCacheTagsWithId(): void
    {
        $tags = $this->helper->generateCacheTags('news', 'abc-123');

        self::assertSame(['news', 'news.abc-123'], $tags);
    }

    #[Test]
    public function generateCriteriaHashReturnsDeterministicHash(): void
    {
        $criteria = ['status' => 'published', 'limit' => 10];

        $hash1 = $this->helper->generateCriteriaHash($criteria);
        $hash2 = $this->helper->generateCriteriaHash($criteria);

        self::assertSame($hash1, $hash2);
        self::assertSame(32, strlen($hash1));
    }

    #[Test]
    public function generateCriteriaHashDiffersForDifferentCriteria(): void
    {
        $hash1 = $this->helper->generateCriteriaHash(['a' => 1]);
        $hash2 = $this->helper->generateCriteriaHash(['a' => 2]);

        self::assertNotSame($hash1, $hash2);
    }

    #[Test]
    public function fetchWithStampedePreventionCallsCacheGet(): void
    {
        /** @var TagAwareCacheInterface&MockInterface $cache */
        $cache = Mockery::mock(TagAwareCacheInterface::class);
        $expectedValue = 'cached-data';

        $cache->shouldReceive('get')
            ->once()
            ->with('test-key', Mockery::type('callable'), Mockery::any())
            ->andReturn($expectedValue);

        $result = $this->helper->fetchWithStampedePrevention(
            $cache,
            'test-key',
            fn () => $expectedValue,
            ['tag1'],
            300,
        );

        self::assertSame($expectedValue, $result);
    }
}
