<?php

declare(strict_types=1);

/**
 * Get the first key of the given array without affecting the internal array pointer.
 *
 * @link  https://secure.php.net/array_key_first
 *
 * @param array<mixed> $arr
 *
 * @return string|int|null Returns the first key of array if the array is not empty; NULL otherwise.
 *
 * @phpstan-ignore-next-line
 */
function array_key_first(array $arr)
{
    foreach ($arr as $key => $unused) {
        return $key;
    }
    return null;
}
