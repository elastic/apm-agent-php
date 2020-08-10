<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\Util;

use Elastic\Apm\ExecutionSegmentContextInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
abstract class ExecutionSegmentContextTestDto extends TestDtoBase implements ExecutionSegmentContextInterface
{
    /** @var array<string, string|bool|int|float|null> */
    private $labels = [];

    public function setLabel(string $key, $value): void
    {
        $this->labels[$key] = $value;
    }

    public function getLabels(): array
    {
        return $this->labels;
    }

    public function isNoop(): bool
    {
        return false;
    }
}
