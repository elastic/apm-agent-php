<?php

/** @noinspection PhpUnusedPrivateFieldInspection */

declare(strict_types=1);

namespace Elastic\Apm\Tests\UnitTests\UtilTests;

class DerivedClassToTestObjectToStringBuilder extends ClassToTestObjectToStringBuilder
{
    /** @var float */
    private $derivedFloatField = 1.5;
}
