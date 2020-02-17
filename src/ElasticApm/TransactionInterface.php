<?php

declare(strict_types=1);

namespace ElasticApm;

use ElasticApm\Report\TransactionDtoInterface;

interface TransactionInterface extends ExecutionSegmentInterface, TransactionDtoInterface
{
}
