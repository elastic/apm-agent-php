<?php

declare(strict_types=1);

namespace Elastic\Apm;

/**
 * Any arbitrary contextual information regarding the event, captured by the agent, optionally provided by the user
 *
 * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/spans/span.json#L43
 */
interface SpanContextInterface extends ExecutionSegmentContextInterface
{
}
