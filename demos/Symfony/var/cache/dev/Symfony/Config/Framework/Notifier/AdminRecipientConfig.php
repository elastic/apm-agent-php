<?php

namespace Symfony\Config\Framework\Notifier;

use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class AdminRecipientConfig 
{
    private $email;
    private $phone;
    private $_usedProperties = [];

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function email($value): static
    {
        $this->_usedProperties['email'] = true;
        $this->email = $value;

        return $this;
    }

    /**
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function phone($value): static
    {
        $this->_usedProperties['phone'] = true;
        $this->phone = $value;

        return $this;
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('email', $value)) {
            $this->_usedProperties['email'] = true;
            $this->email = $value['email'];
            unset($value['email']);
        }

        if (array_key_exists('phone', $value)) {
            $this->_usedProperties['phone'] = true;
            $this->phone = $value['phone'];
            unset($value['phone']);
        }

        if ([] !== $value) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($value)));
        }
    }

    public function toArray(): array
    {
        $output = [];
        if (isset($this->_usedProperties['email'])) {
            $output['email'] = $this->email;
        }
        if (isset($this->_usedProperties['phone'])) {
            $output['phone'] = $this->phone;
        }

        return $output;
    }

}
