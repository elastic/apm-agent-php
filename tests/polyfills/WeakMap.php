<?php

/*
 * Licensed to Elasticsearch B.V. under one or more contributor
 * license agreements. See the NOTICE file distributed with
 * this work for additional information regarding copyright
 * ownership. Elasticsearch B.V. licenses this file to you under
 * the Apache License, Version 2.0 (the "License"); you may
 * not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied.  See the License for the
 * specific language governing permissions and limitations
 * under the License.
 */

/** @noinspection PhpElementIsNotAvailableInCurrentPhpVersionInspection */
/** @noinspection PhpIllegalPsrClassPathInspection */

declare(strict_types=1);

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */

/**
 * Weak maps allow creating a map from objects to arbitrary values
 * (similar to SplObjectStorage) without preventing the objects that are used
 * as keys from being garbage collected. If an object key is garbage collected,
 * it will simply be removed from the map.
 *
 * @since 8.0
 */
final class WeakMap implements ArrayAccess, Countable, IteratorAggregate
{
    /**
     * @return never
     */
    private static function throwException(): void
    {
        throw new LogicException(
            'This class is just a dummy to pass static analysis'
            . ' on PHP versions that do not have WeakMap'
        );
    }

    /**
     * Returns {@see true} if the value for the object is contained in
     * the {@see WeakMap} and {@see false} instead.
     *
     * @param object $object Any object
     * @return bool
     */
    public function offsetExists($object): bool
    {
        self::throwException();
    }

    /**
     * Returns the existsing value by an object.
     *
     * @param object $object Any object
     * @return mixed Value associated with the key object
     */
    public function offsetGet($object)
    {
        self::throwException();
    }

    /**
     * Sets a new value for an object.
     *
     * @param object $object Any object
     * @param mixed $value Any value
     * @return void
     */
    public function offsetSet($object, $value)
    {
        self::throwException();
    }

    /**
     * Force removes an object value from the {@see WeakMap} instance.
     *
     * @param object $object Any object
     * @return void
     */
    public function offsetUnset($object)
    {
        self::throwException();
    }

    /**
     * Returns an iterator in the "[object => mixed]" format.
     *
     * @return Traversable
     */
    public function getIterator()
    {
        self::throwException();
    }

    /**
     * Returns the number of items in the {@see WeakMap} instance.
     *
     * @return int
     */
    public function count()
    {
        self::throwException();
    }
}
