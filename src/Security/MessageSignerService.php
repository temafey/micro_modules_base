<?php

declare(strict_types=1);

namespace MicroModule\Base\Security;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Random\Randomizer;

/**
 * Service for cryptographic signing and verification of messages.
 *
 * Uses HMAC-SHA256 for generating tamper-proof signatures.
 * Includes replay attack prevention via nonce and timestamp validation.
 */
final class MessageSignerService
{
    private const string HASH_ALGORITHM = 'sha256';

    private const int DEFAULT_MESSAGE_LIFETIME = 300;

    private const int NONCE_LENGTH = 32;

    /** @var array<string, true> */
    private array $usedNonces = [];

    public function __construct(
        private readonly string $secret,
        private readonly int $messageLifetime = self::DEFAULT_MESSAGE_LIFETIME,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
        if (strlen($this->secret) < 32) {
            $this->logger->warning(
                'Message signing secret is shorter than recommended 32 bytes',
                ['length' => strlen($this->secret)]
            );
        }
    }

    public function sign(SignedMessageInterface $message): SignedMessageInterface
    {
        if (! $message->getTimestamp() instanceof \DateTimeImmutable) {
            $message->setTimestamp(new \DateTimeImmutable());
        }

        if ($message->getNonce() === null) {
            $message->setNonce($this->generateNonce());
        }

        $signature = $this->computeSignature($message);
        $message->setSignature($signature);

        $this->logger->debug('Message signed', [
            'timestamp' => $message->getTimestamp()?->format(\DateTimeInterface::ATOM),
            'nonce' => $message->getNonce(),
            'signature_prefix' => substr($signature, 0, 8) . '...',
        ]);

        return $message;
    }

    public function verify(SignedMessageInterface $message): bool
    {
        $signature = $message->getSignature();

        if ($signature === null) {
            $this->logger->warning('Message verification failed: no signature');

            return false;
        }

        if (! $this->isTimestampValid($message)) {
            $this->logger->warning('Message verification failed: timestamp expired', [
                'timestamp' => $message->getTimestamp()?->format(\DateTimeInterface::ATOM),
                'lifetime' => $this->messageLifetime,
            ]);

            return false;
        }

        if (! $this->isNonceValid($message)) {
            $this->logger->warning('Message verification failed: nonce already used', [
                'nonce' => $message->getNonce(),
            ]);

            return false;
        }

        $expectedSignature = $this->computeSignature($message);

        if (! hash_equals($expectedSignature, $signature)) {
            $this->logger->warning('Message verification failed: signature mismatch');

            return false;
        }

        $nonce = $message->getNonce();
        if ($nonce !== null) {
            $this->usedNonces[$nonce] = true;
        }

        $this->logger->debug('Message verified successfully', [
            'timestamp' => $message->getTimestamp()?->format(\DateTimeInterface::ATOM),
            'nonce' => $message->getNonce(),
        ]);

        return true;
    }

    private function computeSignature(SignedMessageInterface $message): string
    {
        $payload = $message->getSignablePayload();
        ksort($payload, SORT_STRING);
        $data = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $timestamp = $message->getTimestamp()?->format(\DateTimeInterface::ATOM) ?? '';
        $nonce = $message->getNonce() ?? '';
        $signatureInput = $data . '|' . $timestamp . '|' . $nonce;

        return hash_hmac(self::HASH_ALGORITHM, $signatureInput, $this->secret);
    }

    private function isTimestampValid(SignedMessageInterface $message): bool
    {
        $timestamp = $message->getTimestamp();

        if (! $timestamp instanceof \DateTimeImmutable) {
            return false;
        }

        $now = new \DateTimeImmutable();
        $age = $now->getTimestamp() - $timestamp->getTimestamp();

        return $age >= 0 && $age <= $this->messageLifetime;
    }

    private function isNonceValid(SignedMessageInterface $message): bool
    {
        $nonce = $message->getNonce();

        if ($nonce === null) {
            return false;
        }

        return ! isset($this->usedNonces[$nonce]);
    }

    private function generateNonce(): string
    {
        $randomizer = new Randomizer();

        return bin2hex($randomizer->getBytes(self::NONCE_LENGTH));
    }

    public function clearNonceCache(): void
    {
        $this->usedNonces = [];
    }

    public function getNonceCacheCount(): int
    {
        return count($this->usedNonces);
    }
}
