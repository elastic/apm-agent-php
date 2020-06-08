<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests\Util;

use React\EventLoop\TimerInterface;

final class MockApmServerPendingDataRequest
{
    /** @var int */
    public $fromIndex;

    /**
     * @var callable
     * @phpstan-var callable(\Psr\Http\Message\ResponseInterface): void
     */
    public $resolveCallback;

    /** @var TimerInterface */
    public $timer;

    /**
     * @param int      $fromIndex
     * @param callable $resolveCallback
     *
     * @phpstan-param callable(\Psr\Http\Message\ResponseInterface): void $resolveCallback
     */
    public function __construct(int $fromIndex, callable $resolveCallback, TimerInterface $timer)
    {
        $this->fromIndex = $fromIndex;
        $this->resolveCallback = $resolveCallback;
        $this->timer = $timer;
    }
}
