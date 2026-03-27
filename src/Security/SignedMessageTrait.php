<?php

declare(strict_types=1);

namespace MicroModule\Base\Security;

/**
 * Trait providing default implementation of SignedMessageInterface.
 */
trait SignedMessageTrait
{
    private ?string $signature = null;

    private ?\DateTimeImmutable $timestamp = null;

    private ?string $nonce = null;

    public function getSignature(): ?string
    {
        return $this->signature;
    }

    public function setSignature(string $signature): void
    {
        $this->signature = $signature;
    }

    public function getTimestamp(): ?\DateTimeImmutable
    {
        return $this->timestamp;
    }

    public function setTimestamp(\DateTimeImmutable $timestamp): void
    {
        $this->timestamp = $timestamp;
    }

    public function getNonce(): ?string
    {
        return $this->nonce;
    }

    public function setNonce(string $nonce): void
    {
        $this->nonce = $nonce;
    }
}
