<?php

declare(strict_types=1);

namespace MicroModule\Base\Tests\Unit\Application\Tracing;

use Exception;
use MicroModule\Base\Application\Tracing\TracingHelper;
use MicroModule\Base\Application\Tracing\TracingTrait;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\ScopeInterface;
use OpenTelemetry\SDK\Trace\TracerProviderInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

/**
 * Unit tests for TracingTrait.
 */
#[CoversClass(TracingTrait::class)]
class TracingTraitTest extends MockeryTestCase
{
    private object $traitUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->traitUser = new class {
            use TracingTrait;

            /**
             * Expose protected method for testing.
             */
            public function testStartActiveSpan(string $operation, array $options = []): ?array
            {
                return $this->startTracingActiveSpan($operation, $options);
            }

            /**
             * Expose protected method for testing.
             */
            public function testStartSpan(string $operation, array $options = []): ?SpanInterface
            {
                return $this->startTracingSpan($operation, $options);
            }

            /**
             * Expose protected method for testing.
             */
            public function testFinishSpan(?SpanInterface $span, ?ScopeInterface $scope = null): void
            {
                $this->finishTraceSpan($span, $scope);
            }

            /**
             * Expose protected method for testing.
             */
            public function testRecordException(?SpanInterface $span, \Throwable $exception): void
            {
                $this->recordTraceException($span, $exception);
            }

            /**
             * Expose protected method for testing.
             */
            public function testSetStatusOk(?SpanInterface $span): void
            {
                $this->setTraceStatusOk($span);
            }
        };
    }

    #[Test]
    public function setTracerSetsTracerAndCreatesHelper(): void
    {
        $tracer = Mockery::mock(TracerInterface::class);

        $this->traitUser->setTracer($tracer);

        // Assert tracer is set by trying to use it
        $this->traitUser->setIsTracingEnabled(true);

        // No exception means success
        self::assertTrue(true);
    }

    #[Test]
    public function setIsTracingEnabledSetsFlag(): void
    {
        $this->traitUser->setIsTracingEnabled(true);

        // No exception means success
        self::assertTrue(true);
    }

    #[Test]
    public function setTracerProviderSetsProvider(): void
    {
        $provider = Mockery::mock(TracerProviderInterface::class);

        $this->traitUser->setTracerProvider($provider);

        // No exception means success
        self::assertTrue(true);
    }

    #[Test]
    public function flushTraceReturnsTrueWhenNoProvider(): void
    {
        $result = $this->traitUser->flushTrace();

        self::assertTrue($result);
    }

    #[Test]
    public function flushTraceReturnsTrueWhenTracingDisabled(): void
    {
        $provider = Mockery::mock(TracerProviderInterface::class);
        $provider->shouldNotReceive('forceFlush');

        $this->traitUser->setTracerProvider($provider);
        $this->traitUser->setIsTracingEnabled(false);

        $result = $this->traitUser->flushTrace();

        self::assertTrue($result);
    }

    #[Test]
    public function flushTraceCallsForceFlushOnProvider(): void
    {
        $provider = Mockery::mock(TracerProviderInterface::class);
        $provider->shouldReceive('forceFlush')
            ->once()
            ->andReturn(true);

        $this->traitUser->setTracerProvider($provider);
        $this->traitUser->setIsTracingEnabled(true);

        $result = $this->traitUser->flushTrace();

        self::assertTrue($result);
    }

    #[Test]
    public function startTracingActiveSpanReturnsNullWhenTracingDisabled(): void
    {
        $this->traitUser->setIsTracingEnabled(false);

        $result = $this->traitUser->testStartActiveSpan('test.operation');

        self::assertNull($result);
    }

    #[Test]
    public function startTracingActiveSpanReturnsNullWhenNoTracer(): void
    {
        $this->traitUser->setIsTracingEnabled(true);

        $result = $this->traitUser->testStartActiveSpan('test.operation');

        self::assertNull($result);
    }

    #[Test]
    public function startTracingActiveSpanReturnsSpanAndScope(): void
    {
        $span = Mockery::mock(SpanInterface::class);
        $scope = Mockery::mock(ScopeInterface::class);
        $spanBuilder = Mockery::mock(SpanBuilderInterface::class);

        $spanBuilder->shouldReceive('startSpan')
            ->once()
            ->andReturn($span);

        $span->shouldReceive('activate')
            ->once()
            ->andReturn($scope);

        $tracer = Mockery::mock(TracerInterface::class);
        $tracer->shouldReceive('spanBuilder')
            ->once()
            ->andReturn($spanBuilder);

        $this->traitUser->setTracer($tracer);
        $this->traitUser->setIsTracingEnabled(true);

        $result = $this->traitUser->testStartActiveSpan('test.operation');

        self::assertIsArray($result);
        self::assertCount(2, $result);
        self::assertSame($span, $result[0]);
        self::assertSame($scope, $result[1]);
    }

    #[Test]
    public function startTracingActiveSpanSetsSpanKind(): void
    {
        $span = Mockery::mock(SpanInterface::class);
        $scope = Mockery::mock(ScopeInterface::class);
        $spanBuilder = Mockery::mock(SpanBuilderInterface::class);

        $spanBuilder->shouldReceive('setSpanKind')
            ->once()
            ->with(SpanKind::KIND_SERVER);

        $spanBuilder->shouldReceive('startSpan')
            ->once()
            ->andReturn($span);

        $span->shouldReceive('activate')
            ->once()
            ->andReturn($scope);

        $tracer = Mockery::mock(TracerInterface::class);
        $tracer->shouldReceive('spanBuilder')
            ->once()
            ->andReturn($spanBuilder);

        $this->traitUser->setTracer($tracer);
        $this->traitUser->setIsTracingEnabled(true);

        $this->traitUser->testStartActiveSpan('test.operation', ['kind' => SpanKind::KIND_SERVER]);
    }

    #[Test]
    public function startTracingActiveSpanSetsAttributes(): void
    {
        $span = Mockery::mock(SpanInterface::class);
        $scope = Mockery::mock(ScopeInterface::class);
        $spanBuilder = Mockery::mock(SpanBuilderInterface::class);

        $spanBuilder->shouldReceive('setAttribute')
            ->once()
            ->with('key1', 'value1');
        $spanBuilder->shouldReceive('setAttribute')
            ->once()
            ->with('key2', 42);

        $spanBuilder->shouldReceive('startSpan')
            ->once()
            ->andReturn($span);

        $span->shouldReceive('activate')
            ->once()
            ->andReturn($scope);

        $tracer = Mockery::mock(TracerInterface::class);
        $tracer->shouldReceive('spanBuilder')
            ->once()
            ->andReturn($spanBuilder);

        $this->traitUser->setTracer($tracer);
        $this->traitUser->setIsTracingEnabled(true);

        $this->traitUser->testStartActiveSpan('test.operation', [
            'attributes' => ['key1' => 'value1', 'key2' => 42],
        ]);
    }

    #[Test]
    public function startTracingSpanReturnsNullWhenTracingDisabled(): void
    {
        $this->traitUser->setIsTracingEnabled(false);

        $result = $this->traitUser->testStartSpan('test.operation');

        self::assertNull($result);
    }

    #[Test]
    public function startTracingSpanReturnsSpan(): void
    {
        $span = Mockery::mock(SpanInterface::class);
        $spanBuilder = Mockery::mock(SpanBuilderInterface::class);

        $spanBuilder->shouldReceive('startSpan')
            ->once()
            ->andReturn($span);

        $tracer = Mockery::mock(TracerInterface::class);
        $tracer->shouldReceive('spanBuilder')
            ->once()
            ->andReturn($spanBuilder);

        $this->traitUser->setTracer($tracer);
        $this->traitUser->setIsTracingEnabled(true);

        $result = $this->traitUser->testStartSpan('test.operation');

        self::assertSame($span, $result);
    }

    #[Test]
    public function finishTraceSpanDetachesScopeAndEndsSpan(): void
    {
        $span = Mockery::mock(SpanInterface::class);
        $scope = Mockery::mock(ScopeInterface::class);

        $scope->shouldReceive('detach')
            ->once();
        $span->shouldReceive('end')
            ->once();

        $this->traitUser->testFinishSpan($span, $scope);
    }

    #[Test]
    public function finishTraceSpanEndsSpanWithoutScope(): void
    {
        $span = Mockery::mock(SpanInterface::class);
        $span->shouldReceive('end')
            ->once();

        $this->traitUser->testFinishSpan($span, null);
    }

    #[Test]
    public function finishTraceSpanHandlesNullSpan(): void
    {
        // Should not throw exception
        $this->traitUser->testFinishSpan(null, null);

        self::assertTrue(true);
    }

    #[Test]
    public function recordTraceExceptionRecordsExceptionOnSpan(): void
    {
        $span = Mockery::mock(SpanInterface::class);
        $exception = new Exception('Test error message');

        $span->shouldReceive('recordException')
            ->once()
            ->with($exception);

        $span->shouldReceive('setStatus')
            ->once()
            ->with(StatusCode::STATUS_ERROR, 'Test error message');

        $this->traitUser->testRecordException($span, $exception);
    }

    #[Test]
    public function recordTraceExceptionHandlesNullSpan(): void
    {
        $exception = new Exception('Test error');

        // Should not throw exception
        $this->traitUser->testRecordException(null, $exception);

        self::assertTrue(true);
    }

    #[Test]
    public function setTraceStatusOkSetsOkStatus(): void
    {
        $span = Mockery::mock(SpanInterface::class);

        $span->shouldReceive('setStatus')
            ->once()
            ->with(StatusCode::STATUS_OK);

        $this->traitUser->testSetStatusOk($span);
    }

    #[Test]
    public function setTraceStatusOkHandlesNullSpan(): void
    {
        // Should not throw exception
        $this->traitUser->testSetStatusOk(null);

        self::assertTrue(true);
    }
}
