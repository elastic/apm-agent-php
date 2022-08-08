<?php

namespace Symfony\Config\Framework;

use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class HttpCacheConfig 
{
    private $enabled;
    private $debug;
    private $traceLevel;
    private $traceHeader;
    private $defaultTtl;
    private $privateHeaders;
    private $allowReload;
    private $allowRevalidate;
    private $staleWhileRevalidate;
    private $staleIfError;
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
     * @default '%kernel.debug%'
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function debug($value): static
    {
        $this->_usedProperties['debug'] = true;
        $this->debug = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|'none'|'short'|'full' $value
     * @return $this
     */
    public function traceLevel($value): static
    {
        $this->_usedProperties['traceLevel'] = true;
        $this->traceLevel = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function traceHeader($value): static
    {
        $this->_usedProperties['traceHeader'] = true;
        $this->traceHeader = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|int $value
     * @return $this
     */
    public function defaultTtl($value): static
    {
        $this->_usedProperties['defaultTtl'] = true;
        $this->defaultTtl = $value;

        return $this;
    }

    /**
     * @param ParamConfigurator|list<ParamConfigurator|mixed> $value
     *
     * @return $this
     */
    public function privateHeaders(ParamConfigurator|array $value): static
    {
        $this->_usedProperties['privateHeaders'] = true;
        $this->privateHeaders = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function allowReload($value): static
    {
        $this->_usedProperties['allowReload'] = true;
        $this->allowReload = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function allowRevalidate($value): static
    {
        $this->_usedProperties['allowRevalidate'] = true;
        $this->allowRevalidate = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|int $value
     * @return $this
     */
    public function staleWhileRevalidate($value): static
    {
        $this->_usedProperties['staleWhileRevalidate'] = true;
        $this->staleWhileRevalidate = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|int $value
     * @return $this
     */
    public function staleIfError($value): static
    {
        $this->_usedProperties['staleIfError'] = true;
        $this->staleIfError = $value;

        return $this;
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('enabled', $value)) {
            $this->_usedProperties['enabled'] = true;
            $this->enabled = $value['enabled'];
            unset($value['enabled']);
        }

        if (array_key_exists('debug', $value)) {
            $this->_usedProperties['debug'] = true;
            $this->debug = $value['debug'];
            unset($value['debug']);
        }

        if (array_key_exists('trace_level', $value)) {
            $this->_usedProperties['traceLevel'] = true;
            $this->traceLevel = $value['trace_level'];
            unset($value['trace_level']);
        }

        if (array_key_exists('trace_header', $value)) {
            $this->_usedProperties['traceHeader'] = true;
            $this->traceHeader = $value['trace_header'];
            unset($value['trace_header']);
        }

        if (array_key_exists('default_ttl', $value)) {
            $this->_usedProperties['defaultTtl'] = true;
            $this->defaultTtl = $value['default_ttl'];
            unset($value['default_ttl']);
        }

        if (array_key_exists('private_headers', $value)) {
            $this->_usedProperties['privateHeaders'] = true;
            $this->privateHeaders = $value['private_headers'];
            unset($value['private_headers']);
        }

        if (array_key_exists('allow_reload', $value)) {
            $this->_usedProperties['allowReload'] = true;
            $this->allowReload = $value['allow_reload'];
            unset($value['allow_reload']);
        }

        if (array_key_exists('allow_revalidate', $value)) {
            $this->_usedProperties['allowRevalidate'] = true;
            $this->allowRevalidate = $value['allow_revalidate'];
            unset($value['allow_revalidate']);
        }

        if (array_key_exists('stale_while_revalidate', $value)) {
            $this->_usedProperties['staleWhileRevalidate'] = true;
            $this->staleWhileRevalidate = $value['stale_while_revalidate'];
            unset($value['stale_while_revalidate']);
        }

        if (array_key_exists('stale_if_error', $value)) {
            $this->_usedProperties['staleIfError'] = true;
            $this->staleIfError = $value['stale_if_error'];
            unset($value['stale_if_error']);
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
        if (isset($this->_usedProperties['debug'])) {
            $output['debug'] = $this->debug;
        }
        if (isset($this->_usedProperties['traceLevel'])) {
            $output['trace_level'] = $this->traceLevel;
        }
        if (isset($this->_usedProperties['traceHeader'])) {
            $output['trace_header'] = $this->traceHeader;
        }
        if (isset($this->_usedProperties['defaultTtl'])) {
            $output['default_ttl'] = $this->defaultTtl;
        }
        if (isset($this->_usedProperties['privateHeaders'])) {
            $output['private_headers'] = $this->privateHeaders;
        }
        if (isset($this->_usedProperties['allowReload'])) {
            $output['allow_reload'] = $this->allowReload;
        }
        if (isset($this->_usedProperties['allowRevalidate'])) {
            $output['allow_revalidate'] = $this->allowRevalidate;
        }
        if (isset($this->_usedProperties['staleWhileRevalidate'])) {
            $output['stale_while_revalidate'] = $this->staleWhileRevalidate;
        }
        if (isset($this->_usedProperties['staleIfError'])) {
            $output['stale_if_error'] = $this->staleIfError;
        }

        return $output;
    }

}
