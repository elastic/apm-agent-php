<?php

/** @noinspection PhpUnusedPrivateFieldInspection, PhpPrivateFieldCanBeLocalVariableInspection */

declare(strict_types=1);

namespace ElasticApmTests\UnitTests\LogTests;

class DerivedObjectForLoggableTraitTests extends ObjectForLoggableTraitTests
{
    /** @var float */
    private $derivedFloatProp = 1.5;

    /** @var string */
    private $anotherExcludedProp = 'anotherExcludedProp value';

    /**
     * @return array<string>
     */
    protected static function propertiesExcludedFromLogImpl(): array
    {
        return array_merge(parent::propertiesExcludedFromLogImpl(), ['anotherExcludedProp']);
    }
}
