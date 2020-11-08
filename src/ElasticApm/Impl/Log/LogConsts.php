<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Log;

use Elastic\Apm\Impl\Util\StaticClassTrait;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class LogConsts
{
    use StaticClassTrait;

    public const TYPE_KEY = 'type';

    public const RESOURCE_TYPE_KEY = 'resource_type';
    public const RESOURCE_ID_KEY = 'resource_ID';
    public const RESOURCE_TYPE_VALUE = 'resource';

    public const VALUE_AS_STRING_KEY = 'value_as_string';
    public const VALUE_AS_DEBUG_INFO_KEY = 'value_as_string';

    public const SMALL_LIST_ARRAY_MAX_COUNT = 100;
    public const SMALL_MAP_ARRAY_MAX_COUNT = 100;

    public const LIST_ARRAY_TYPE_VALUE = 'list-array';
    public const MAP_ARRAY_TYPE_VALUE = 'map-array';
    public const ARRAY_COUNT_KEY = 'count';

    public const OBJECT_ID_KEY = 'object_ID';
    public const OBJECT_HASH_KEY = 'object_hash';
}
