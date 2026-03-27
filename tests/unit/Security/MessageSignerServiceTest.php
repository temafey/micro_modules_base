<?php

declare(strict_types=1);

namespace MicroModule\Base\Tests\Unit\Security;

use MicroModule\Base\Security\MessageSignerService;
use MicroModule\Base\Security\SignedMessageInterface;
use MicroModule\Base\Security\SignedMessageTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MessageSignerService::class)]
final class MessageSignerServiceTest extends TestCase
{
    private const string TEST_SECRET = 'a-very-secure-secret-key-that-is-long-enough-for-hmac';

    private MessageSignerService $signer;

    protected function setUp(): void
    {
        $this->signer = new MessageSignerService(self::TEST_SECRET);
    }

    #[Test]
    public function signSetsTimestampNonceAndSignature(): void
    {
        $message = $this->createTestMessage(['key' => 'value']);

        $this->signer->sign($message);

        self::assertNotNull($message->getSignature());
        self::assertNotNull($message->getTimestamp());
        self::assertNotNull($message->getNonce());
    }

    #[Test]
    public function verifyReturnsTrueForValidSignature(): void
    {
        $message = $this->createTestMessage(['key' => 'value']);

        $this->signer->sign($message);

        self::assertTrue($this->signer->verify($message));
    }

    #[Test]
    public function verifyReturnsFalseForTamperedPayload(): void
    {
        $message = $this->createTestMessage(['key' => 'value']);
        $this->signer->sign($message);

        // Tamper with the message by creating a new one with different payload but same signature
        $tampered = $this->createTestMessage(['key' => 'tampered']);
        $tampered->setTimestamp($message->getTimestamp());
        $tampered->setNonce($message->getNonce());
        $tampered->setSignature($message->getSignature());

        self::assertFalse($this->signer->verify($tampered));
    }

    #[Test]
    public function verifyReturnsFalseWhenNoSignature(): void
    {
        $message = $this->createTestMessage(['key' => 'value']);

        self::assertFalse($this->signer->verify($message));
    }

    #[Test]
    public function verifyReturnsFalseForExpiredTimestamp(): void
    {
        $signer = new MessageSignerService(self::TEST_SECRET, messageLifetime: 1);
        $message = $this->createTestMessage(['key' => 'value']);

        // Set an old timestamp
        $message->setTimestamp(new \DateTimeImmutable('-10 seconds'));
        $message->setNonce('test-nonce');

        $signer->sign($message);

        self::assertFalse($signer->verify($message));
    }

    #[Test]
    public function verifyReturnsFalseForReplayedNonce(): void
    {
        $message1 = $this->createTestMessage(['key' => 'value']);
        $this->signer->sign($message1);

        // First verify succeeds
        self::assertTrue($this->signer->verify($message1));

        // Second verify fails (nonce replay)
        $message2 = $this->createTestMessage(['key' => 'value']);
        $message2->setTimestamp($message1->getTimestamp());
        $message2->setNonce($message1->getNonce());
        $message2->setSignature($message1->getSignature());

        self::assertFalse($this->signer->verify($message2));
    }

    #[Test]
    public function clearNonceCacheResetsUsedNonces(): void
    {
        $message = $this->createTestMessage(['key' => 'value']);
        $this->signer->sign($message);
        $this->signer->verify($message);

        self::assertSame(1, $this->signer->getNonceCacheCount());

        $this->signer->clearNonceCache();

        self::assertSame(0, $this->signer->getNonceCacheCount());
    }

    private function createTestMessage(array $payload): SignedMessageInterface
    {
        return new class ($payload) implements SignedMessageInterface {
            use SignedMessageTrait;

            public function __construct(private readonly array $payload)
            {
            }

            public function getSignablePayload(): array
            {
                return $this->payload;
            }
        };
    }
}
