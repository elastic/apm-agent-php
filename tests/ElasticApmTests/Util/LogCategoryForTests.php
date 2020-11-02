<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\Util;

use Elastic\Apm\Impl\Util\StaticClassTrait;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class LogCategoryForTests
{
    use StaticClassTrait;

    public const MOCK = 'mock';

    public const TEST_UTIL = 'test-util';

    public const TEST = 'test';
}
