<?php

namespace Symfony\Config\Framework\RateLimiter\LimiterConfig;

use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class RateConfig 
{
    private $interval;
    private $amount;
    private $_usedProperties = [];

    /**
     * Configures the rate interval. The value must be a number followed by "second", "minute", "hour", "day", "week" or "month" (or their plural equivalent).
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function interval($value): static
    {
        $this->_usedProperties['interval'] = true;
        $this->interval = $value;

        return $this;
    }

    /**
     * Amount of tokens to add each interval
     * @default 1
     * @param ParamConfigurator|int $value
     * @return $this
     */
    public function amount($value): static
    {
        $this->_usedProperties['amount'] = true;
        $this->amount = $value;

        return $this;
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('interval', $value)) {
            $this->_usedProperties['interval'] = true;
            $this->interval = $value['interval'];
            unset($value['interval']);
        }

        if (array_key_exists('amount', $value)) {
            $this->_usedProperties['amount'] = true;
            $this->amount = $value['amount'];
            unset($value['amount']);
        }

        if ([] !== $value) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($value)));
        }
    }

    public function toArray(): array
    {
        $output = [];
        if (isset($this->_usedProperties['interval'])) {
            $output['interval'] = $this->interval;
        }
        if (isset($this->_usedProperties['amount'])) {
            $output['amount'] = $this->amount;
        }

        return $output;
    }

}
