<?php

/** @noinspection PhpUndefinedClassInspection */

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Elastic\Apm\ExecutionSegmentDataInterface;
use Elastic\Apm\Impl\Util\ObjectToStringBuilder;
use Elastic\Apm\Impl\Util\TextUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
abstract class ExecutionSegmentData extends EventData implements ExecutionSegmentDataInterface
{
    /** @var float */
    protected $duration;

    /** @var string */
    protected $id;

    /** @var array<string, string|bool|int|float|null> */
    protected $labels = [];

    /** @var string */
    protected $name;

    /** @var float UTC based and in microseconds since Unix epoch */
    protected $timestamp;

    /** @var string */
    protected $traceId;

    /** @var string */
    protected $type;

    public function getId(): string
    {
        return $this->id;
    }

    public function getTimestamp(): float
    {
        return $this->timestamp;
    }

    public function getDuration(): float
    {
        return $this->duration;
    }

    public function getTraceId(): string
    {
        return $this->traceId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getLabels(): array
    {
        return $this->labels;
    }

    /**
     * @param mixed $value
     *
     * @return bool
     */
    public static function doesValueHaveSupportedLabelType($value): bool
    {
        return is_null($value) || is_string($value) || is_bool($value) || is_int($value) || is_float($value);
    }

    /**
     * @param string               $propKey
     * @param mixed                $propValue
     * @param array<string, mixed> $result
     */
    protected function serializeProperty(string $propKey, $propValue, array &$result): void
    {
        if ($propKey === 'timestamp' && PHP_INT_SIZE >= 8) {
            parent::serializeProperty($propKey, intval($propValue), /* ref */ $result);
            return;
        }

        if ($propKey === 'labels') {
            $this->serializeContext(/* ref */ $result);
            return;
        }

        parent::serializeProperty($propKey, $propValue, /* ref */ $result);
    }

    /**
     * @param array<string, mixed> $result
     */
    protected function serializeContext(array &$result): void
    {
        if (! $this->shouldSerializeContext()) {
            return;
        }

        parent::serializeProperty('context', $this->buildSerializedContextValue(), /* ref */ $result);
    }

    protected function shouldSerializeContext(): bool
    {
        return !empty($this->getLabels());
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildSerializedContextValue(): array
    {
        return empty($this->getLabels()) ? [] : ['tags' => $this->getLabels()];
    }

    protected static function getterMethodNameForConvertToData(string $propKey): string
    {
        return 'get' . TextUtil::camelToPascalCase($propKey);
    }

    public static function dataToString(ExecutionSegmentDataInterface $data, string $type): string
    {
        $builder = new ObjectToStringBuilder($type);
        $builder->add('ID', $data->getId());
        $builder->add('name', $data->getName());
        return $builder->build();
    }
}
