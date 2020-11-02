<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\TestsSharedCode\TransactionMaxSpansTest;

use Elastic\Apm\Impl\Util\ObjectToStringUsingPropertiesTrait;

final class Args
{
    use ObjectToStringUsingPropertiesTrait;

    /** @var int */
    public $variantIndex = -1;

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
