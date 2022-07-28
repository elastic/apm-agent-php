<?php

namespace Symfony\Config;

require_once __DIR__.\DIRECTORY_SEPARATOR.'Doctrine'.\DIRECTORY_SEPARATOR.'DbalConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'Doctrine'.\DIRECTORY_SEPARATOR.'OrmConfig.php';

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class DoctrineConfig implements \Symfony\Component\Config\Builder\ConfigBuilderInterface
{
    private $dbal;
    private $orm;
    private $_usedProperties = [];

    /**
     * @return \Symfony\Config\Doctrine\DbalConfig|$this
     */
    public function dbal(mixed $value = []): \Symfony\Config\Doctrine\DbalConfig|static
    {
        if (!\is_array($value)) {
            $this->_usedProperties['dbal'] = true;
            $this->dbal = $value;

            return $this;
        }

        if (!$this->dbal instanceof \Symfony\Config\Doctrine\DbalConfig) {
            $this->_usedProperties['dbal'] = true;
            $this->dbal = new \Symfony\Config\Doctrine\DbalConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "dbal()" has already been initialized. You cannot pass values the second time you call dbal().');
        }

        return $this->dbal;
    }

    /**
     * @return \Symfony\Config\Doctrine\OrmConfig|$this
     */
    public function orm(mixed $value = []): \Symfony\Config\Doctrine\OrmConfig|static
    {
        if (!\is_array($value)) {
            $this->_usedProperties['orm'] = true;
            $this->orm = $value;

            return $this;
        }

        if (!$this->orm instanceof \Symfony\Config\Doctrine\OrmConfig) {
            $this->_usedProperties['orm'] = true;
            $this->orm = new \Symfony\Config\Doctrine\OrmConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "orm()" has already been initialized. You cannot pass values the second time you call orm().');
        }

        return $this->orm;
    }

    public function getExtensionAlias(): string
    {
        return 'doctrine';
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('dbal', $value)) {
            $this->_usedProperties['dbal'] = true;
            $this->dbal = \is_array($value['dbal']) ? new \Symfony\Config\Doctrine\DbalConfig($value['dbal']) : $value['dbal'];
            unset($value['dbal']);
        }

        if (array_key_exists('orm', $value)) {
            $this->_usedProperties['orm'] = true;
            $this->orm = \is_array($value['orm']) ? new \Symfony\Config\Doctrine\OrmConfig($value['orm']) : $value['orm'];
            unset($value['orm']);
        }

        if ([] !== $value) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($value)));
        }
    }

    public function toArray(): array
    {
        $output = [];
        if (isset($this->_usedProperties['dbal'])) {
            $output['dbal'] = $this->dbal instanceof \Symfony\Config\Doctrine\DbalConfig ? $this->dbal->toArray() : $this->dbal;
        }
        if (isset($this->_usedProperties['orm'])) {
            $output['orm'] = $this->orm instanceof \Symfony\Config\Doctrine\OrmConfig ? $this->orm->toArray() : $this->orm;
        }

        return $output;
    }

}
