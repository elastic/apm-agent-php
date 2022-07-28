<?php

namespace Symfony\Config\Framework\Mailer;

use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class EnvelopeConfig 
{
    private $sender;
    private $recipients;
    private $_usedProperties = [];

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function sender($value): static
    {
        $this->_usedProperties['sender'] = true;
        $this->sender = $value;

        return $this;
    }

    /**
     * @param mixed $value
     *
     * @return $this
     */
    public function recipients(mixed $value): static
    {
        $this->_usedProperties['recipients'] = true;
        $this->recipients = $value;

        return $this;
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('sender', $value)) {
            $this->_usedProperties['sender'] = true;
            $this->sender = $value['sender'];
            unset($value['sender']);
        }

        if (array_key_exists('recipients', $value)) {
            $this->_usedProperties['recipients'] = true;
            $this->recipients = $value['recipients'];
            unset($value['recipients']);
        }

        if ([] !== $value) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($value)));
        }
    }

    public function toArray(): array
    {
        $output = [];
        if (isset($this->_usedProperties['sender'])) {
            $output['sender'] = $this->sender;
        }
        if (isset($this->_usedProperties['recipients'])) {
            $output['recipients'] = $this->recipients;
        }

        return $output;
    }

}
