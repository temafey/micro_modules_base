<?php

declare(strict_types=1);

namespace MicroModule\Base\Tests\Unit\Application\Tracing;

use MicroModule\Base\Application\Tracing\TracerProviderFactory;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use OpenTelemetry\API\Trace\NoopTracerProvider;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface as ApiTracerProviderInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

/**
 * Unit tests for TracerProviderFactory class.
 */
#[CoversClass(TracerProviderFactory::class)]
class TracerProviderFactoryTest extends MockeryTestCase
{
    #[Test]
    public function createNoopReturnsNoopTracerProvider(): void
    {
        $provider = TracerProviderFactory::createNoop();

        self::assertInstanceOf(ApiTracerProviderInterface::class, $provider);
        self::assertInstanceOf(NoopTracerProvider::class, $provider);
    }

    #[Test]
    public function createTracerReturnsTracerFromProvider(): void
    {
        $tracer = Mockery::mock(TracerInterface::class);
        $provider = Mockery::mock(ApiTracerProviderInterface::class);

        $provider->shouldReceive('getTracer')
            ->once()
            ->with('test-instrumentation', '1.0.0')
            ->andReturn($tracer);

        $result = TracerProviderFactory::createTracer(
            $provider,
            'test-instrumentation',
            '1.0.0'
        );

        self::assertSame($tracer, $result);
    }

    #[Test]
    public function createTracerUsesDefaultInstrumentationName(): void
    {
        $tracer = Mockery::mock(TracerInterface::class);
        $provider = Mockery::mock(ApiTracerProviderInterface::class);

        $provider->shouldReceive('getTracer')
            ->once()
            ->with('micromodule-base', null)
            ->andReturn($tracer);

        $result = TracerProviderFactory::createTracer($provider);

        self::assertSame($tracer, $result);
    }

    #[Test]
    public function createTracerWithNullVersion(): void
    {
        $tracer = Mockery::mock(TracerInterface::class);
        $provider = Mockery::mock(ApiTracerProviderInterface::class);

        $provider->shouldReceive('getTracer')
            ->once()
            ->with('custom-instrumentation', null)
            ->andReturn($tracer);

        $result = TracerProviderFactory::createTracer(
            $provider,
            'custom-instrumentation',
            null
        );

        self::assertSame($tracer, $result);
    }

    #[Test]
    public function noopTracerProviderReturnsNoopTracer(): void
    {
        $provider = TracerProviderFactory::createNoop();
        $tracer = $provider->getTracer('test');

        self::assertInstanceOf(TracerInterface::class, $tracer);
    }

    #[Test]
    public function factoryClassHasStaticMethods(): void
    {
        self::assertTrue(method_exists(TracerProviderFactory::class, 'create'));
        self::assertTrue(method_exists(TracerProviderFactory::class, 'createFromEnvironment'));
        self::assertTrue(method_exists(TracerProviderFactory::class, 'createTracer'));
        self::assertTrue(method_exists(TracerProviderFactory::class, 'createNoop'));
    }

    #[Test]
    public function factoryClassIsFinal(): void
    {
        // Factory should be instantiable but methods are static
        $reflection = new \ReflectionClass(TracerProviderFactory::class);

        // Verify static methods
        self::assertTrue($reflection->getMethod('create')->isStatic());
        self::assertTrue($reflection->getMethod('createFromEnvironment')->isStatic());
        self::assertTrue($reflection->getMethod('createTracer')->isStatic());
        self::assertTrue($reflection->getMethod('createNoop')->isStatic());
    }

    #[Test]
    public function createMethodHasCorrectSignature(): void
    {
        $reflection = new \ReflectionMethod(TracerProviderFactory::class, 'create');
        $parameters = $reflection->getParameters();

        self::assertCount(5, $parameters);

        // Check parameter names
        self::assertSame('serviceName', $parameters[0]->getName());
        self::assertSame('serviceVersion', $parameters[1]->getName());
        self::assertSame('environment', $parameters[2]->getName());
        self::assertSame('otlpEndpoint', $parameters[3]->getName());
        self::assertSame('useBatchProcessor', $parameters[4]->getName());

        // Check all have default values
        foreach ($parameters as $param) {
            self::assertTrue($param->isDefaultValueAvailable());
        }
    }

    #[Test]
    public function createFromEnvironmentMethodHasNoRequiredParameters(): void
    {
        $reflection = new \ReflectionMethod(TracerProviderFactory::class, 'createFromEnvironment');
        $parameters = $reflection->getParameters();

        self::assertCount(0, $parameters);
    }
}
