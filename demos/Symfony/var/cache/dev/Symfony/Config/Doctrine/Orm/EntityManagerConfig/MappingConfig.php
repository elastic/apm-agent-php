<?php

namespace Symfony\Config\Doctrine\Orm\EntityManagerConfig;

use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class MappingConfig 
{
    private $mapping;
    private $type;
    private $dir;
    private $alias;
    private $prefix;
    private $isBundle;
    private $_usedProperties = [];

    /**
     * @default true
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function mapping($value): static
    {
        $this->_usedProperties['mapping'] = true;
        $this->mapping = $value;

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
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function dir($value): static
    {
        $this->_usedProperties['dir'] = true;
        $this->dir = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function alias($value): static
    {
        $this->_usedProperties['alias'] = true;
        $this->alias = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function prefix($value): static
    {
        $this->_usedProperties['prefix'] = true;
        $this->prefix = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function isBundle($value): static
    {
        $this->_usedProperties['isBundle'] = true;
        $this->isBundle = $value;

        return $this;
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('mapping', $value)) {
            $this->_usedProperties['mapping'] = true;
            $this->mapping = $value['mapping'];
            unset($value['mapping']);
        }

        if (array_key_exists('type', $value)) {
            $this->_usedProperties['type'] = true;
            $this->type = $value['type'];
            unset($value['type']);
        }

        if (array_key_exists('dir', $value)) {
            $this->_usedProperties['dir'] = true;
            $this->dir = $value['dir'];
            unset($value['dir']);
        }

        if (array_key_exists('alias', $value)) {
            $this->_usedProperties['alias'] = true;
            $this->alias = $value['alias'];
            unset($value['alias']);
        }

        if (array_key_exists('prefix', $value)) {
            $this->_usedProperties['prefix'] = true;
            $this->prefix = $value['prefix'];
            unset($value['prefix']);
        }

        if (array_key_exists('is_bundle', $value)) {
            $this->_usedProperties['isBundle'] = true;
            $this->isBundle = $value['is_bundle'];
            unset($value['is_bundle']);
        }

        if ([] !== $value) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($value)));
        }
    }

    public function toArray(): array
    {
        $output = [];
        if (isset($this->_usedProperties['mapping'])) {
            $output['mapping'] = $this->mapping;
        }
        if (isset($this->_usedProperties['type'])) {
            $output['type'] = $this->type;
        }
        if (isset($this->_usedProperties['dir'])) {
            $output['dir'] = $this->dir;
        }
        if (isset($this->_usedProperties['alias'])) {
            $output['alias'] = $this->alias;
        }
        if (isset($this->_usedProperties['prefix'])) {
            $output['prefix'] = $this->prefix;
        }
        if (isset($this->_usedProperties['isBundle'])) {
            $output['is_bundle'] = $this->isBundle;
        }

        return $output;
    }

}
