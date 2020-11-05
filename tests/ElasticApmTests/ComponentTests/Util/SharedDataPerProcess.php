<?php

declare(strict_types=1);

namespace ElasticApmTests\ComponentTests\Util;

final class SharedDataPerProcess extends SharedData
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
