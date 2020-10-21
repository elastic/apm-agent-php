<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests\Util;

final class SharedDataPerProcess extends SharedDataBase
{
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
