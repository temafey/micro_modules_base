<?php

declare(strict_types=1);

namespace MicroModule\Base\Security;

/**
 * Exception thrown when message signature validation fails.
 */
final class InvalidSignatureException extends \RuntimeException
{
    private function __construct(
        string $message,
        private readonly string $commandClass,
        private readonly string $reason,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function missingSignature(string $commandClass): self
    {
        return new self(
            sprintf('Message signature required but not provided for command: %s', $commandClass),
            $commandClass,
            'missing_signature'
        );
    }

    public static function invalidSignature(string $commandClass): self
    {
        return new self(
            sprintf('Invalid message signature for command: %s', $commandClass),
            $commandClass,
            'invalid_signature'
        );
    }

    public static function expiredMessage(string $commandClass, \DateTimeImmutable $timestamp): self
    {
        return new self(
            sprintf(
                'Message timestamp expired for command: %s (timestamp: %s)',
                $commandClass,
                $timestamp->format(\DateTimeInterface::ATOM)
            ),
            $commandClass,
            'expired_timestamp'
        );
    }

    public static function replayDetected(string $commandClass, string $nonce): self
    {
        return new self(
            sprintf('Replay attack detected for command: %s (nonce: %s)', $commandClass, $nonce),
            $commandClass,
            'replay_attack'
        );
    }

    public function getCommandClass(): string
    {
        return $this->commandClass;
    }

    /**
     * @return string One of: missing_signature, invalid_signature, expired_timestamp, replay_attack
     */
    public function getReason(): string
    {
        return $this->reason;
    }
}
