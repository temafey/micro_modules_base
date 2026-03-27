<?php

declare(strict_types=1);

namespace MicroModule\Base\Security;

/**
 * Interface for messages that support cryptographic signing.
 *
 * Implement this interface on commands, events, or other messages
 * that require tamper-proof verification.
 *
 * @see MessageSignerService For signature generation and verification
 * @see SignedMessageMiddleware For automatic validation in command bus
 */
interface SignedMessageInterface
{
    /**
     * @return string|null The signature, or null if not yet signed
     */
    public function getSignature(): ?string;

    public function setSignature(string $signature): void;

    /**
     * @return array<string, mixed> The payload data for signing
     */
    public function getSignablePayload(): array;

    /**
     * @return \DateTimeImmutable|null The timestamp, or null if not set
     */
    public function getTimestamp(): ?\DateTimeImmutable;

    public function setTimestamp(\DateTimeImmutable $timestamp): void;

    /**
     * @return string|null The nonce, or null if not set
     */
    public function getNonce(): ?string;

    public function setNonce(string $nonce): void;
}
