<?php

namespace Symfony\Config\Framework;

require_once __DIR__.\DIRECTORY_SEPARATOR.'RateLimiter'.\DIRECTORY_SEPARATOR.'LimiterConfig.php';

use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class RateLimiterConfig 
{
    private $enabled;
    private $limiters;
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

    public function limiter(string $name, array $value = []): \Symfony\Config\Framework\RateLimiter\LimiterConfig
    {
        if (!isset($this->limiters[$name])) {
            $this->_usedProperties['limiters'] = true;
            $this->limiters[$name] = new \Symfony\Config\Framework\RateLimiter\LimiterConfig($value);
        } elseif (1 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "limiter()" has already been initialized. You cannot pass values the second time you call limiter().');
        }

        return $this->limiters[$name];
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('enabled', $value)) {
            $this->_usedProperties['enabled'] = true;
            $this->enabled = $value['enabled'];
            unset($value['enabled']);
        }

        if (array_key_exists('limiters', $value)) {
            $this->_usedProperties['limiters'] = true;
            $this->limiters = array_map(function ($v) { return new \Symfony\Config\Framework\RateLimiter\LimiterConfig($v); }, $value['limiters']);
            unset($value['limiters']);
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
        if (isset($this->_usedProperties['limiters'])) {
            $output['limiters'] = array_map(function ($v) { return $v->toArray(); }, $this->limiters);
        }

        return $output;
    }

}
