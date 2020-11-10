<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Elastic\Apm\ExecutionSegmentContextInterface;
use Elastic\Apm\Impl\Log\LogCategory;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Util\DbgUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
abstract class ExecutionSegmentContext extends ContextDataWrapper implements ExecutionSegmentContextInterface
{
    /** @var ExecutionSegmentContextData */
    private $data;

    /** @var Logger */
    private $logger;

    protected function __construct(ExecutionSegment $owner, ExecutionSegmentContextData $data)
    {
        parent::__construct($owner);
        $this->data = $data;
        $this->logger = $this->getTracer()->loggerFactory()
                             ->loggerForClass(LogCategory::PUBLIC_API, __NAMESPACE__, __CLASS__, __FILE__)
                             ->addContext('this', $this);
    }

    /** @inheritDoc */
    public function setLabel(string $key, $value): void
    {
        if ($this->beforeMutating()) {
            return;
        }

        if (!self::doesValueHaveSupportedLabelType($value)) {
            ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Value for label is of unsupported type - it will be discarded',
                ['value type' => DbgUtil::getType($value), 'key' => $key, 'value' => $value]
            );
            return;
        }

        $this->data->labels[Tracer::limitKeywordString($key)] = is_string($value)
            ? Tracer::limitKeywordString($value)
            : $value;
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
     * @return string[]
     */
    protected static function propertiesExcludedFromLog(): array
    {
        return array_merge(parent::propertiesExcludedFromLog(), ['logger']);
    }
}
