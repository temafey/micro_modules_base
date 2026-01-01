<?php

declare(strict_types=1);

namespace MicroModule\Base\Application\Tracing;

use OpenTelemetry\API\Trace\SpanInterface;

/**
 * Interface TracingHelperInterface.
 *
 * Provides helper methods for distributed tracing using OpenTelemetry.
 * Migrated from OpenTracing to OpenTelemetry API.
 */
interface TracingHelperInterface
{
    /** Attribute key for process correlation UUID. */
    public const KEY_PROCESS_UUID = 'process_uuid';

    /** Attribute key for component identification. */
    public const KEY_COMPONENT_TAG = 'component';

    /** Attribute key for entity UUID. */
    public const KEY_ENTITY_UUID = 'entity_uuid';

    /** Attribute key for command name. */
    public const KEY_COMMAND_TAG = 'command';

    /** Component type: task. */
    public const KEY_COMPONENT_TASK = 'task';

    /** Component type: command bus. */
    public const KEY_COMPONENT_COMMAND_BUS = 'command_bus';

    /** Component type: event model. */
    public const KEY_COMPONENT_EVENT_MODEL = 'event_model';

    /** Component type: saga. */
    public const KEY_COMPONENT_SAGA = 'saga';

    /** Option key: add class name to operation. */
    public const KEY_OPTIONS_ADD_CLASSNAME_TO_OPERATION = 'add_classname_to_operation';

    /**
     * Set an attribute on the span.
     *
     * Replaces OpenTracing's setTag() method.
     * Attributes are key-value pairs providing context about the traced operation.
     *
     * @param SpanInterface $span The span to add the attribute to
     * @param string $key The attribute name
     * @param string|int|float|bool $value The attribute value
     */
    public function setAttribute(SpanInterface $span, string $key, string|int|float|bool $value): self;

    /**
     * Add an event to the span.
     *
     * Replaces OpenTracing's log() method.
     * Events are time-stamped annotations describing something that happened during the span's lifetime.
     *
     * @param SpanInterface $span The span to add the event to
     * @param string $name The event name
     * @param array<string, string|int|float|bool> $attributes Optional attributes for the event
     */
    public function addEvent(SpanInterface $span, string $name, array $attributes = []): self;

    /**
     * Generate and return list of span options.
     *
     * Processes options for span creation, including operation name formatting.
     *
     * @param string $calledClassName The class initiating the span
     * @param string $operation The operation name
     * @param array<string, mixed> $options Additional options
     *
     * @return array<string, mixed> Processed span options
     */
    public function processSpanOptions(string $calledClassName, string $operation, array $options): array;

    /**
     * Generate and return short name of class.
     *
     * Extracts the class name without namespace for cleaner span names.
     *
     * @param string $fullClassDefinition The fully qualified class name
     *
     * @return string The short class name
     */
    public function getShortClassName(string $fullClassDefinition): string;
}
