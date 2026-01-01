<?php

declare(strict_types=1);

namespace MicroModule\Base\Tests\Unit\Application\Tracing;

use MicroModule\Base\Application\Tracing\TracingHelper;
use MicroModule\Base\Application\Tracing\TracingHelperInterface;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use OpenTelemetry\API\Trace\SpanInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * Unit tests for TracingHelper class.
 */
#[CoversClass(TracingHelper::class)]
class TracingHelperTest extends MockeryTestCase
{
    private TracingHelper $helper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->helper = new TracingHelper();
    }

    #[Test]
    public function itImplementsTracingHelperInterface(): void
    {
        self::assertInstanceOf(TracingHelperInterface::class, $this->helper);
    }

    #[Test]
    public function setAttributeSetsAttributeOnSpan(): void
    {
        $span = Mockery::mock(SpanInterface::class);
        $span->shouldReceive('setAttribute')
            ->once()
            ->with('test.key', 'test_value');

        $result = $this->helper->setAttribute($span, 'test.key', 'test_value');

        self::assertSame($this->helper, $result);
    }

    #[Test]
    public function setAttributeSupportsIntegerValue(): void
    {
        $span = Mockery::mock(SpanInterface::class);
        $span->shouldReceive('setAttribute')
            ->once()
            ->with('count', 42);

        $this->helper->setAttribute($span, 'count', 42);
    }

    #[Test]
    public function setAttributeSupportsBooleanValue(): void
    {
        $span = Mockery::mock(SpanInterface::class);
        $span->shouldReceive('setAttribute')
            ->once()
            ->with('enabled', true);

        $this->helper->setAttribute($span, 'enabled', true);
    }

    #[Test]
    public function setAttributeSupportsFloatValue(): void
    {
        $span = Mockery::mock(SpanInterface::class);
        $span->shouldReceive('setAttribute')
            ->once()
            ->with('rate', 3.14);

        $this->helper->setAttribute($span, 'rate', 3.14);
    }

    #[Test]
    public function addEventAddsEventToSpan(): void
    {
        $span = Mockery::mock(SpanInterface::class);
        $span->shouldReceive('addEvent')
            ->once()
            ->with('test.event', []);

        $result = $this->helper->addEvent($span, 'test.event');

        self::assertSame($this->helper, $result);
    }

    #[Test]
    public function addEventAddsEventWithAttributes(): void
    {
        $span = Mockery::mock(SpanInterface::class);
        $attributes = ['message' => 'Error occurred', 'code' => 500];
        $span->shouldReceive('addEvent')
            ->once()
            ->with('error', $attributes);

        $this->helper->addEvent($span, 'error', $attributes);
    }

    #[Test]
    #[DataProvider('shortClassNameDataProvider')]
    public function getShortClassNameExtractsClassName(string $fullClassName, string $expected): void
    {
        $result = $this->helper->getShortClassName($fullClassName);

        self::assertSame($expected, $result);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function shortClassNameDataProvider(): array
    {
        return [
            'fully qualified class' => [
                'MicroModule\\Base\\Application\\Tracing\\TracingHelper',
                'TracingHelper',
            ],
            'class without namespace' => [
                'SimpleClass',
                'SimpleClass',
            ],
            'deeply nested namespace' => [
                'Vendor\\Package\\SubPackage\\Module\\ClassName',
                'ClassName',
            ],
            'empty string' => [
                '',
                '',
            ],
        ];
    }

    #[Test]
    public function processSpanOptionsAddsClassNameToOperationByDefault(): void
    {
        $className = 'MicroModule\\Base\\Application\\Handler\\TestHandler';
        $operation = 'execute';
        $options = [];

        [$resultOperation, $resultOptions] = $this->helper->processSpanOptions($className, $operation, $options);

        self::assertSame('TestHandler_execute', $resultOperation);
        self::assertArrayNotHasKey(TracingHelperInterface::KEY_OPTIONS_ADD_CLASSNAME_TO_OPERATION, $resultOptions);
    }

    #[Test]
    public function processSpanOptionsSkipsClassNameWhenOptionIsFalse(): void
    {
        $className = 'MicroModule\\Base\\Application\\Handler\\TestHandler';
        $operation = 'execute';
        $options = [TracingHelperInterface::KEY_OPTIONS_ADD_CLASSNAME_TO_OPERATION => false];

        [$resultOperation, $resultOptions] = $this->helper->processSpanOptions($className, $operation, $options);

        self::assertSame('execute', $resultOperation);
        self::assertArrayNotHasKey(TracingHelperInterface::KEY_OPTIONS_ADD_CLASSNAME_TO_OPERATION, $resultOptions);
    }

    #[Test]
    public function processSpanOptionsPreservesOtherOptions(): void
    {
        $className = 'TestHandler';
        $operation = 'execute';
        $options = [
            'kind' => 'server',
            'attributes' => ['key' => 'value'],
        ];

        [$resultOperation, $resultOptions] = $this->helper->processSpanOptions($className, $operation, $options);

        self::assertArrayHasKey('kind', $resultOptions);
        self::assertArrayHasKey('attributes', $resultOptions);
        self::assertSame('server', $resultOptions['kind']);
    }
}
