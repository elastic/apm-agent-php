<?php

namespace Symfony\Config\Framework;

require_once __DIR__.\DIRECTORY_SEPARATOR.'Serializer'.\DIRECTORY_SEPARATOR.'MappingConfig.php';

use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class SerializerConfig 
{
    private $enabled;
    private $enableAnnotations;
    private $nameConverter;
    private $circularReferenceHandler;
    private $maxDepthHandler;
    private $mapping;
    private $defaultContext;
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
     * @default true
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function enableAnnotations($value): static
    {
        $this->_usedProperties['enableAnnotations'] = true;
        $this->enableAnnotations = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function nameConverter($value): static
    {
        $this->_usedProperties['nameConverter'] = true;
        $this->nameConverter = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function circularReferenceHandler($value): static
    {
        $this->_usedProperties['circularReferenceHandler'] = true;
        $this->circularReferenceHandler = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function maxDepthHandler($value): static
    {
        $this->_usedProperties['maxDepthHandler'] = true;
        $this->maxDepthHandler = $value;

        return $this;
    }

    /**
     * @default {"paths":[]}
    */
    public function mapping(array $value = []): \Symfony\Config\Framework\Serializer\MappingConfig
    {
        if (null === $this->mapping) {
            $this->_usedProperties['mapping'] = true;
            $this->mapping = new \Symfony\Config\Framework\Serializer\MappingConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "mapping()" has already been initialized. You cannot pass values the second time you call mapping().');
        }

        return $this->mapping;
    }

    /**
     * @return $this
     */
    public function defaultContext(string $name, mixed $value): static
    {
        $this->_usedProperties['defaultContext'] = true;
        $this->defaultContext[$name] = $value;

        return $this;
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('enabled', $value)) {
            $this->_usedProperties['enabled'] = true;
            $this->enabled = $value['enabled'];
            unset($value['enabled']);
        }

        if (array_key_exists('enable_annotations', $value)) {
            $this->_usedProperties['enableAnnotations'] = true;
            $this->enableAnnotations = $value['enable_annotations'];
            unset($value['enable_annotations']);
        }

        if (array_key_exists('name_converter', $value)) {
            $this->_usedProperties['nameConverter'] = true;
            $this->nameConverter = $value['name_converter'];
            unset($value['name_converter']);
        }

        if (array_key_exists('circular_reference_handler', $value)) {
            $this->_usedProperties['circularReferenceHandler'] = true;
            $this->circularReferenceHandler = $value['circular_reference_handler'];
            unset($value['circular_reference_handler']);
        }

        if (array_key_exists('max_depth_handler', $value)) {
            $this->_usedProperties['maxDepthHandler'] = true;
            $this->maxDepthHandler = $value['max_depth_handler'];
            unset($value['max_depth_handler']);
        }

        if (array_key_exists('mapping', $value)) {
            $this->_usedProperties['mapping'] = true;
            $this->mapping = new \Symfony\Config\Framework\Serializer\MappingConfig($value['mapping']);
            unset($value['mapping']);
        }

        if (array_key_exists('default_context', $value)) {
            $this->_usedProperties['defaultContext'] = true;
            $this->defaultContext = $value['default_context'];
            unset($value['default_context']);
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
        if (isset($this->_usedProperties['enableAnnotations'])) {
            $output['enable_annotations'] = $this->enableAnnotations;
        }
        if (isset($this->_usedProperties['nameConverter'])) {
            $output['name_converter'] = $this->nameConverter;
        }
        if (isset($this->_usedProperties['circularReferenceHandler'])) {
            $output['circular_reference_handler'] = $this->circularReferenceHandler;
        }
        if (isset($this->_usedProperties['maxDepthHandler'])) {
            $output['max_depth_handler'] = $this->maxDepthHandler;
        }
        if (isset($this->_usedProperties['mapping'])) {
            $output['mapping'] = $this->mapping->toArray();
        }
        if (isset($this->_usedProperties['defaultContext'])) {
            $output['default_context'] = $this->defaultContext;
        }

        return $output;
    }

}
