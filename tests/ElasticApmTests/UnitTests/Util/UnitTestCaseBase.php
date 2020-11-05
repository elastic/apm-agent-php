<?php

declare(strict_types=1);

namespace ElasticApmTests\UnitTests\Util;

use Closure;
use Elastic\Apm\Impl\GlobalTracerHolder;
use Elastic\Apm\Impl\Log\NoopLogSink;
use Elastic\Apm\Impl\TracerBuilder;
use Elastic\Apm\Impl\TracerInterface;
use Elastic\Apm\Impl\Util\ElasticApmExtensionUtil;
use ElasticApmTests\Util\TestCaseBase;
use RuntimeException;

class UnitTestCaseBase extends TestCaseBase
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
        $builder = TracerBuilder::startNew();

        // Set empty config source to prevent config from default sources (env vars and php.ini) from being used
        // since unit test cannot assume anything about the state of those config sources
        $builder->withConfigRawSnapshotSource(new EmptyConfigRawSnapshotSource());

        if ($shouldCreateMockEventSink) {
            $this->mockEventSink = new MockEventSink();
            $builder->withEventSink($this->mockEventSink)
                    ->withLogSink(NoopLogSink::singletonInstance());
        }
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
