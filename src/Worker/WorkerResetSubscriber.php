<?php

declare(strict_types=1);

namespace MicroModule\Base\Worker;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Resets stateful services between FrankenPHP worker requests.
 *
 * In worker mode, PHP processes stay alive between requests. This subscriber
 * ensures that stateful services are properly reset to prevent:
 * - Memory leaks (EntityManager identity map growth)
 * - Stale data (cached doctrine entities)
 * - Connection exhaustion
 *
 * @see https://frankenphp.dev/docs/worker/
 */
final readonly class WorkerResetSubscriber implements EventSubscriberInterface
{
    /**
     * @param iterable<ResetInterface>|null $resettableServices Services tagged with 'kernel.reset'
     */
    public function __construct(
        private ?ManagerRegistry $doctrine = null,
        private ?iterable $resettableServices = null,
        private ?LoggerInterface $logger = null,
    ) {
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::TERMINATE => ['onKernelTerminate', -1024],
        ];
    }

    public function onKernelTerminate(TerminateEvent $event): void
    {
        if (! $event->isMainRequest()) {
            return;
        }

        $this->resetDoctrine();
        $this->resetServices();
    }

    private function resetDoctrine(): void
    {
        if ($this->doctrine === null) {
            return;
        }

        foreach ($this->doctrine->getManagers() as $name => $manager) {
            if ($manager instanceof EntityManagerInterface) {
                $manager->clear();

                $connection = $manager->getConnection();
                if (! $connection->isConnected()) {
                    try {
                        $connection->connect();
                    } catch (\Throwable $e) {
                        $this->logger?->warning('Failed to reconnect database', [
                            'manager' => $name,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
        }
    }

    private function resetServices(): void
    {
        if ($this->resettableServices === null) {
            return;
        }

        foreach ($this->resettableServices as $service) {
            try {
                $service->reset();
            } catch (\Throwable $e) {
                $this->logger?->warning('Failed to reset service', [
                    'service' => $service::class,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
