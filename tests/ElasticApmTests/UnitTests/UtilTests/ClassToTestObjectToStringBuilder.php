<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\UnitTests\UtilTests;

use Elastic\Apm\Impl\Util\ObjectToStringUsingPropertiesTrait;

class ClassToTestObjectToStringBuilder
{
    use ObjectToStringUsingPropertiesTrait;

    /** @var int */
    private $intField = 123;

    /** @var string */
    private $stringField = 'Abc';

    /** @var ?string */
    private $nullableStringField = null;
}
