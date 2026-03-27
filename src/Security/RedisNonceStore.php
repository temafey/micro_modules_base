<?php

declare(strict_types=1);

namespace MicroModule\Base\Security;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Redis-based nonce storage for replay attack prevention.
 *
 * Stores used nonces in Redis with automatic expiration matching
 * the message lifetime.
 */
final readonly class RedisNonceStore
{
    private const string KEY_PREFIX = 'message_nonce:';

    private const int DEFAULT_TTL = 300;

    public function __construct(
        private \Redis|\RedisCluster $redis,
        private int $ttl = self::DEFAULT_TTL,
        private string $keyPrefix = self::KEY_PREFIX,
        private LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function isUsed(string $nonce): bool
    {
        $key = $this->getKey($nonce);

        try {
            return (bool) $this->redis->exists($key);
        } catch (\RedisException $redisException) {
            $this->logger->error('Redis error checking nonce', [
                'nonce' => $nonce,
                'error' => $redisException->getMessage(),
            ]);

            return false;
        }
    }

    public function markUsed(string $nonce): bool
    {
        $key = $this->getKey($nonce);

        try {
            $result = $this->redis->set($key, (string) time(), [
                'NX',
                'EX' => $this->ttl,
            ]);

            if ($result === false) {
                $this->logger->warning('Nonce already used (concurrent request)', [
                    'nonce' => $nonce,
                ]);

                return false;
            }

            $this->logger->debug('Nonce registered', [
                'nonce' => $nonce,
                'ttl' => $this->ttl,
            ]);

            return true;
        } catch (\RedisException $redisException) {
            $this->logger->error('Redis error marking nonce', [
                'nonce' => $nonce,
                'error' => $redisException->getMessage(),
            ]);

            return true;
        }
    }

    public function validateAndMark(string $nonce): bool
    {
        return $this->markUsed($nonce);
    }

    public function count(): int
    {
        try {
            $pattern = $this->keyPrefix . '*';
            $keys = $this->redis->keys($pattern);

            return is_array($keys) ? count($keys) : 0;
        } catch (\RedisException $redisException) {
            $this->logger->error('Redis error counting nonces', [
                'error' => $redisException->getMessage(),
            ]);

            return 0;
        }
    }

    private function getKey(string $nonce): string
    {
        return $this->keyPrefix . $nonce;
    }
}
