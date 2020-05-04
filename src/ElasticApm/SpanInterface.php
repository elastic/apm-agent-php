<?php

declare(strict_types=1);

namespace Elastic\Apm;

interface SpanInterface extends ExecutionSegmentInterface, SpanDataInterface
{
    /**
     * @param string|null $subtype
     *
     * @see SpanDataInterface::getSubtype() For the description
     */
    public function setSubtype(?string $subtype): void;

    /**
     * @param string|null $action
     *
     * @see SpanDataInterface::getAction() For the description
     */
    public function setAction(?string $action): void;
}
