<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\AutoInstrument;

use Elastic\Apm\AutoInstrument\OnInterceptedCallBeginInterface;
use Elastic\Apm\AutoInstrument\OnInterceptedCallEndInterface;
use Elastic\Apm\SpanInterface;
use Throwable;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
abstract class InterceptedCallToSpanBase
{
    /** @var SpanInterface */
    protected $span;

    /**
     * @param callable $interceptedCallToSpanBaseFactory
     *
     * @phpstan-param callable(): InterceptedCallToSpanBase $interceptedCallToSpanBaseFactory
     *
     * @return OnInterceptedCallBeginInterface
     */
    public static function wrap(callable $interceptedCallToSpanBaseFactory): OnInterceptedCallBeginInterface
    {
        return new class ($interceptedCallToSpanBaseFactory) implements OnInterceptedCallBeginInterface {
            /** @var callable */
            private $interceptedCallToSpanBaseFactory;

            /**
             * @param callable $interceptedCallToSpanBaseFactory
             *
             * @phpstan-param callable(): InterceptedCallToSpanBase $interceptedCallToSpanBaseFactory
             */
            public function __construct(callable $interceptedCallToSpanBaseFactory)
            {
                $this->interceptedCallToSpanBaseFactory = $interceptedCallToSpanBaseFactory;
            }

            /** @inheritDoc */
            public function onInterceptedCallBegin(...$interceptedCallArgs): ?OnInterceptedCallEndInterface
            {
                /** @var InterceptedCallToSpanBase */
                $interceptedCallToSpanBase = ($this->interceptedCallToSpanBaseFactory)();
                $interceptedCallToSpanBase->beginSpan(...$interceptedCallArgs);

                $onInterceptedCallEnd
                    = new class ($interceptedCallToSpanBase) implements OnInterceptedCallEndInterface {

                        /** @var InterceptedCallToSpanBase */
                        public $interceptedCallToSpanBase;

                        public function __construct(InterceptedCallToSpanBase $interceptedCallToSpanBase)
                        {
                            $this->interceptedCallToSpanBase = $interceptedCallToSpanBase;
                        }

                        public function onInterceptedCallEnd(bool $hasExitedByException, $returnValueOrThrown): void
                        {
                            $this->interceptedCallToSpanBase->endSpanIfSet($hasExitedByException, $returnValueOrThrown);
                        }
                    };

                return $onInterceptedCallEnd;
            }
        };
    }

    /**
     * @param mixed ...$interceptedCallArgs Intercepted call arguments
     */
    abstract public function beginSpan(...$interceptedCallArgs): void;

    /**
     * @param bool            $hasExitedByException
     * @param mixed|Throwable $returnValueOrThrown Return value of the intercepted call or thrown object
     */
    public function endSpanIfSet(bool $hasExitedByException, $returnValueOrThrown): void
    {
        if (isset($this->span)) {
            $this->endSpan($hasExitedByException, $returnValueOrThrown);
        }
    }

    /**
     * @param bool            $hasExitedByException
     * @param mixed|Throwable $returnValueOrThrown Return value of the intercepted call or thrown object
     *
     * @noinspection PhpUnusedParameterInspection
     */
    public function endSpan(bool $hasExitedByException, $returnValueOrThrown): void
    {
        $this->span->end();
    }
}
