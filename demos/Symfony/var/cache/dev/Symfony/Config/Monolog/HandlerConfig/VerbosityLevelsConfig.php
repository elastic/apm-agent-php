<?php

namespace Symfony\Config\Monolog\HandlerConfig;

use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class VerbosityLevelsConfig 
{
    private $vERBOSITYQUIET;
    private $vERBOSITYNORMAL;
    private $vERBOSITYVERBOSE;
    private $vERBOSITYVERYVERBOSE;
    private $vERBOSITYDEBUG;
    private $_usedProperties = [];

    /**
     * @default 'ERROR'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function vERBOSITYQUIET($value): static
    {
        $this->_usedProperties['vERBOSITYQUIET'] = true;
        $this->vERBOSITYQUIET = $value;

        return $this;
    }

    /**
     * @default 'WARNING'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function vERBOSITYNORMAL($value): static
    {
        $this->_usedProperties['vERBOSITYNORMAL'] = true;
        $this->vERBOSITYNORMAL = $value;

        return $this;
    }

    /**
     * @default 'NOTICE'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function vERBOSITYVERBOSE($value): static
    {
        $this->_usedProperties['vERBOSITYVERBOSE'] = true;
        $this->vERBOSITYVERBOSE = $value;

        return $this;
    }

    /**
     * @default 'INFO'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function vERBOSITYVERYVERBOSE($value): static
    {
        $this->_usedProperties['vERBOSITYVERYVERBOSE'] = true;
        $this->vERBOSITYVERYVERBOSE = $value;

        return $this;
    }

    /**
     * @default 'DEBUG'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function vERBOSITYDEBUG($value): static
    {
        $this->_usedProperties['vERBOSITYDEBUG'] = true;
        $this->vERBOSITYDEBUG = $value;

        return $this;
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('VERBOSITY_QUIET', $value)) {
            $this->_usedProperties['vERBOSITYQUIET'] = true;
            $this->vERBOSITYQUIET = $value['VERBOSITY_QUIET'];
            unset($value['VERBOSITY_QUIET']);
        }

        if (array_key_exists('VERBOSITY_NORMAL', $value)) {
            $this->_usedProperties['vERBOSITYNORMAL'] = true;
            $this->vERBOSITYNORMAL = $value['VERBOSITY_NORMAL'];
            unset($value['VERBOSITY_NORMAL']);
        }

        if (array_key_exists('VERBOSITY_VERBOSE', $value)) {
            $this->_usedProperties['vERBOSITYVERBOSE'] = true;
            $this->vERBOSITYVERBOSE = $value['VERBOSITY_VERBOSE'];
            unset($value['VERBOSITY_VERBOSE']);
        }

        if (array_key_exists('VERBOSITY_VERY_VERBOSE', $value)) {
            $this->_usedProperties['vERBOSITYVERYVERBOSE'] = true;
            $this->vERBOSITYVERYVERBOSE = $value['VERBOSITY_VERY_VERBOSE'];
            unset($value['VERBOSITY_VERY_VERBOSE']);
        }

        if (array_key_exists('VERBOSITY_DEBUG', $value)) {
            $this->_usedProperties['vERBOSITYDEBUG'] = true;
            $this->vERBOSITYDEBUG = $value['VERBOSITY_DEBUG'];
            unset($value['VERBOSITY_DEBUG']);
        }

        if ([] !== $value) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($value)));
        }
    }

    public function toArray(): array
    {
        $output = [];
        if (isset($this->_usedProperties['vERBOSITYQUIET'])) {
            $output['VERBOSITY_QUIET'] = $this->vERBOSITYQUIET;
        }
        if (isset($this->_usedProperties['vERBOSITYNORMAL'])) {
            $output['VERBOSITY_NORMAL'] = $this->vERBOSITYNORMAL;
        }
        if (isset($this->_usedProperties['vERBOSITYVERBOSE'])) {
            $output['VERBOSITY_VERBOSE'] = $this->vERBOSITYVERBOSE;
        }
        if (isset($this->_usedProperties['vERBOSITYVERYVERBOSE'])) {
            $output['VERBOSITY_VERY_VERBOSE'] = $this->vERBOSITYVERYVERBOSE;
        }
        if (isset($this->_usedProperties['vERBOSITYDEBUG'])) {
            $output['VERBOSITY_DEBUG'] = $this->vERBOSITYDEBUG;
        }

        return $output;
    }

}
