<?php

declare(strict_types=1);

namespace MicroModule\Base\Application\Tracing;

use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\ScopeInterface;
use OpenTelemetry\SDK\Trace\TracerProviderInterface;
use Throwable;

/**
 * Trait TracingTrait.
 *
 * Provides distributed tracing capabilities using OpenTelemetry.
 * Classes using this trait gain the ability to create and manage spans.
 *
 * Migrated from OpenTracing to OpenTelemetry API.
 */
trait TracingTrait
{
    /**
     * Is tracing enabled flag.
     */
    protected bool $isTracingEnabled = false;

    /**
     * OpenTelemetry tracer instance.
     */
    protected ?TracerInterface $tracer = null;

    /**
     * Helper for tracing operations.
     */
    protected ?TracingHelperInterface $tracingHelper = null;

    /**
     * OpenTelemetry tracer provider for flushing spans.
     */
    protected ?TracerProviderInterface $tracerProvider = null;

    /**
     * Set OpenTelemetry tracer.
     */
    public function setTracer(TracerInterface $tracer): void
    {
        $this->tracer = $tracer;
        $this->tracingHelper = new TracingHelper();
    }

    /**
     * Set tracing flag.
     */
    public function setIsTracingEnabled(bool $isTracingEnabled): void
    {
        $this->isTracingEnabled = $isTracingEnabled;
    }

    /**
     * Set OpenTelemetry tracer provider for flush support.
     */
    public function setTracerProvider(TracerProviderInterface $tracerProvider): void
    {
        $this->tracerProvider = $tracerProvider;
    }

    /**
     * Flush pending spans to the exporter.
     *
     * In OpenTelemetry, flushing is handled by the TracerProvider.
     * This ensures all completed spans are exported before request end.
     *
     * @return bool True if flush was successful or no provider configured
     */
    public function flushTrace(): bool
    {
        if ($this->tracerProvider === null || !$this->isTracingEnabled) {
            return true;
        }

        return $this->tracerProvider->forceFlush();
    }

    /**
     * Starts a new active span representing a unit of work.
     *
     * The span is automatically activated and attached to the current context.
     * Returns an array containing the span and scope for proper cleanup.
     *
     * @param string $operation The operation name
     * @param array<string, mixed> $options Span options (attributes, kind, etc.)
     *
     * @return array{SpanInterface, ScopeInterface}|null Span and scope pair, or null if tracing disabled
     */
    protected function startTracingActiveSpan(string $operation, array $options = []): ?array
    {
        if (
            $this->tracer === null ||
            $this->tracingHelper === null ||
            !$this->isTracingEnabled
        ) {
            return null;
        }

        [$operation, $options] = $this->tracingHelper->processSpanOptions(static::class, $operation, $options);

        $spanBuilder = $this->tracer->spanBuilder($operation);

        // Set span kind if provided (SpanKind constants are integers)
        if (isset($options['kind']) && is_int($options['kind'])) {
            $spanBuilder->setSpanKind($options['kind']);
        }

        // Set attributes if provided
        if (isset($options['attributes']) && is_array($options['attributes'])) {
            foreach ($options['attributes'] as $key => $value) {
                $spanBuilder->setAttribute($key, $value);
            }
        }

        $span = $spanBuilder->startSpan();
        $scope = $span->activate();

        return [$span, $scope];
    }

    /**
     * Starts a new span representing a unit of work (not activated).
     *
     * Use this for parallel/async operations where you need to manage
     * the span lifecycle manually.
     *
     * @param string $operation The operation name
     * @param array<string, mixed> $options Span options (attributes, kind, parent, etc.)
     *
     * @return SpanInterface|null The created span, or null if tracing disabled
     */
    protected function startTracingSpan(string $operation, array $options = []): ?SpanInterface
    {
        if (
            $this->tracer === null ||
            $this->tracingHelper === null ||
            !$this->isTracingEnabled
        ) {
            return null;
        }

        [$operation, $options] = $this->tracingHelper->processSpanOptions(static::class, $operation, $options);

        $spanBuilder = $this->tracer->spanBuilder($operation);

        // Set span kind if provided (SpanKind constants are integers)
        if (isset($options['kind']) && is_int($options['kind'])) {
            $spanBuilder->setSpanKind($options['kind']);
        }

        // Set attributes if provided
        if (isset($options['attributes']) && is_array($options['attributes'])) {
            foreach ($options['attributes'] as $key => $value) {
                $spanBuilder->setAttribute($key, $value);
            }
        }

        return $spanBuilder->startSpan();
    }

    /**
     * Ends a span and detaches the scope if provided.
     *
     * @param SpanInterface|null $span The span to end
     * @param ScopeInterface|null $scope The scope to detach (optional)
     */
    protected function finishTraceSpan(?SpanInterface $span, ?ScopeInterface $scope = null): void
    {
        if ($scope !== null) {
            $scope->detach();
        }

        if ($span !== null) {
            $span->end();
        }
    }

    /**
     * Records an exception on the span and sets error status.
     *
     * @param SpanInterface|null $span The span to record the exception on
     * @param Throwable $exception The exception to record
     */
    protected function recordTraceException(?SpanInterface $span, Throwable $exception): void
    {
        if ($span === null) {
            return;
        }

        $span->recordException($exception);
        $span->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());
    }

    /**
     * Sets the span status to OK.
     *
     * @param SpanInterface|null $span The span to set status on
     */
    protected function setTraceStatusOk(?SpanInterface $span): void
    {
        if ($span === null) {
            return;
        }

        $span->setStatus(StatusCode::STATUS_OK);
    }
}
