<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Util;

use Throwable;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
class ObjectToStringBuilder
{
    /** @var int */
    private const MAX_HORIZONTAL_LENGTH = 2000;

    /** @var string|null */
    private $type;

    /** @var array<string, mixed> */
    private $keyValuePairs;

    /** @var bool */
    private $shouldFormatOnlyHorizontally;

    /**
     * ObjectToStringBuilder constructor.
     *
     * @param string|null $type
     * @param mixed       $initialKeyValuePairs
     * @param bool        $shouldFormatOnlyHorizontally
     */
    public function __construct(
        ?string $type = null,
        $initialKeyValuePairs = [],
        bool $shouldFormatOnlyHorizontally = false
    ) {
        $this->type = $type;
        $this->shouldFormatOnlyHorizontally = $shouldFormatOnlyHorizontally;
        $this->keyValuePairs = $initialKeyValuePairs;
    }

    /**
     * @param string $key
     * @param mixed  $value
     */
    public function add(string $key, $value): self
    {
        $this->keyValuePairs[$key] = $value;
        return $this;
    }

    /**
     * @param mixed $keyValuePairs
     */
    public function addAll($keyValuePairs): self
    {
        foreach ($keyValuePairs as $key => $value) {
            $this->add($key, $value);
        }
        return $this;
    }


    /**
     * @param mixed $value
     *
     * @return string
     */
    private static function valueToString($value): string
    {
        if (is_array($value)) {
            return 'array<' . count($value) . '>';
        }

        try {
            return strval($value);
        } catch (Throwable $throwable) {
            if (is_object($value)) {
                return 'Value of ' . get_class($value);
            }
            return 'Value of ' . gettype($value);
        }
    }

    /**
     * @param array<string, string> $keyValueAsStringPairs
     *
     * @return string|null
     */
    private function tryToFormatHorizontally(array &$keyValueAsStringPairs): ?string
    {
        $result = '';
        if (!is_null($this->type)) {
            $result .= $this->type;
        }
        $result .= '{';
        $isFirst = true;
        foreach ($this->keyValuePairs as $key => $value) {
            // $valueAsString = DbgUtil::formatValue($value);
            $valueAsString = self::valueToString($value);
            $keyValueAsStringPairs[$key] = $valueAsString;
            if (!$this->shouldFormatOnlyHorizontally && TextUtil::containsNewLine($valueAsString)) {
                return null;
            }
            if ($isFirst) {
                $isFirst = false;
            } else {
                $result .= ', ';
            }
            $result .= $key;
            $result .= ': ';
            $result .= $valueAsString;
            if (!$this->shouldFormatOnlyHorizontally && strlen($result) > self::MAX_HORIZONTAL_LENGTH) {
                return null;
            }
        }
        $result .= '}';

        return $result;
    }

    /**
     * @param array<string, string> $keyValueAsStringPairs
     *
     * @return string
     */
    private function formatVertically(array $keyValueAsStringPairs): string
    {
        $result = '';
        if (!is_null($this->type)) {
            $result .= $this->type;
        }
        foreach ($this->keyValuePairs as $key => $value) {
            $result .= PHP_EOL;
            $valueAsString = $keyValueAsStringPairs[$key] ?? strval($value);
            $result .= TextUtil::indent($key);
            $result .= ': ';
            $result .= $valueAsString;
        }

        return $result;
    }

    public function build(): string
    {
        $keyValueAsStringPairs = [];
        $formattedHorizontally = $this->tryToFormatHorizontally(/* ref */ $keyValueAsStringPairs);
        if (!is_null($formattedHorizontally)) {
            return $formattedHorizontally;
        }

        return $this->formatVertically($keyValueAsStringPairs);
    }

    public function __toString(): string
    {
        return $this->build();
    }
}
