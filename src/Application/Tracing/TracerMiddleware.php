<?php

declare(strict_types=1);

namespace MicroModule\Base\Application\Tracing;

use League\Tactician\Handler\Locator\HandlerLocator;
use League\Tactician\Handler\MethodNameInflector\MethodNameInflector;
use League\Tactician\Middleware;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\Context\ScopeInterface;
use Throwable;

/**
 * TracerMiddleware - Command Bus tracing middleware using OpenTelemetry.
 *
 * Wraps command execution with distributed tracing spans.
 * Each command handler execution is captured as a span with:
 * - Command class name as attribute
 * - Handler method as span name
 * - Exception details on failure
 *
 * Migrated from OpenTracing to OpenTelemetry API.
 */
class TracerMiddleware implements Middleware
{
    use TracingTrait;

    /**
     * Constructor with PHP 8 property promotion.
     */
    public function __construct(
        protected readonly HandlerLocator $handlerLocator,
        protected readonly MethodNameInflector $methodNameInflector,
    ) {
    }

    /**
     * Executes a command with distributed tracing.
     *
     * @param object $command The command to execute
     * @param callable $next The next middleware in the chain
     *
     * @return mixed The command result
     *
     * @throws Throwable Re-throws any exception after recording it
     */
    public function execute($command, callable $next)
    {
        $traceContext = $this->startTrace($command);

        if ($traceContext === null) {
            // Tracing disabled, just execute
            return $next($command);
        }

        [$span, $scope] = $traceContext;

        try {
            $returnValue = $next($command);
            $this->setTraceStatusOk($span);
        } catch (Throwable $e) {
            $this->handleTraceException($span, $command, $e);
            $this->finishTraceSpan($span, $scope);

            throw $e;
        }

        $this->finishTraceSpan($span, $scope);

        return $returnValue;
    }

    /**
     * Start tracing for the command execution.
     *
     * @param object $command The command being executed
     *
     * @return array{SpanInterface, ScopeInterface}|null Span and scope pair, or null if tracing disabled
     */
    protected function startTrace(object $command): ?array
    {
        if ($this->tracingHelper === null || $this->tracer === null || !$this->isTracingEnabled) {
            return null;
        }

        $handler = $this->handlerLocator->getHandlerForCommand($command::class);
        $methodName = $this->methodNameInflector->inflect($command, $handler);

        $traceContext = $this->startTracingActiveSpan(
            sprintf('%s_%s', $this->tracingHelper->getShortClassName($handler::class), $methodName),
            [
                TracingHelperInterface::KEY_OPTIONS_ADD_CLASSNAME_TO_OPERATION => false,
            ]
        );

        if ($traceContext === null) {
            return null;
        }

        [$span, $scope] = $traceContext;

        // Add component and command attributes
        $this->tracingHelper
            ->setAttribute(
                $span,
                TracingHelperInterface::KEY_COMPONENT_TAG,
                TracingHelperInterface::KEY_COMPONENT_COMMAND_BUS
            )
            ->setAttribute($span, TracingHelperInterface::KEY_COMMAND_TAG, $command::class);

        return [$span, $scope];
    }

    /**
     * Handle exception during command execution.
     *
     * Records the exception on the span with relevant attributes.
     *
     * @param SpanInterface $span The active span
     * @param object $command The command that failed
     * @param Throwable $exception The exception thrown
     */
    protected function handleTraceException(SpanInterface $span, object $command, Throwable $exception): void
    {
        $this->recordTraceException($span, $exception);

        $this->tracingHelper?->setAttribute($span, 'command.failed', $command::class);
        $this->tracingHelper?->addEvent($span, 'command.error', [
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ]);
    }
}
