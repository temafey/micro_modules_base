<?php

declare(strict_types=1);

namespace MicroModule\Base\Tests\Unit\Security;

use MicroModule\Base\Security\InvalidSignatureException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(InvalidSignatureException::class)]
final class InvalidSignatureExceptionTest extends TestCase
{
    #[Test]
    public function missingSignatureHasCorrectReasonAndClass(): void
    {
        $exception = InvalidSignatureException::missingSignature('App\\Command\\MyCommand');

        self::assertSame('missing_signature', $exception->getReason());
        self::assertSame('App\\Command\\MyCommand', $exception->getCommandClass());
        self::assertStringContainsString('not provided', $exception->getMessage());
    }

    #[Test]
    public function invalidSignatureHasCorrectReasonAndClass(): void
    {
        $exception = InvalidSignatureException::invalidSignature('App\\Command\\MyCommand');

        self::assertSame('invalid_signature', $exception->getReason());
        self::assertSame('App\\Command\\MyCommand', $exception->getCommandClass());
        self::assertStringContainsString('Invalid', $exception->getMessage());
    }

    #[Test]
    public function expiredMessageHasCorrectReasonAndClass(): void
    {
        $timestamp = new \DateTimeImmutable('2026-01-01T00:00:00+00:00');
        $exception = InvalidSignatureException::expiredMessage('App\\Command\\MyCommand', $timestamp);

        self::assertSame('expired_timestamp', $exception->getReason());
        self::assertSame('App\\Command\\MyCommand', $exception->getCommandClass());
        self::assertStringContainsString('expired', $exception->getMessage());
        self::assertStringContainsString('2026-01-01', $exception->getMessage());
    }

    #[Test]
    public function replayDetectedHasCorrectReasonAndClass(): void
    {
        $exception = InvalidSignatureException::replayDetected('App\\Command\\MyCommand', 'nonce-abc');

        self::assertSame('replay_attack', $exception->getReason());
        self::assertSame('App\\Command\\MyCommand', $exception->getCommandClass());
        self::assertStringContainsString('Replay attack', $exception->getMessage());
        self::assertStringContainsString('nonce-abc', $exception->getMessage());
    }
}
