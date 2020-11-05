<?php

declare(strict_types=1);

namespace ElasticApmTests\Util\Deserialization;

use Elastic\Apm\Impl\Util\JsonUtil;
use Elastic\Apm\Impl\Util\StaticClassTrait;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class JsonUtilForTests
{
    use StaticClassTrait;

    /**
     * @param string $inputJson
     *
     * @return string
     */
    public static function prettyFormat(string $inputJson): string
    {
        return JsonUtil::encode(JsonUtil::decode($inputJson, /* asAssocArray */ true), /* prettyPrint: */ true);
    }
}
