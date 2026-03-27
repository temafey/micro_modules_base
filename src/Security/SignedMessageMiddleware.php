<?php

declare(strict_types=1);

namespace MicroModule\Base\Security;

use League\Tactician\Middleware;

/**
 * Tactician middleware for validating signed messages.
 *
 * Intercepts commands that implement SignedMessageInterface
 * and validates their cryptographic signatures before allowing execution.
 */
final readonly class SignedMessageMiddleware implements Middleware
{
    public function __construct(
        private MessageSignerService $messageSigner,
        private bool $requireSignature = true,
    ) {
    }

    #[\Override]
    public function execute($command, callable $next)
    {
        if (! $command instanceof SignedMessageInterface) {
            return $next($command);
        }

        $signature = $command->getSignature();

        if ($signature === null) {
            if ($this->requireSignature) {
                throw InvalidSignatureException::missingSignature($command::class);
            }

            return $next($command);
        }

        if (! $this->messageSigner->verify($command)) {
            throw InvalidSignatureException::invalidSignature($command::class);
        }

        return $next($command);
    }
}
