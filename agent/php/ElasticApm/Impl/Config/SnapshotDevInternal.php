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

declare(strict_types=1);

namespace Elastic\Apm\Impl\Config;

use Elastic\Apm\Impl\Log\LogCategory;
use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableTrait;
use Elastic\Apm\Impl\Log\LoggerFactory;
use Elastic\Apm\Impl\Util\TextUtil;
use Elastic\Apm\Impl\Util\WildcardListMatcher;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class SnapshotDevInternal implements LoggableInterface
{
    use LoggableTrait;

    /** @var bool */
    private $dropEventAfterEnd = false;

    /** @var bool */
    private $dropEventsBeforeSendCCode = false;

    /** @var bool */
    private $gcCollectCyclesAfterEveryTransaction = false;

    /** @var bool */
    private $gcMemCachesAfterEveryTransaction = false;

    public function __construct(?WildcardListMatcher $devInternal, LoggerFactory $loggerFactory)
    {
        $logger = $loggerFactory->loggerForClass(LogCategory::CONFIGURATION, __NAMESPACE__, __CLASS__, __FILE__);

        foreach (get_object_vars($this) as $propName => $propValue) {
            $subOptName = TextUtil::camelToSnakeCase($propName);
            $matchedExpr = WildcardListMatcher::matchNullable($devInternal, $subOptName);
            if ($matchedExpr === null) {
                continue;
            }

            ($loggerProxy = $logger->ifInfoLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                OptionNames::DEV_INTERNAL . ' sub-option ' . $subOptName . ' is set',
                [OptionNames::DEV_INTERNAL . ' configuration' => $devInternal]
            );

            $this->$propName = true;
        }
    }

    public function dropEventAfterEnd(): bool
    {
        return $this->dropEventAfterEnd;
    }

    public function dropEventsBeforeSendCCode(): bool
    {
        return $this->dropEventsBeforeSendCCode;
    }

    public function gcCollectCyclesAfterEveryTransaction(): bool
    {
        return $this->gcCollectCyclesAfterEveryTransaction;
    }

    public function gcMemCachesAfterEveryTransaction(): bool
    {
        return $this->gcMemCachesAfterEveryTransaction;
    }
}
