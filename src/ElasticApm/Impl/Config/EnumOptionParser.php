<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Config;

use Elastic\Apm\Impl\Util\TextUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 *
 * @template   T
 *
 * @extends    OptionParser<T>
 */
class EnumOptionParser extends OptionParser
{
    /** @var string */
    private $dbgEnumDesc;

    /**
     * We are forced to use list-array of pairs instead of regular associative array
     * because in an associative array if the key is numeric string it's automatically converted to int
     * (see https://www.php.net/manual/en/language.types.array.php)
     *
     * @var array<array<string|mixed>>
     * @phpstan-var array<array{string, T}>
     */
    private $nameValuePairs;

    /** @var bool */
    private $isCaseSensitive;

    /** @var bool */
    private $isUnambiguousPrefixAllowed;

    /**
     * @param string                     $dbgEnumDesc
     * @param array<array<string|mixed>> $nameValuePairs
     * @param bool                       $isCaseSensitive
     * @param bool                       $isUnambiguousPrefixAllowed
     *
     * @phpstan-param array<array{string, T}> $nameValuePairs
     */
    public function __construct(
        string $dbgEnumDesc,
        array $nameValuePairs,
        bool $isCaseSensitive,
        bool $isUnambiguousPrefixAllowed
    ) {
        $this->dbgEnumDesc = $dbgEnumDesc;
        $this->nameValuePairs = $nameValuePairs;
        $this->isCaseSensitive = $isCaseSensitive;
        $this->isUnambiguousPrefixAllowed = $isUnambiguousPrefixAllowed;
    }

    /**
     * @return array<array<string|mixed>>
     * @phpstan-return array<array{string, T}>
     */
    public function nameValuePairs(): array
    {
        return $this->nameValuePairs;
    }

    public function isCaseSensitive(): bool
    {
        return $this->isCaseSensitive;
    }

    public function isUnambiguousPrefixAllowed(): bool
    {
        return $this->isUnambiguousPrefixAllowed;
    }

    /**
     * @param string $rawValue
     *
     * @return mixed
     *
     * @phpstan-return T
     */
    public function parse(string $rawValue)
    {
        foreach ($this->nameValuePairs as $enumEntryNameValuePair) {
            if (TextUtil::isPrefixOf($rawValue, $enumEntryNameValuePair[0], $this->isCaseSensitive)) {
                if (strlen($enumEntryNameValuePair[0]) === strlen($rawValue)) {
                    return $enumEntryNameValuePair[1];
                }

                if (!$this->isUnambiguousPrefixAllowed) {
                    continue;
                }

                if (isset($foundMatchingEntry)) {
                    throw new ParseException(
                        "Not a valid $this->dbgEnumDesc value - it matches more than one entry as a prefix."
                        . " Raw option value: `$rawValue'"
                    );
                }
                $foundMatchingEntry = $enumEntryNameValuePair[1];
            }
        }

        if (!isset($foundMatchingEntry)) {
            throw new ParseException("Not a valid $this->dbgEnumDesc value. Raw option value: `$rawValue'");
        }

        return $foundMatchingEntry;
    }
}
