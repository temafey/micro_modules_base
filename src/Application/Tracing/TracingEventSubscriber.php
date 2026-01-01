<?php

declare(strict_types=1);

namespace MicroModule\Base\Application\Tracing;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * TracingEventSubscriber - Flushes OpenTelemetry spans on kernel response.
 *
 * Subscribes to Symfony kernel events to ensure all trace spans
 * are flushed to the exporter before the response is sent.
 *
 * Migrated from OpenTracing to OpenTelemetry API.
 */
class TracingEventSubscriber implements EventSubscriberInterface, TraceableInterface
{
    use TracingTrait;

    /**
     * Return list of subscribed events.
     *
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'flush',
        ];
    }

    /**
     * Flush pending spans to the OpenTelemetry exporter.
     *
     * Called on kernel.response event to ensure all spans
     * are exported before the request completes.
     */
    public function flush(): void
    {
        $this->flushTrace();
    }
}
