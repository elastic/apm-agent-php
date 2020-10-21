<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\UnitTests\UtilTests;

use Elastic\Apm\Impl\Util\ObjectToStringUsingPropertiesTrait;

class ClassToTestObjectToStringBuilderWithExcludedProperty
{
    use ObjectToStringUsingPropertiesTrait;

    /** @var int */
    private $intField = 123;

    /** @var string */
    private $stringField = 'Abc';

    /** @var ?string */
    private $nullableStringField = null;

    /** @var int */
    private $excludedProperty = 45;

    /** @var int */
    private $notExcludedProperty = 67;

    public function __toString(): string
    {
        return $this->toStringUsingProperties(['excludedProperty']);
    }
}
