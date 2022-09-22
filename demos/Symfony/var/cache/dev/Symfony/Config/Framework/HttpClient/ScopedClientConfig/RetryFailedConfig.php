<?php

namespace Symfony\Config\Framework\HttpClient\ScopedClientConfig;

require_once __DIR__.\DIRECTORY_SEPARATOR.'RetryFailed'.\DIRECTORY_SEPARATOR.'HttpCodeConfig.php';

use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class RetryFailedConfig 
{
    private $enabled;
    private $retryStrategy;
    private $httpCodes;
    private $maxRetries;
    private $delay;
    private $multiplier;
    private $maxDelay;
    private $jitter;
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
     * service id to override the retry strategy
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function retryStrategy($value): static
    {
        $this->_usedProperties['retryStrategy'] = true;
        $this->retryStrategy = $value;

        return $this;
    }

    /**
     * A list of HTTP status code that triggers a retry
     * @return \Symfony\Config\Framework\HttpClient\ScopedClientConfig\RetryFailed\HttpCodeConfig|$this
     */
    public function httpCode(string $code, mixed $value = []): \Symfony\Config\Framework\HttpClient\ScopedClientConfig\RetryFailed\HttpCodeConfig|static
    {
        if (!\is_array($value)) {
            $this->_usedProperties['httpCodes'] = true;
            $this->httpCodes[$code] = $value;

            return $this;
        }

        if (!isset($this->httpCodes[$code]) || !$this->httpCodes[$code] instanceof \Symfony\Config\Framework\HttpClient\ScopedClientConfig\RetryFailed\HttpCodeConfig) {
            $this->_usedProperties['httpCodes'] = true;
            $this->httpCodes[$code] = new \Symfony\Config\Framework\HttpClient\ScopedClientConfig\RetryFailed\HttpCodeConfig($value);
        } elseif (1 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "httpCode()" has already been initialized. You cannot pass values the second time you call httpCode().');
        }

        return $this->httpCodes[$code];
    }

    /**
     * @default 3
     * @param ParamConfigurator|int $value
     * @return $this
     */
    public function maxRetries($value): static
    {
        $this->_usedProperties['maxRetries'] = true;
        $this->maxRetries = $value;

        return $this;
    }

    /**
     * Time in ms to delay (or the initial value when multiplier is used)
     * @default 1000
     * @param ParamConfigurator|int $value
     * @return $this
     */
    public function delay($value): static
    {
        $this->_usedProperties['delay'] = true;
        $this->delay = $value;

        return $this;
    }

    /**
     * If greater than 1, delay will grow exponentially for each retry: delay * (multiple ^ retries)
     * @default 2
     * @param ParamConfigurator|float $value
     * @return $this
     */
    public function multiplier($value): static
    {
        $this->_usedProperties['multiplier'] = true;
        $this->multiplier = $value;

        return $this;
    }

    /**
     * Max time in ms that a retry should ever be delayed (0 = infinite)
     * @default 0
     * @param ParamConfigurator|int $value
     * @return $this
     */
    public function maxDelay($value): static
    {
        $this->_usedProperties['maxDelay'] = true;
        $this->maxDelay = $value;

        return $this;
    }

    /**
     * Randomness in percent (between 0 and 1) to apply to the delay
     * @default 0.1
     * @param ParamConfigurator|float $value
     * @return $this
     */
    public function jitter($value): static
    {
        $this->_usedProperties['jitter'] = true;
        $this->jitter = $value;

        return $this;
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('enabled', $value)) {
            $this->_usedProperties['enabled'] = true;
            $this->enabled = $value['enabled'];
            unset($value['enabled']);
        }

        if (array_key_exists('retry_strategy', $value)) {
            $this->_usedProperties['retryStrategy'] = true;
            $this->retryStrategy = $value['retry_strategy'];
            unset($value['retry_strategy']);
        }

        if (array_key_exists('http_codes', $value)) {
            $this->_usedProperties['httpCodes'] = true;
            $this->httpCodes = array_map(function ($v) { return \is_array($v) ? new \Symfony\Config\Framework\HttpClient\ScopedClientConfig\RetryFailed\HttpCodeConfig($v) : $v; }, $value['http_codes']);
            unset($value['http_codes']);
        }

        if (array_key_exists('max_retries', $value)) {
            $this->_usedProperties['maxRetries'] = true;
            $this->maxRetries = $value['max_retries'];
            unset($value['max_retries']);
        }

        if (array_key_exists('delay', $value)) {
            $this->_usedProperties['delay'] = true;
            $this->delay = $value['delay'];
            unset($value['delay']);
        }

        if (array_key_exists('multiplier', $value)) {
            $this->_usedProperties['multiplier'] = true;
            $this->multiplier = $value['multiplier'];
            unset($value['multiplier']);
        }

        if (array_key_exists('max_delay', $value)) {
            $this->_usedProperties['maxDelay'] = true;
            $this->maxDelay = $value['max_delay'];
            unset($value['max_delay']);
        }

        if (array_key_exists('jitter', $value)) {
            $this->_usedProperties['jitter'] = true;
            $this->jitter = $value['jitter'];
            unset($value['jitter']);
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
        if (isset($this->_usedProperties['retryStrategy'])) {
            $output['retry_strategy'] = $this->retryStrategy;
        }
        if (isset($this->_usedProperties['httpCodes'])) {
            $output['http_codes'] = array_map(function ($v) { return $v instanceof \Symfony\Config\Framework\HttpClient\ScopedClientConfig\RetryFailed\HttpCodeConfig ? $v->toArray() : $v; }, $this->httpCodes);
        }
        if (isset($this->_usedProperties['maxRetries'])) {
            $output['max_retries'] = $this->maxRetries;
        }
        if (isset($this->_usedProperties['delay'])) {
            $output['delay'] = $this->delay;
        }
        if (isset($this->_usedProperties['multiplier'])) {
            $output['multiplier'] = $this->multiplier;
        }
        if (isset($this->_usedProperties['maxDelay'])) {
            $output['max_delay'] = $this->maxDelay;
        }
        if (isset($this->_usedProperties['jitter'])) {
            $output['jitter'] = $this->jitter;
        }

        return $output;
    }

}
