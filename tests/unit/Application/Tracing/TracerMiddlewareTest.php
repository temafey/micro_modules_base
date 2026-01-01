<?php

declare(strict_types=1);

namespace MicroModule\Base\Tests\Unit\Application\Tracing;

use Exception;
use League\Tactician\Handler\Locator\HandlerLocator;
use League\Tactician\Handler\MethodNameInflector\MethodNameInflector;
use MicroModule\Base\Application\Tracing\TracerMiddleware;
use MicroModule\Base\Application\Tracing\TracingHelper;
use MicroModule\Base\Application\Tracing\TracingHelperInterface;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\ScopeInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use stdClass;

/**
 * Unit tests for TracerMiddleware class.
 */
#[CoversClass(TracerMiddleware::class)]
class TracerMiddlewareTest extends MockeryTestCase
{
    private HandlerLocator $handlerLocator;
    private MethodNameInflector $methodNameInflector;
    private TracerMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handlerLocator = Mockery::mock(HandlerLocator::class);
        $this->methodNameInflector = Mockery::mock(MethodNameInflector::class);
        $this->middleware = new TracerMiddleware($this->handlerLocator, $this->methodNameInflector);
    }

    #[Test]
    public function executePassesThroughWhenTracingDisabled(): void
    {
        $command = new stdClass();
        $expectedResult = 'test-result';

        $next = function (object $cmd) use ($expectedResult) {
            return $expectedResult;
        };

        $result = $this->middleware->execute($command, $next);

        self::assertSame($expectedResult, $result);
    }

    #[Test]
    public function executePassesThroughWhenNoTracer(): void
    {
        $command = new stdClass();
        $expectedResult = ['data' => 'test'];

        $this->middleware->setIsTracingEnabled(true);

        $next = function (object $cmd) use ($expectedResult) {
            return $expectedResult;
        };

        $result = $this->middleware->execute($command, $next);

        self::assertSame($expectedResult, $result);
    }

    #[Test]
    public function executeCreatesSpanAndFinishesOnSuccess(): void
    {
        $command = new stdClass();
        $handler = new class {
            public function handle(): void
            {
            }
        };
        $expectedResult = 'success';

        $span = Mockery::mock(SpanInterface::class);
        $scope = Mockery::mock(ScopeInterface::class);
        $spanBuilder = Mockery::mock(SpanBuilderInterface::class);
        $tracer = Mockery::mock(TracerInterface::class);
        $tracingHelper = Mockery::mock(TracingHelper::class);

        $this->handlerLocator->shouldReceive('getHandlerForCommand')
            ->once()
            ->with(stdClass::class)
            ->andReturn($handler);

        $this->methodNameInflector->shouldReceive('inflect')
            ->once()
            ->with($command, $handler)
            ->andReturn('handle');

        $tracingHelper->shouldReceive('getShortClassName')
            ->once()
            ->andReturn('AnonymousHandler');

        $tracingHelper->shouldReceive('processSpanOptions')
            ->once()
            ->with(TracerMiddleware::class, 'AnonymousHandler_handle', Mockery::type('array'))
            ->andReturn(['AnonymousHandler_handle', []]);

        $tracer->shouldReceive('spanBuilder')
            ->once()
            ->with('AnonymousHandler_handle')
            ->andReturn($spanBuilder);

        $spanBuilder->shouldReceive('startSpan')
            ->once()
            ->andReturn($span);

        $span->shouldReceive('activate')
            ->once()
            ->andReturn($scope);

        $tracingHelper->shouldReceive('setAttribute')
            ->with($span, TracingHelperInterface::KEY_COMPONENT_TAG, TracingHelperInterface::KEY_COMPONENT_COMMAND_BUS)
            ->once()
            ->andReturn($tracingHelper);

        $tracingHelper->shouldReceive('setAttribute')
            ->with($span, TracingHelperInterface::KEY_COMMAND_TAG, stdClass::class)
            ->once()
            ->andReturn($tracingHelper);

        $span->shouldReceive('setStatus')
            ->once()
            ->with(StatusCode::STATUS_OK);

        $scope->shouldReceive('detach')
            ->once();

        $span->shouldReceive('end')
            ->once();

        // Set up middleware with tracing enabled
        $this->middleware->setTracer($tracer);
        $this->middleware->setIsTracingEnabled(true);

        // Use reflection to set the tracingHelper
        $reflection = new \ReflectionClass($this->middleware);
        $property = $reflection->getProperty('tracingHelper');
        $property->setValue($this->middleware, $tracingHelper);

        $next = function (object $cmd) use ($expectedResult) {
            return $expectedResult;
        };

        $result = $this->middleware->execute($command, $next);

        self::assertSame($expectedResult, $result);
    }

    #[Test]
    public function executeRecordsExceptionAndRethrows(): void
    {
        $command = new stdClass();
        $handler = new class {
            public function handle(): void
            {
            }
        };
        $exception = new RuntimeException('Test error', 500);

        $span = Mockery::mock(SpanInterface::class);
        $scope = Mockery::mock(ScopeInterface::class);
        $spanBuilder = Mockery::mock(SpanBuilderInterface::class);
        $tracer = Mockery::mock(TracerInterface::class);
        $tracingHelper = Mockery::mock(TracingHelper::class);

        $this->handlerLocator->shouldReceive('getHandlerForCommand')
            ->once()
            ->with(stdClass::class)
            ->andReturn($handler);

        $this->methodNameInflector->shouldReceive('inflect')
            ->once()
            ->with($command, $handler)
            ->andReturn('handle');

        $tracingHelper->shouldReceive('getShortClassName')
            ->once()
            ->andReturn('AnonymousHandler');

        $tracingHelper->shouldReceive('processSpanOptions')
            ->once()
            ->with(TracerMiddleware::class, 'AnonymousHandler_handle', Mockery::type('array'))
            ->andReturn(['AnonymousHandler_handle', []]);

        $tracer->shouldReceive('spanBuilder')
            ->once()
            ->andReturn($spanBuilder);

        $spanBuilder->shouldReceive('startSpan')
            ->once()
            ->andReturn($span);

        $span->shouldReceive('activate')
            ->once()
            ->andReturn($scope);

        $tracingHelper->shouldReceive('setAttribute')
            ->times(3)
            ->andReturn($tracingHelper);

        // Exception recording
        $span->shouldReceive('recordException')
            ->once()
            ->with($exception);

        $span->shouldReceive('setStatus')
            ->once()
            ->with(StatusCode::STATUS_ERROR, 'Test error');

        $tracingHelper->shouldReceive('addEvent')
            ->once()
            ->with($span, 'command.error', Mockery::type('array'));

        $scope->shouldReceive('detach')
            ->once();

        $span->shouldReceive('end')
            ->once();

        // Set up middleware
        $this->middleware->setTracer($tracer);
        $this->middleware->setIsTracingEnabled(true);

        $reflection = new \ReflectionClass($this->middleware);
        $property = $reflection->getProperty('tracingHelper');
        $property->setValue($this->middleware, $tracingHelper);

        $next = function (object $cmd) use ($exception) {
            throw $exception;
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Test error');

        $this->middleware->execute($command, $next);
    }

    #[Test]
    public function executeHandlesNullReturnFromNext(): void
    {
        $command = new stdClass();

        $next = function (object $cmd): mixed {
            return null;
        };

        $result = $this->middleware->execute($command, $next);

        self::assertNull($result);
    }

    #[Test]
    public function constructorSetsDependencies(): void
    {
        $middleware = new TracerMiddleware($this->handlerLocator, $this->methodNameInflector);

        // Use reflection to verify dependencies are set
        $reflection = new \ReflectionClass($middleware);

        $locatorProperty = $reflection->getProperty('handlerLocator');
        self::assertSame($this->handlerLocator, $locatorProperty->getValue($middleware));

        $inflectorProperty = $reflection->getProperty('methodNameInflector');
        self::assertSame($this->methodNameInflector, $inflectorProperty->getValue($middleware));
    }

    #[Test]
    public function middlewareImplementsTacticianMiddlewareInterface(): void
    {
        self::assertInstanceOf(\League\Tactician\Middleware::class, $this->middleware);
    }

    #[Test]
    public function executePreservesCommandObjectIntegrity(): void
    {
        $command = new stdClass();
        $command->data = 'original';

        $capturedCommand = null;
        $next = function (object $cmd) use (&$capturedCommand) {
            $capturedCommand = $cmd;

            return 'result';
        };

        $this->middleware->execute($command, $next);

        self::assertSame($command, $capturedCommand);
        self::assertSame('original', $capturedCommand->data);
    }
}
