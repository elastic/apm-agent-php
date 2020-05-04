<?php

declare(strict_types=1);

namespace Elastic\Apm;

/**
 * Any arbitrary contextual information regarding the event, captured by the agent, optionally provided by the user
 *
 * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/context.json
 * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/transactions/transaction.json#L40
 * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/errors/error.json#L44
 */
interface ContextDataInterface
{
    /**
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/request.json
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/context.json#L43
     */
    public function getRequest(): ?ContextRequestDataInterface;
}
