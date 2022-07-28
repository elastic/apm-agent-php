<?php

namespace Symfony\Config\Framework;

use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class SecretsConfig 
{
    private $enabled;
    private $vaultDirectory;
    private $localDotenvFile;
    private $decryptionEnvVar;
    private $_usedProperties = [];

    /**
     * @default true
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
     * @default '%kernel.project_dir%/config/secrets/%kernel.runtime_environment%'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function vaultDirectory($value): static
    {
        $this->_usedProperties['vaultDirectory'] = true;
        $this->vaultDirectory = $value;

        return $this;
    }

    /**
     * @default '%kernel.project_dir%/.env.%kernel.environment%.local'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function localDotenvFile($value): static
    {
        $this->_usedProperties['localDotenvFile'] = true;
        $this->localDotenvFile = $value;

        return $this;
    }

    /**
     * @default 'base64:default::SYMFONY_DECRYPTION_SECRET'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function decryptionEnvVar($value): static
    {
        $this->_usedProperties['decryptionEnvVar'] = true;
        $this->decryptionEnvVar = $value;

        return $this;
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('enabled', $value)) {
            $this->_usedProperties['enabled'] = true;
            $this->enabled = $value['enabled'];
            unset($value['enabled']);
        }

        if (array_key_exists('vault_directory', $value)) {
            $this->_usedProperties['vaultDirectory'] = true;
            $this->vaultDirectory = $value['vault_directory'];
            unset($value['vault_directory']);
        }

        if (array_key_exists('local_dotenv_file', $value)) {
            $this->_usedProperties['localDotenvFile'] = true;
            $this->localDotenvFile = $value['local_dotenv_file'];
            unset($value['local_dotenv_file']);
        }

        if (array_key_exists('decryption_env_var', $value)) {
            $this->_usedProperties['decryptionEnvVar'] = true;
            $this->decryptionEnvVar = $value['decryption_env_var'];
            unset($value['decryption_env_var']);
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
        if (isset($this->_usedProperties['vaultDirectory'])) {
            $output['vault_directory'] = $this->vaultDirectory;
        }
        if (isset($this->_usedProperties['localDotenvFile'])) {
            $output['local_dotenv_file'] = $this->localDotenvFile;
        }
        if (isset($this->_usedProperties['decryptionEnvVar'])) {
            $output['decryption_env_var'] = $this->decryptionEnvVar;
        }

        return $output;
    }

}
