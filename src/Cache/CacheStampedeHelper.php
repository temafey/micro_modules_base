<?php

declare(strict_types=1);

namespace MicroModule\Base\Cache;

use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * Reusable helper trait for cache stampede prevention using XFetch algorithm.
 *
 * Implements probabilistic early expiration to prevent cache stampedes
 * (thundering herd) by randomly recomputing cache values before they expire.
 *
 * @see https://cseweb.ucsd.edu/~avattani/papers/cache_stampede.pdf
 */
trait CacheStampedeHelper
{
    /**
     * Fetch data with cache stampede prevention using XFetch algorithm.
     *
     * @param TagAwareCacheInterface $cache    The tag-aware cache pool
     * @param string                 $key      Cache key
     * @param callable               $callback Callback to generate value on cache miss
     * @param array<string>          $tags     Cache tags for invalidation
     * @param int                    $ttl      Time to live in seconds
     * @param float                  $beta     Early expiration probability (1.0 = optimal)
     *
     * @return mixed The cached or computed value
     */
    protected function fetchWithStampedePrevention(
        TagAwareCacheInterface $cache,
        string $key,
        callable $callback,
        array $tags,
        int $ttl,
        float $beta = 1.0,
    ): mixed {
        return $cache->get(
            $key,
            function (ItemInterface $item) use ($callback, $tags, $ttl): mixed {
                $item->expiresAfter($ttl);
                $item->tag($tags);

                return $callback();
            },
            beta: $beta
        );
    }

    /**
     * Generate a namespaced cache key.
     *
     * @param string $prefix   Key prefix (e.g., 'news.query.item')
     * @param string ...$parts Additional key parts
     *
     * @return string The complete cache key
     */
    protected function generateCacheKey(string $prefix, string ...$parts): string
    {
        return sprintf('%s.%s', $prefix, implode('.', $parts));
    }

    /**
     * Generate cache tags for an entity.
     *
     * @param string      $entity Entity type (e.g., 'news')
     * @param string|null $id     Optional entity ID
     *
     * @return array<string> Cache tags
     */
    protected function generateCacheTags(string $entity, ?string $id = null): array
    {
        $tags = [$entity];

        if ($id !== null) {
            $tags[] = sprintf('%s.%s', $entity, $id);
        }

        return $tags;
    }

    /**
     * Generate a hash for criteria-based cache keys.
     *
     * @param mixed $criteria The criteria to hash
     *
     * @return string MD5 hash of the serialized criteria
     */
    protected function generateCriteriaHash(mixed $criteria): string
    {
        return md5(serialize($criteria));
    }
}
