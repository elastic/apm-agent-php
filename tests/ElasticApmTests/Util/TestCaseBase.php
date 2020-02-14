<?php

declare(strict_types=1);

namespace ElasticApmTests\Util;

use Jchook\AssertThrows\AssertThrows;
use PHPUnit\Framework\TestCase;

class TestCaseBase extends TestCase
{
    // Adds the assertThrows method
    use AssertThrows;
}
