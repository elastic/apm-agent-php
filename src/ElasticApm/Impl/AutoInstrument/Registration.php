<?php

/** @noinspection PhpUnusedAliasInspection */

declare(strict_types=1);

namespace Elastic\Apm\Impl\AutoInstrument;

use Elastic\Apm\AutoInstrument\InterceptedCallTrackerInterface;
use Elastic\Apm\Impl\MetadataInterface;
use Elastic\Apm\Impl\Util\ObjectToStringBuilder;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class Registration
{
    /** @var int */
    private $dbgPluginIndex;

    /** @var string */
    private $dbgPluginDesc;

    /** @var string */
    private $dbgInterceptedCallDesc;

    /** @var callable */
    public $factory;

    /**
     * @param int      $dbgPluginIndex
     * @param string   $dbgPluginDesc
     * @param string   $dbgInterceptedCallDesc
     * @param callable $interceptedCallTrackerFactory
     *
     * @phpstan-param callable(): InterceptedCallTrackerInterface $interceptedCallTrackerFactory
     *
     */
    public function __construct(
        int $dbgPluginIndex,
        string $dbgPluginDesc,
        string $dbgInterceptedCallDesc,
        callable $interceptedCallTrackerFactory
    ) {
        $this->dbgPluginIndex = $dbgPluginIndex;
        $this->dbgPluginDesc = $dbgPluginDesc;
        $this->dbgInterceptedCallDesc = $dbgInterceptedCallDesc;
        $this->factory = $interceptedCallTrackerFactory;
    }

    public function __toString(): string
    {
        $builder = new ObjectToStringBuilder();

        $builder->add(
            'plugin',
            (new ObjectToStringBuilder())
                ->add('index', $this->dbgPluginIndex)
                ->add('description', $this->dbgPluginDesc)
                ->build()
        );

        $builder->add('interceptedCallDescription', $this->dbgInterceptedCallDesc);

        return $builder->build();
    }
}
