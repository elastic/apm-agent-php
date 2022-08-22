<?php

namespace Symfony\Config\Framework;

use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class RouterConfig 
{
    private $enabled;
    private $resource;
    private $type;
    private $defaultUri;
    private $httpPort;
    private $httpsPort;
    private $strictRequirements;
    private $utf8;
    private $_usedProperties = [];

    /**
     * @default false
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function enabled($value): static
    {
        $this->_usedProperties['enabled'] = true;
        $this->enabled = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function resource($value): static
    {
        $this->_usedProperties['resource'] = true;
        $this->resource = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function type($value): static
    {
        $this->_usedProperties['type'] = true;
        $this->type = $value;

        return $this;
    }

    /**
     * The default URI used to generate URLs in a non-HTTP context
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function defaultUri($value): static
    {
        $this->_usedProperties['defaultUri'] = true;
        $this->defaultUri = $value;

        return $this;
    }

    /**
     * @default 80
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function httpPort($value): static
    {
        $this->_usedProperties['httpPort'] = true;
        $this->httpPort = $value;

        return $this;
    }

    /**
     * @default 443
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function httpsPort($value): static
    {
        $this->_usedProperties['httpsPort'] = true;
        $this->httpsPort = $value;

        return $this;
    }

    /**
     * set to true to throw an exception when a parameter does not match the requirements
    set to false to disable exceptions when a parameter does not match the requirements (and return null instead)
    set to null to disable parameter checks against requirements
    'true' is the preferred configuration in development mode, while 'false' or 'null' might be preferred in production
     * @default true
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function strictRequirements($value): static
    {
        $this->_usedProperties['strictRequirements'] = true;
        $this->strictRequirements = $value;

        return $this;
    }

    /**
     * @default true
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function utf8($value): static
    {
        $this->_usedProperties['utf8'] = true;
        $this->utf8 = $value;

        return $this;
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('enabled', $value)) {
            $this->_usedProperties['enabled'] = true;
            $this->enabled = $value['enabled'];
            unset($value['enabled']);
        }

        if (array_key_exists('resource', $value)) {
            $this->_usedProperties['resource'] = true;
            $this->resource = $value['resource'];
            unset($value['resource']);
        }

        if (array_key_exists('type', $value)) {
            $this->_usedProperties['type'] = true;
            $this->type = $value['type'];
            unset($value['type']);
        }

        if (array_key_exists('default_uri', $value)) {
            $this->_usedProperties['defaultUri'] = true;
            $this->defaultUri = $value['default_uri'];
            unset($value['default_uri']);
        }

        if (array_key_exists('http_port', $value)) {
            $this->_usedProperties['httpPort'] = true;
            $this->httpPort = $value['http_port'];
            unset($value['http_port']);
        }

        if (array_key_exists('https_port', $value)) {
            $this->_usedProperties['httpsPort'] = true;
            $this->httpsPort = $value['https_port'];
            unset($value['https_port']);
        }

        if (array_key_exists('strict_requirements', $value)) {
            $this->_usedProperties['strictRequirements'] = true;
            $this->strictRequirements = $value['strict_requirements'];
            unset($value['strict_requirements']);
        }

        if (array_key_exists('utf8', $value)) {
            $this->_usedProperties['utf8'] = true;
            $this->utf8 = $value['utf8'];
            unset($value['utf8']);
        }

        if ([] !== $value) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($value)));
        }
    }

    public function toArray(): array
    {
        $output = [];
        if (isset($this->_usedProperties['enabled'])) {
            $output['enabled'] = $this->enabled;
        }
        if (isset($this->_usedProperties['resource'])) {
            $output['resource'] = $this->resource;
        }
        if (isset($this->_usedProperties['type'])) {
            $output['type'] = $this->type;
        }
        if (isset($this->_usedProperties['defaultUri'])) {
            $output['default_uri'] = $this->defaultUri;
        }
        if (isset($this->_usedProperties['httpPort'])) {
            $output['http_port'] = $this->httpPort;
        }
        if (isset($this->_usedProperties['httpsPort'])) {
            $output['https_port'] = $this->httpsPort;
        }
        if (isset($this->_usedProperties['strictRequirements'])) {
            $output['strict_requirements'] = $this->strictRequirements;
        }
        if (isset($this->_usedProperties['utf8'])) {
            $output['utf8'] = $this->utf8;
        }

        return $output;
    }

}
