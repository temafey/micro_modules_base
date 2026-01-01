<?php

declare(strict_types=1);

namespace MicroModule\Base\Application\Tracing;

use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface as ApiTracerProviderInterface;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Common\Export\Http\PsrTransportFactory;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Trace\Sampler\AlwaysOnSampler;
use OpenTelemetry\SDK\Trace\Sampler\ParentBased;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessor;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SDK\Trace\TracerProviderInterface;
use OpenTelemetry\SemConv\ResourceAttributes;

/**
 * TracerProviderFactory - Creates and configures OpenTelemetry TracerProvider.
 *
 * Factory for creating properly configured OpenTelemetry tracer providers
 * with OTLP exporters for integration with observability backends like
 * Jaeger, Grafana Tempo, or any OTLP-compatible collector.
 *
 * Configuration options:
 * - serviceName: Name of the service for trace identification
 * - serviceVersion: Version of the service
 * - environment: Deployment environment (prod, staging, dev)
 * - otlpEndpoint: OTLP collector endpoint URL
 * - useBatchProcessor: Whether to use batch processing (recommended for production)
 *
 * @see https://opentelemetry.io/docs/instrumentation/php/
 */
class TracerProviderFactory
{
    private const DEFAULT_OTLP_ENDPOINT = 'http://localhost:4318/v1/traces';
    private const DEFAULT_SERVICE_NAME = 'microservice';
    private const DEFAULT_SERVICE_VERSION = '1.0.0';
    private const DEFAULT_ENVIRONMENT = 'development';

    /**
     * Create a TracerProvider with OTLP exporter.
     *
     * @param string $serviceName The name of the service
     * @param string $serviceVersion The version of the service
     * @param string $environment The deployment environment
     * @param string $otlpEndpoint OTLP collector endpoint URL
     * @param bool $useBatchProcessor Whether to use batch processing
     *
     * @return TracerProviderInterface Configured tracer provider
     */
    public static function create(
        string $serviceName = self::DEFAULT_SERVICE_NAME,
        string $serviceVersion = self::DEFAULT_SERVICE_VERSION,
        string $environment = self::DEFAULT_ENVIRONMENT,
        string $otlpEndpoint = self::DEFAULT_OTLP_ENDPOINT,
        bool $useBatchProcessor = true,
    ): TracerProviderInterface {
        // Create resource with service metadata
        $resource = ResourceInfoFactory::defaultResource()->merge(
            ResourceInfo::create(
                Attributes::create([
                    ResourceAttributes::SERVICE_NAME => $serviceName,
                    ResourceAttributes::SERVICE_VERSION => $serviceVersion,
                    ResourceAttributes::DEPLOYMENT_ENVIRONMENT_NAME => $environment,
                ])
            )
        );

        // Create OTLP exporter with HTTP transport
        $transport = PsrTransportFactory::discover()->create(
            $otlpEndpoint,
            'application/x-protobuf'
        );
        $exporter = new SpanExporter($transport);

        // Create span processor (batch for production, simple for development)
        $spanProcessor = $useBatchProcessor
            ? new BatchSpanProcessor($exporter)
            : new SimpleSpanProcessor($exporter);

        // Create sampler (always on with parent-based propagation)
        $sampler = new ParentBased(new AlwaysOnSampler());

        return new TracerProvider(
            spanProcessors: [$spanProcessor],
            sampler: $sampler,
            resource: $resource,
        );
    }

    /**
     * Create a TracerProvider from environment variables.
     *
     * Environment variables:
     * - OTEL_SERVICE_NAME: Service name (default: microservice)
     * - OTEL_SERVICE_VERSION: Service version (default: 1.0.0)
     * - OTEL_ENVIRONMENT: Deployment environment (default: development)
     * - OTEL_EXPORTER_OTLP_ENDPOINT: OTLP endpoint (default: http://localhost:4318/v1/traces)
     * - OTEL_USE_BATCH_PROCESSOR: Use batch processor (default: true)
     *
     * @return TracerProviderInterface Configured tracer provider
     */
    public static function createFromEnvironment(): TracerProviderInterface
    {
        $serviceName = $_ENV['OTEL_SERVICE_NAME']
            ?? getenv('OTEL_SERVICE_NAME')
            ?: self::DEFAULT_SERVICE_NAME;

        $serviceVersion = $_ENV['OTEL_SERVICE_VERSION']
            ?? getenv('OTEL_SERVICE_VERSION')
            ?: self::DEFAULT_SERVICE_VERSION;

        $environment = $_ENV['OTEL_ENVIRONMENT']
            ?? getenv('OTEL_ENVIRONMENT')
            ?: self::DEFAULT_ENVIRONMENT;

        $otlpEndpoint = $_ENV['OTEL_EXPORTER_OTLP_ENDPOINT']
            ?? getenv('OTEL_EXPORTER_OTLP_ENDPOINT')
            ?: self::DEFAULT_OTLP_ENDPOINT;

        // Ensure endpoint has /v1/traces suffix for HTTP
        if (!str_ends_with($otlpEndpoint, '/v1/traces')) {
            $otlpEndpoint = rtrim($otlpEndpoint, '/') . '/v1/traces';
        }

        $useBatchProcessor = filter_var(
            $_ENV['OTEL_USE_BATCH_PROCESSOR']
                ?? getenv('OTEL_USE_BATCH_PROCESSOR')
                ?: 'true',
            FILTER_VALIDATE_BOOLEAN
        );

        return self::create(
            $serviceName,
            $serviceVersion,
            $environment,
            $otlpEndpoint,
            $useBatchProcessor,
        );
    }

    /**
     * Create a tracer instance from a provider.
     *
     * @param ApiTracerProviderInterface $tracerProvider The tracer provider
     * @param string $instrumentationName Name of the instrumentation library
     * @param string|null $instrumentationVersion Version of the instrumentation
     *
     * @return TracerInterface Configured tracer instance
     */
    public static function createTracer(
        ApiTracerProviderInterface $tracerProvider,
        string $instrumentationName = 'micromodule-base',
        ?string $instrumentationVersion = null,
    ): TracerInterface {
        return $tracerProvider->getTracer(
            $instrumentationName,
            $instrumentationVersion,
        );
    }

    /**
     * Create a no-op TracerProvider for testing or disabled tracing.
     *
     * @return ApiTracerProviderInterface No-op tracer provider
     */
    public static function createNoop(): ApiTracerProviderInterface
    {
        return new \OpenTelemetry\API\Trace\NoopTracerProvider();
    }
}
