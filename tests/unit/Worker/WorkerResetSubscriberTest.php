<?php

declare(strict_types=1);

namespace MicroModule\Base\Tests\Unit\Worker;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use MicroModule\Base\Worker\WorkerResetSubscriber;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Service\ResetInterface;

#[CoversClass(WorkerResetSubscriber::class)]
final class WorkerResetSubscriberTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
    }

    #[Test]
    public function subscribesToTerminateEvent(): void
    {
        $events = WorkerResetSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(KernelEvents::TERMINATE, $events);
        self::assertSame(['onKernelTerminate', -1024], $events[KernelEvents::TERMINATE]);
    }

    #[Test]
    public function resetsClearsDoctrine(): void
    {
        /** @var Connection&MockInterface $connection */
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('isConnected')->once()->andReturn(true);

        /** @var EntityManagerInterface&MockInterface $em */
        $em = Mockery::mock(EntityManagerInterface::class);
        $em->shouldReceive('clear')->once();
        $em->shouldReceive('getConnection')->once()->andReturn($connection);

        /** @var ManagerRegistry&MockInterface $doctrine */
        $doctrine = Mockery::mock(ManagerRegistry::class);
        $doctrine->shouldReceive('getManagers')->once()->andReturn(['default' => $em]);

        $subscriber = new WorkerResetSubscriber(doctrine: $doctrine);
        $event = $this->createTerminateEvent(true);

        $subscriber->onKernelTerminate($event);
    }

    #[Test]
    public function resetsResettableServices(): void
    {
        /** @var ResetInterface&MockInterface $service1 */
        $service1 = Mockery::mock(ResetInterface::class);
        $service1->shouldReceive('reset')->once();

        /** @var ResetInterface&MockInterface $service2 */
        $service2 = Mockery::mock(ResetInterface::class);
        $service2->shouldReceive('reset')->once();

        $subscriber = new WorkerResetSubscriber(resettableServices: [$service1, $service2]);
        $event = $this->createTerminateEvent(true);

        $subscriber->onKernelTerminate($event);
    }

    #[Test]
    public function skipsSubRequests(): void
    {
        /** @var ManagerRegistry&MockInterface $doctrine */
        $doctrine = Mockery::mock(ManagerRegistry::class);
        $doctrine->shouldNotReceive('getManagers');

        $subscriber = new WorkerResetSubscriber(doctrine: $doctrine);
        $event = $this->createTerminateEvent(false);

        $subscriber->onKernelTerminate($event);
    }

    #[Test]
    public function handlesServiceResetFailureGracefully(): void
    {
        /** @var ResetInterface&MockInterface $failingService */
        $failingService = Mockery::mock(ResetInterface::class);
        $failingService->shouldReceive('reset')
            ->once()
            ->andThrow(new \RuntimeException('Reset failed'));

        $subscriber = new WorkerResetSubscriber(resettableServices: [$failingService]);
        $event = $this->createTerminateEvent(true);

        // Should not throw
        $subscriber->onKernelTerminate($event);
        self::assertTrue(true);
    }

    private function createTerminateEvent(bool $isMainRequest): TerminateEvent
    {
        /** @var HttpKernelInterface&MockInterface $kernel */
        $kernel = Mockery::mock(HttpKernelInterface::class);
        $request = Request::create('/');
        $response = new Response();

        $requestType = $isMainRequest
            ? HttpKernelInterface::MAIN_REQUEST
            : HttpKernelInterface::SUB_REQUEST;

        return new TerminateEvent($kernel, $request, $response);
    }
}
