<?php

namespace Symfony\Config\Monolog\HandlerConfig;

use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class ExcludedHttpCodeConfig 
{
    private $code;
    private $urls;
    private $_usedProperties = [];

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function code($value): static
    {
        $this->_usedProperties['code'] = true;
        $this->code = $value;

        return $this;
    }

    /**
     * @param ParamConfigurator|list<ParamConfigurator|mixed> $value
     *
     * @return $this
     */
    public function urls(ParamConfigurator|array $value): static
    {
        $this->_usedProperties['urls'] = true;
        $this->urls = $value;

        return $this;
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('code', $value)) {
            $this->_usedProperties['code'] = true;
            $this->code = $value['code'];
            unset($value['code']);
        }

        if (array_key_exists('urls', $value)) {
            $this->_usedProperties['urls'] = true;
            $this->urls = $value['urls'];
            unset($value['urls']);
        }

        if ([] !== $value) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($value)));
        }
    }

    public function toArray(): array
    {
        $output = [];
        if (isset($this->_usedProperties['code'])) {
            $output['code'] = $this->code;
        }
        if (isset($this->_usedProperties['urls'])) {
            $output['urls'] = $this->urls;
        }

        return $output;
    }

}
