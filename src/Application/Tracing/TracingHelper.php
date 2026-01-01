<?php

declare(strict_types=1);

namespace MicroModule\Base\Application\Tracing;

use OpenTelemetry\API\Trace\SpanInterface;

/**
 * TracingHelper implementation for OpenTelemetry.
 *
 * Provides helper methods for setting span attributes and adding events.
 * Migrated from OpenTracing to OpenTelemetry API.
 */
class TracingHelper implements TracingHelperInterface
{
    /**
     * Set an attribute on the span.
     *
     * @param SpanInterface $span The span to add the attribute to
     * @param string $key The attribute name
     * @param string|int|float|bool $value The attribute value
     */
    public function setAttribute(SpanInterface $span, string $key, string|int|float|bool $value): self
    {
        $span->setAttribute($key, $value);

        return $this;
    }

    /**
     * Add an event to the span.
     *
     * @param SpanInterface $span The span to add the event to
     * @param string $name The event name
     * @param array<string, string|int|float|bool> $attributes Optional attributes for the event
     */
    public function addEvent(SpanInterface $span, string $name, array $attributes = []): self
    {
        $span->addEvent($name, $attributes);

        return $this;
    }

    /**
     * Generate and return list of span options.
     *
     * @param string $calledClassName The class initiating the span
     * @param string $operation The operation name
     * @param array<string, mixed> $options Additional options
     *
     * @return array<string, mixed> Processed span options [operation, options]
     */
    public function processSpanOptions(string $calledClassName, string $operation, array $options): array
    {
        if (!isset($options[self::KEY_OPTIONS_ADD_CLASSNAME_TO_OPERATION])
            || $options[self::KEY_OPTIONS_ADD_CLASSNAME_TO_OPERATION] !== false
        ) {
            $operation = sprintf('%s_%s', $this->getShortClassName($calledClassName), $operation);
        }
        unset($options[self::KEY_OPTIONS_ADD_CLASSNAME_TO_OPERATION]);

        return [$operation, $options];
    }

    /**
     * Generate and return short name of class.
     *
     * @param string $fullClassDefinition The fully qualified class name
     *
     * @return string The short class name
     */
    public function getShortClassName(string $fullClassDefinition): string
    {
        $shortName = strrchr($fullClassDefinition, '\\');

        return $shortName !== false ? substr($shortName, 1) : $fullClassDefinition;
    }
}
