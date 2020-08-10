<?php

declare(strict_types=1);

namespace Elastic\Apm;

use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LogStreamInterface;
use Elastic\Apm\Impl\Util\SerializationUtil;
use JsonSerializable;

/**
 * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/process.json
 */
class ProcessData implements JsonSerializable, LoggableInterface
{
    /** @var int */
    private $pid;

    public function __construct()
    {
        $this->setPid(getmypid());
    }

    /**
     * Process ID of the service
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/process.json#L6
     *
     * @param int $pid
     */
    public function setPid(int $pid): void
    {
        $this->pid = $pid;
    }

    /**
     * @see setPid() For the description
     */
    public function getPid(): int
    {
        return $this->pid;
    }

    /**
     * @return array<string, mixed>
     *
     * Called by json_encode
     * @noinspection PhpUnused
     */
    public function jsonSerialize(): array
    {
        return SerializationUtil::buildJsonSerializeResult(['pid' => $this->pid]);
    }

    public function toLog(LogStreamInterface $logStream): void
    {
        $logStream->writeMap(['pid' => $this->pid]);
    }
}
