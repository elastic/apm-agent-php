<?php

declare(strict_types=1);

namespace ElasticApmTests\UnitTests\ConfigTests;

use Elastic\Apm\Impl\Util\SingletonInstanceTrait;
use Elastic\Apm\Impl\Util\TextUtil;
use ElasticApmTests\Util\RangeUtilForTests;
use ElasticApmTests\Util\IterableUtilForTests;
use ElasticApmTests\Util\RandomUtilForTests;
use ElasticApmTests\Util\TextUtilForTests;

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
