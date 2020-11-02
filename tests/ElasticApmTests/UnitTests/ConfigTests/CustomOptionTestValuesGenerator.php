<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\UnitTests\ConfigTests;

use Elastic\Apm\Impl\Util\SingletonInstanceTrait;
use Elastic\Apm\Impl\Util\TextUtil;
use Elastic\Apm\Tests\Util\RangeUtilForTests;
use Elastic\Apm\Tests\Util\IterableUtilForTests;
use Elastic\Apm\Tests\Util\RandomUtilForTests;
use Elastic\Apm\Tests\Util\TextUtilForTests;

/**
 * @implements OptionTestValuesGeneratorInterface<null>
 */
final class CustomOptionTestValuesGenerator implements OptionTestValuesGeneratorInterface
{
    use SingletonInstanceTrait;

    /**
     * @return iterable<OptionTestValidValue<null>>
     */
    public function validValues(): iterable
    {
        return [];
    }

    public function invalidRawValues(): iterable
    {
        return [];
    }
}
