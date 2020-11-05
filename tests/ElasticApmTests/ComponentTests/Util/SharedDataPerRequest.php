<?php

declare(strict_types=1);

namespace ElasticApmTests\ComponentTests\Util;

final class SharedDataPerRequest extends SharedData
{
    /** @var array<string, mixed>|null */
    public $appCodeArguments = null;

    /** @var string|null */
    public $appCodeClass = null;

    /** @var string|null */
    public $appCodeMethod = null;

    /** @var string|null */
    public $appTopLevelCodeId = null;

    /** @var string */
    public $serverId;

    public static function fromServerId(string $serverId, ?SharedDataPerRequest $prototype = null): self
    {
        $result = new SharedDataPerRequest();

        if (!is_null($prototype)) {
            // @phpstan-ignore-next-line - see https://github.com/phpstan/phpstan/issues/1060
            foreach ($prototype as $propName => $propValue) {
                $result->$propName = $propValue;
            }
        }

        $result->serverId = $serverId;

        return $result;
    }
}
