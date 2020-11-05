<?php

declare(strict_types=1);

namespace ElasticApmTests\UnitTests\LogTests;

use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableTrait;

class ObjectWithResourceForTests implements LoggableInterface
{
    use LoggableTrait;

    /** @var resource */
    private $resourceField;
}
