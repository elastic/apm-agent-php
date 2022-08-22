<?php

namespace Symfony\Config\Framework\HttpClient\DefaultOptions;

use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class PeerFingerprintConfig 
{
    private $sha1;
    private $pinsha256;
    private $md5;
    private $_usedProperties = [];

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     *
     * @return $this
     */
    public function sha1(mixed $value): static
    {
        $this->_usedProperties['sha1'] = true;
        $this->sha1 = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     *
     * @return $this
     */
    public function pinsha256(mixed $value): static
    {
        $this->_usedProperties['pinsha256'] = true;
        $this->pinsha256 = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     *
     * @return $this
     */
    public function md5(mixed $value): static
    {
        $this->_usedProperties['md5'] = true;
        $this->md5 = $value;

        return $this;
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('sha1', $value)) {
            $this->_usedProperties['sha1'] = true;
            $this->sha1 = $value['sha1'];
            unset($value['sha1']);
        }

        if (array_key_exists('pin-sha256', $value)) {
            $this->_usedProperties['pinsha256'] = true;
            $this->pinsha256 = $value['pin-sha256'];
            unset($value['pin-sha256']);
        }

        if (array_key_exists('md5', $value)) {
            $this->_usedProperties['md5'] = true;
            $this->md5 = $value['md5'];
            unset($value['md5']);
        }

        if ([] !== $value) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($value)));
        }
    }

    public function toArray(): array
    {
        $output = [];
        if (isset($this->_usedProperties['sha1'])) {
            $output['sha1'] = $this->sha1;
        }
        if (isset($this->_usedProperties['pinsha256'])) {
            $output['pin-sha256'] = $this->pinsha256;
        }
        if (isset($this->_usedProperties['md5'])) {
            $output['md5'] = $this->md5;
        }

        return $output;
    }

}
