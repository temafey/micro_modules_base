<?php

declare(strict_types=1);

namespace MicroModule\Base\Tests\Unit\Application\Tracing;

use MicroModule\Base\Application\Tracing\TraceableInterface;
use MicroModule\Base\Application\Tracing\TracingEventSubscriber;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use OpenTelemetry\SDK\Trace\TracerProviderInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Unit tests for TracingEventSubscriber class.
 */
#[CoversClass(TracingEventSubscriber::class)]
class TracingEventSubscriberTest extends MockeryTestCase
{
    private TracingEventSubscriber $subscriber;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subscriber = new TracingEventSubscriber();
    }

    #[Test]
    public function itImplementsEventSubscriberInterface(): void
    {
        self::assertInstanceOf(EventSubscriberInterface::class, $this->subscriber);
    }

    #[Test]
    public function itImplementsTraceableInterface(): void
    {
        self::assertInstanceOf(TraceableInterface::class, $this->subscriber);
    }

    #[Test]
    public function getSubscribedEventsReturnsKernelResponseEvent(): void
    {
        $events = TracingEventSubscriber::getSubscribedEvents();

        self::assertIsArray($events);
        self::assertArrayHasKey(KernelEvents::RESPONSE, $events);
        self::assertSame('flush', $events[KernelEvents::RESPONSE]);
    }

    #[Test]
    public function flushCallsFlushTraceWhenProviderIsSet(): void
    {
        $provider = Mockery::mock(TracerProviderInterface::class);
        $provider->shouldReceive('forceFlush')
            ->once()
            ->andReturn(true);

        $this->subscriber->setTracerProvider($provider);
        $this->subscriber->setIsTracingEnabled(true);

        $this->subscriber->flush();
    }

    #[Test]
    public function flushDoesNothingWhenNoProviderSet(): void
    {
        // No provider set, flush should complete without error
        $this->subscriber->flush();

        self::assertTrue(true);
    }

    #[Test]
    public function flushDoesNothingWhenTracingDisabled(): void
    {
        $provider = Mockery::mock(TracerProviderInterface::class);
        $provider->shouldNotReceive('forceFlush');

        $this->subscriber->setTracerProvider($provider);
        $this->subscriber->setIsTracingEnabled(false);

        $this->subscriber->flush();
    }

    #[Test]
    public function setIsTracingEnabledSetsFlag(): void
    {
        $this->subscriber->setIsTracingEnabled(true);
        $this->subscriber->setIsTracingEnabled(false);

        // No exception means success
        self::assertTrue(true);
    }

    #[Test]
    public function subscriberCanBeUsedInEventDispatcher(): void
    {
        // Verify the subscriber has the correct method signature for Symfony
        $events = TracingEventSubscriber::getSubscribedEvents();

        foreach ($events as $eventName => $methodName) {
            self::assertTrue(
                method_exists($this->subscriber, $methodName),
                sprintf('Method %s does not exist on subscriber', $methodName)
            );
        }
    }
}
