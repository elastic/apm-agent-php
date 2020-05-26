<?php

/** @noinspection PhpUnusedAliasInspection */

declare(strict_types=1);

namespace Elastic\Apm\Impl\AutoInstrument;

use Elastic\Apm\AutoInstrument\InterceptedFunctionCallTrackerInterface;
use Elastic\Apm\AutoInstrument\InterceptedMethodCallTrackerInterface;
use Elastic\Apm\Impl\MetadataInterface;
use Elastic\Apm\Impl\Util\ObjectToStringBuilder;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class InterceptedCallRegistration
{
    /**
     * @var bool - true if the intercepted call is a method (i.e., has `this' as an implicit argument),
     *              false if the intercepted call is a function
     */
    public $isMethod;

    /** @var callable */
    public $factory;

    /** @var string|null */
    public $className;

    /** @var string|null */
    public $methodName;

    /** @var string|null */
    public $functionName;

    /**
     * @param string   $className
     * @param string   $methodName
     * @param callable $interceptedMethodCallTrackerFactory
     *
     * @phpstan-param callable(): InterceptedMethodCallTrackerInterface $interceptedMethodCallTrackerFactory
     *
     * @return InterceptedCallRegistration
     */
    public static function forMethod(
        string $className,
        string $methodName,
        callable $interceptedMethodCallTrackerFactory
    ): InterceptedCallRegistration {
        $result = new self();
        $result->isMethod = true;
        $result->factory = $interceptedMethodCallTrackerFactory;
        $result->className = $className;
        $result->methodName = $methodName;

        return $result;
    }

    /**
     * @param string   $functionName
     * @param callable $interceptedFunctionCallTrackerFactory
     *
     * @phpstan-param callable(): InterceptedFunctionCallTrackerInterface $interceptedFunctionCallTrackerFactory
     *
     * @return InterceptedCallRegistration
     */
    public static function forFunction(
        string $functionName,
        callable $interceptedFunctionCallTrackerFactory
    ): InterceptedCallRegistration {
        $result = new self();
        $result->isMethod = false;
        $result->factory = $interceptedFunctionCallTrackerFactory;
        $result->functionName = $functionName;

        return $result;
    }

    public function __toString(): string
    {
        $builder = new ObjectToStringBuilder();

        if ($this->isMethod) {
            $builder->add('className', $this->className);
            $builder->add('methodName', $this->methodName);
        } else {
            $builder->add('functionName', $this->functionName);
        }

        return $builder->build();
    }
}
