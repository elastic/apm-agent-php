<?php

declare(strict_types=1);

namespace ElasticApmTests\ComponentTests\Util;

use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableTrait;
use Elastic\Apm\Impl\Util\ClassNameUtil;
use JsonSerializable;
use RuntimeException;

final class IntakeApiRequest implements JsonSerializable, LoggableInterface
{
    use LoggableTrait;

    /** @var array<string, array<string>> */
    public $headers;

    /** @var string */
    public $body;

    /** @var float */
    public $timeReceivedAtServer;

    /**
     * @return array<string, mixed>
     *
     * Called by json_encode
     * @noinspection PhpUnused
     */
    public function jsonSerialize(): array
    {
        $result = [];

        // @phpstan-ignore-next-line - see https://github.com/phpstan/phpstan/issues/1060
        foreach ($this as $thisObjPropName => $thisObjPropValue) {
            $result[$thisObjPropName] = $thisObjPropValue;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $decodedJson
     */
    public static function jsonDeserialize(array $decodedJson): self
    {
        $thisObj = new self();

        // @phpstan-ignore-next-line - see https://github.com/phpstan/phpstan/issues/1060
        foreach ($decodedJson as $propName => $propValue) {
            if (!property_exists($thisObj, $propName)) {
                throw new RuntimeException(
                    'Unexpected key `' . $propName . '\' - there is no corresponding property in '
                    . ClassNameUtil::fqToShort(get_class($thisObj)) . ' class'
                );
            }
            $thisObj->$propName = $propValue;
        }

        return $thisObj;
    }
}
