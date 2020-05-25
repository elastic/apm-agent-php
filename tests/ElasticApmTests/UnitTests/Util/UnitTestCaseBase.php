<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\UnitTests\Util;

use Closure;
use Elastic\Apm\Impl\GlobalTracerHolder;
use Elastic\Apm\Impl\TracerBuilder;
use Elastic\Apm\Impl\TracerInterface;
use Elastic\Apm\Tests\Util\NoopLogSink;
use Elastic\Apm\Tests\Util\TestCaseBase;

class UnitTestCaseBase extends TestCaseBase
{
    /** @var MockEventSink */
    protected $mockEventSink;

    /** @var TracerInterface */
    protected $tracer;

    public function setUp(): void
    {
        $this->setUpTestEnv();
    }

    protected function setUpTestEnv(?Closure $tracerBuildCallback = null, bool $shouldCreateMockEventSink = true): void
    {
        $builder = TracerBuilder::startNew();
        if ($shouldCreateMockEventSink) {
            $this->mockEventSink = new MockEventSink();
            $builder->withEventSink($this->mockEventSink)
                    ->withLogSink(new NoopLogSink());
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
