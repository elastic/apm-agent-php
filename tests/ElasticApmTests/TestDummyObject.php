<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests;

final class TestDummyObject
{
    /** @var string */
    public $dummyPublicStringProperty;

    public function __construct(string $dummyPublicStringProperty)
    {
        $this->dummyPublicStringProperty = $dummyPublicStringProperty;
    }
}
