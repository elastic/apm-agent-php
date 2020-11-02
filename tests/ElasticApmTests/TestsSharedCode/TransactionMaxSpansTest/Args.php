<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\TestsSharedCode\TransactionMaxSpansTest;

use Elastic\Apm\Impl\Util\ObjectToStringUsingPropertiesTrait;
use Elastic\Apm\Tests\Util\Deserialization\DeserializableDataObjectTrait;

final class Args
{
    use ObjectToStringUsingPropertiesTrait;
    use DeserializableDataObjectTrait;

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
