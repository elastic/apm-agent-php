<?php

namespace Symfony\Config\Framework;

require_once __DIR__.\DIRECTORY_SEPARATOR.'Translator'.\DIRECTORY_SEPARATOR.'PseudoLocalizationConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'Translator'.\DIRECTORY_SEPARATOR.'ProviderConfig.php';

use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class TranslatorConfig 
{
    private $enabled;
    private $fallbacks;
    private $logging;
    private $formatter;
    private $cacheDir;
    private $defaultPath;
    private $paths;
    private $pseudoLocalization;
    private $providers;
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
     * @param mixed $value
     *
     * @return $this
     */
    public function fallbacks(mixed $value): static
    {
        $this->_usedProperties['fallbacks'] = true;
        $this->fallbacks = $value;

        return $this;
    }

    /**
     * @default false
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function logging($value): static
    {
        $this->_usedProperties['logging'] = true;
        $this->logging = $value;

        return $this;
    }

    /**
     * @default 'translator.formatter.default'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function formatter($value): static
    {
        $this->_usedProperties['formatter'] = true;
        $this->formatter = $value;

        return $this;
    }

    /**
     * @default '%kernel.cache_dir%/translations'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function cacheDir($value): static
    {
        $this->_usedProperties['cacheDir'] = true;
        $this->cacheDir = $value;

        return $this;
    }

    /**
     * The default path used to load translations
     * @default '%kernel.project_dir%/translations'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function defaultPath($value): static
    {
        $this->_usedProperties['defaultPath'] = true;
        $this->defaultPath = $value;

        return $this;
    }

    /**
     * @param ParamConfigurator|list<ParamConfigurator|mixed> $value
     *
     * @return $this
     */
    public function paths(ParamConfigurator|array $value): static
    {
        $this->_usedProperties['paths'] = true;
        $this->paths = $value;

        return $this;
    }

    /**
     * @default {"enabled":false,"accents":true,"expansion_factor":1,"brackets":true,"parse_html":false,"localizable_html_attributes":[]}
     * @return \Symfony\Config\Framework\Translator\PseudoLocalizationConfig|$this
     */
    public function pseudoLocalization(mixed $value = []): \Symfony\Config\Framework\Translator\PseudoLocalizationConfig|static
    {
        if (!\is_array($value)) {
            $this->_usedProperties['pseudoLocalization'] = true;
            $this->pseudoLocalization = $value;

            return $this;
        }

        if (!$this->pseudoLocalization instanceof \Symfony\Config\Framework\Translator\PseudoLocalizationConfig) {
            $this->_usedProperties['pseudoLocalization'] = true;
            $this->pseudoLocalization = new \Symfony\Config\Framework\Translator\PseudoLocalizationConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "pseudoLocalization()" has already been initialized. You cannot pass values the second time you call pseudoLocalization().');
        }

        return $this->pseudoLocalization;
    }

    /**
     * Translation providers you can read/write your translations from
    */
    public function provider(string $name, array $value = []): \Symfony\Config\Framework\Translator\ProviderConfig
    {
        if (!isset($this->providers[$name])) {
            $this->_usedProperties['providers'] = true;
            $this->providers[$name] = new \Symfony\Config\Framework\Translator\ProviderConfig($value);
        } elseif (1 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "provider()" has already been initialized. You cannot pass values the second time you call provider().');
        }

        return $this->providers[$name];
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('enabled', $value)) {
            $this->_usedProperties['enabled'] = true;
            $this->enabled = $value['enabled'];
            unset($value['enabled']);
        }

        if (array_key_exists('fallbacks', $value)) {
            $this->_usedProperties['fallbacks'] = true;
            $this->fallbacks = $value['fallbacks'];
            unset($value['fallbacks']);
        }

        if (array_key_exists('logging', $value)) {
            $this->_usedProperties['logging'] = true;
            $this->logging = $value['logging'];
            unset($value['logging']);
        }

        if (array_key_exists('formatter', $value)) {
            $this->_usedProperties['formatter'] = true;
            $this->formatter = $value['formatter'];
            unset($value['formatter']);
        }

        if (array_key_exists('cache_dir', $value)) {
            $this->_usedProperties['cacheDir'] = true;
            $this->cacheDir = $value['cache_dir'];
            unset($value['cache_dir']);
        }

        if (array_key_exists('default_path', $value)) {
            $this->_usedProperties['defaultPath'] = true;
            $this->defaultPath = $value['default_path'];
            unset($value['default_path']);
        }

        if (array_key_exists('paths', $value)) {
            $this->_usedProperties['paths'] = true;
            $this->paths = $value['paths'];
            unset($value['paths']);
        }

        if (array_key_exists('pseudo_localization', $value)) {
            $this->_usedProperties['pseudoLocalization'] = true;
            $this->pseudoLocalization = \is_array($value['pseudo_localization']) ? new \Symfony\Config\Framework\Translator\PseudoLocalizationConfig($value['pseudo_localization']) : $value['pseudo_localization'];
            unset($value['pseudo_localization']);
        }

        if (array_key_exists('providers', $value)) {
            $this->_usedProperties['providers'] = true;
            $this->providers = array_map(function ($v) { return new \Symfony\Config\Framework\Translator\ProviderConfig($v); }, $value['providers']);
            unset($value['providers']);
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
        if (isset($this->_usedProperties['fallbacks'])) {
            $output['fallbacks'] = $this->fallbacks;
        }
        if (isset($this->_usedProperties['logging'])) {
            $output['logging'] = $this->logging;
        }
        if (isset($this->_usedProperties['formatter'])) {
            $output['formatter'] = $this->formatter;
        }
        if (isset($this->_usedProperties['cacheDir'])) {
            $output['cache_dir'] = $this->cacheDir;
        }
        if (isset($this->_usedProperties['defaultPath'])) {
            $output['default_path'] = $this->defaultPath;
        }
        if (isset($this->_usedProperties['paths'])) {
            $output['paths'] = $this->paths;
        }
        if (isset($this->_usedProperties['pseudoLocalization'])) {
            $output['pseudo_localization'] = $this->pseudoLocalization instanceof \Symfony\Config\Framework\Translator\PseudoLocalizationConfig ? $this->pseudoLocalization->toArray() : $this->pseudoLocalization;
        }
        if (isset($this->_usedProperties['providers'])) {
            $output['providers'] = array_map(function ($v) { return $v->toArray(); }, $this->providers);
        }

        return $output;
    }

}
