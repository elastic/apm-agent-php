<?php

declare(strict_types=1);

namespace ElasticApmTests\TestsSharedCode\TransactionMaxSpansTest;

use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableTrait;
use ElasticApmTests\Util\Deserialization\DeserializableDataObjectTrait;

final class Args implements LoggableInterface
{
    use DeserializableDataObjectTrait;
    use LoggableTrait;

    /** @var int */
    public $variantIndex;

    /** @var bool */
    public $isSampled;

    /** @var ?int */
    public $configTransactionMaxSpans;

    /** @var int */
    public $numberOfSpansToCreate;

    /** @var int */
    public $maxFanOut;

    /** @var int */
    public $maxDepth;

    /** @var bool */
    public $shouldUseOnlyCurrentCreateSpanApis;
}
