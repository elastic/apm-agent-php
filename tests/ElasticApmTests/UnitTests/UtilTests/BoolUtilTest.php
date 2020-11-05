<?php

declare(strict_types=1);

namespace ElasticApmTests\UnitTests\UtilTests;

use Elastic\Apm\Impl\Util\BoolUtil;
use PHPUnit\Framework\TestCase;

class BoolUtilTest extends TestCase
{
    public function testIfThen(): void
    {
        self::assertTrue(BoolUtil::ifThen(true, true));
        self::assertTrue(BoolUtil::ifThen(false, true));
        self::assertTrue(BoolUtil::ifThen(false, false));

        self::assertTrue(!BoolUtil::ifThen(true, false));
    }
}
