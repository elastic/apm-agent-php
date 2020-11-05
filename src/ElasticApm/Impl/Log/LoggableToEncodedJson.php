<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Log;

use Elastic\Apm\Impl\Util\DbgUtil;
use Elastic\Apm\Impl\Util\JsonUtil;
use Elastic\Apm\Impl\Util\StaticClassTrait;
use Exception;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class LoggableToEncodedJson
{
    use StaticClassTrait;

    /**
     * @param mixed $value
     * @param bool  $prettyPrint
     * @param int   $lengthLimit
     *
     * @return string
     */
    public static function convert(
        $value,
        bool $prettyPrint = false,
        /** @noinspection PhpUnusedParameterInspection */ int $lengthLimit = LoggableToString::DEFAULT_LENGTH_LIMIT
    ): string {
        try {
            $jsonEncodable = LoggableToJsonEncodable::convert($value);
        } catch (Exception $ex) {
            return LoggingSubsystem::onInternalFailure(
                'LoggableToJsonEncodable::convert() failed',
                ['value type' => DbgUtil::getType($value)],
                $ex
            );
        }

        try {
            return JsonUtil::encode($jsonEncodable, $prettyPrint);
        } catch (Exception $ex) {
            return LoggingSubsystem::onInternalFailure(
                'JsonUtil::encode() failed',
                ['$jsonEncodable type' => DbgUtil::getType($jsonEncodable)],
                $ex
            );
        }
    }
}
