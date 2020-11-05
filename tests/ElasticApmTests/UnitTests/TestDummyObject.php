<?php

declare(strict_types=1);

namespace ElasticApmTests\UnitTests;

final class TestDummyObject
{
    /** @var string */
    public $dummyPublicStringProperty;

    public function __construct(string $dummyPublicStringProperty)
    {
        $this->dummyPublicStringProperty = $dummyPublicStringProperty;
    }
}
