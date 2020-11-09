<?php

declare(strict_types=1);

namespace ElasticApmTests\UnitTests\Util;

use Closure;
use Elastic\Apm\Impl\GlobalTracerHolder;
use Elastic\Apm\Impl\TracerInterface;
use Elastic\Apm\Impl\Util\ElasticApmExtensionUtil;
use ElasticApmTests\Util\TestCaseBase;
use RuntimeException;

class TracerUnitTestCaseBase extends TestCaseBase
{
    /** @var MockEventSink */
    protected $mockEventSink;

    /** @var TracerInterface */
    protected $tracer;

    /**
     * @param mixed        $name
     * @param array<mixed> $data
     * @param mixed        $dataName
     */
    public function __construct($name = null, array $data = [], $dataName = '')
    {
        if (ElasticApmExtensionUtil::isLoaded()) {
            throw new RuntimeException(
                ElasticApmExtensionUtil::EXTENSION_NAME . ' should NOT be loaded when running unit tests'
                . ' because it will cause a clash.'
            );
        }

        parent::__construct($name, $data, $dataName);
    }

    public function setUp(): void
    {
        $this->setUpTestEnv();
    }

    protected function setUpTestEnv(?Closure $tracerBuildCallback = null, bool $shouldCreateMockEventSink = true): void
    {
        if ($shouldCreateMockEventSink) {
            $this->mockEventSink = new MockEventSink();
        }

        $builder = self::buildTracerForTests($shouldCreateMockEventSink ? $this->mockEventSink : null);

        if (!is_null($tracerBuildCallback)) {
            $tracerBuildCallback($builder);
        }

        $this->tracer = $builder->build();
        GlobalTracerHolder::set($this->tracer);
    }

    public function tearDown(): void
    {
        GlobalTracerHolder::unset();
    }
}
