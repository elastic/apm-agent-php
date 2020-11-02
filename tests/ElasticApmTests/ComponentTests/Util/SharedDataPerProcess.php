<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests\Util;

use Elastic\Apm\Impl\Util\ObjectToStringUsingPropertiesTrait;

final class SharedDataPerProcess extends SharedDataBase
{
    use ObjectToStringUsingPropertiesTrait;

    /** @var int|null */
    public $resourcesCleanerPort = null;

    /** @var string|null */
    public $resourcesCleanerServerId = null;

    /** @var int */
    public $rootProcessId;

    /** @var string|null */
    public $thisServerId = null;

    /** @var int|null */
    public $thisServerPort = null;
}
