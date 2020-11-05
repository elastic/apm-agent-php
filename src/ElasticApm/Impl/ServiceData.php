<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableTrait;
use JsonSerializable;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
class ServiceData extends EventData implements ServiceDataInterface, JsonSerializable, LoggableInterface
{
    use LoggableTrait;

    /** @var string|null */
    protected $name = null;

    /** @var string|null */
    protected $version = null;

    /** @var string|null */
    protected $environment = null;

    /** @var NameVersionData|null */
    protected $agent = null;

    /** @var NameVersionData|null */
    protected $framework = null;

    /** @var NameVersionData|null */
    protected $language = null;

    /** @var NameVersionData|null */
    protected $runtime = null;

    /** @inheritDoc */
    public function name(): ?string
    {
        return $this->name;
    }

    /** @inheritDoc */
    public function version(): ?string
    {
        return $this->version;
    }

    /** @inheritDoc */
    public function environment(): ?string
    {
        return $this->environment;
    }

    /** @inheritDoc */
    public function agent(): ?NameVersionDataInterface
    {
        return $this->agent;
    }

    /** @inheritDoc */
    public function framework(): ?NameVersionDataInterface
    {
        return $this->framework;
    }

    /** @inheritDoc */
    public function language(): ?NameVersionDataInterface
    {
        return $this->language;
    }

    /** @inheritDoc */
    public function runtime(): ?NameVersionDataInterface
    {
        return $this->runtime;
    }

    /**
     * @param mixed $propValue
     *
     * @return mixed
     */
    protected static function convertPropertyValueToData($propValue)
    {
        if ($propValue instanceof NameVersionDataInterface) {
            return NameVersionData::convertToData($propValue);
        }

        return parent::convertPropertyValueToData($propValue);
    }
}
