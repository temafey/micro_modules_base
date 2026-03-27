<?php

declare(strict_types=1);

namespace MicroModule\Base\Tests\Unit\Security;

use MicroModule\Base\Security\InvalidSignatureException;
use MicroModule\Base\Security\MessageSignerService;
use MicroModule\Base\Security\SignedMessageInterface;
use MicroModule\Base\Security\SignedMessageMiddleware;
use MicroModule\Base\Security\SignedMessageTrait;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SignedMessageMiddleware::class)]
final class SignedMessageMiddlewareTest extends TestCase
{
    private MessageSignerService&MockInterface $signer;

    protected function setUp(): void
    {
        $this->signer = Mockery::mock(MessageSignerService::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    #[Test]
    public function nonSignedCommandsPassThrough(): void
    {
        $middleware = new SignedMessageMiddleware($this->signer);
        $command = new \stdClass();
        $expectedResult = 'result';

        $result = $middleware->execute($command, fn () => $expectedResult);

        self::assertSame($expectedResult, $result);
    }

    #[Test]
    public function validSignatureAllowsExecution(): void
    {
        $middleware = new SignedMessageMiddleware($this->signer);
        $command = $this->createSignedCommand('valid-sig');

        $this->signer->shouldReceive('verify')
            ->once()
            ->with($command)
            ->andReturn(true);

        $result = $middleware->execute($command, fn () => 'ok');

        self::assertSame('ok', $result);
    }

    #[Test]
    public function invalidSignatureThrowsException(): void
    {
        $middleware = new SignedMessageMiddleware($this->signer);
        $command = $this->createSignedCommand('bad-sig');

        $this->signer->shouldReceive('verify')
            ->once()
            ->with($command)
            ->andReturn(false);

        $this->expectException(InvalidSignatureException::class);

        $middleware->execute($command, fn () => 'ok');
    }

    #[Test]
    public function missingSignatureWithRequireThrowsException(): void
    {
        $middleware = new SignedMessageMiddleware($this->signer, requireSignature: true);
        $command = $this->createSignedCommand(null);

        $this->expectException(InvalidSignatureException::class);

        $middleware->execute($command, fn () => 'ok');
    }

    #[Test]
    public function missingSignatureWithoutRequirePassesThrough(): void
    {
        $middleware = new SignedMessageMiddleware($this->signer, requireSignature: false);
        $command = $this->createSignedCommand(null);

        $result = $middleware->execute($command, fn () => 'ok');

        self::assertSame('ok', $result);
    }

    private function createSignedCommand(?string $signature): SignedMessageInterface
    {
        $command = new class () implements SignedMessageInterface {
            use SignedMessageTrait;

            public function getSignablePayload(): array
            {
                return ['data' => 'test'];
            }
        };

        if ($signature !== null) {
            $command->setSignature($signature);
        }

        return $command;
    }
}
