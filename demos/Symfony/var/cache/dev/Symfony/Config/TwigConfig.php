<?php

namespace Symfony\Config;

require_once __DIR__.\DIRECTORY_SEPARATOR.'Twig'.\DIRECTORY_SEPARATOR.'GlobalConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'Twig'.\DIRECTORY_SEPARATOR.'DateConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'Twig'.\DIRECTORY_SEPARATOR.'NumberFormatConfig.php';

use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class TwigConfig implements \Symfony\Component\Config\Builder\ConfigBuilderInterface
{
    private $formThemes;
    private $globals;
    private $autoescape;
    private $autoescapeService;
    private $autoescapeServiceMethod;
    private $baseTemplateClass;
    private $cache;
    private $charset;
    private $debug;
    private $strictVariables;
    private $autoReload;
    private $optimizations;
    private $defaultPath;
    private $fileNamePattern;
    private $paths;
    private $date;
    private $numberFormat;
    private $_usedProperties = [];

    /**
     * @param ParamConfigurator|list<ParamConfigurator|mixed> $value
     *
     * @return $this
     */
    public function formThemes(ParamConfigurator|array $value): static
    {
        $this->_usedProperties['formThemes'] = true;
        $this->formThemes = $value;

        return $this;
    }

    /**
     * @example "@bar"
     * @example 3.14
     * @return \Symfony\Config\Twig\GlobalConfig|$this
     */
    public function global(string $key, mixed $value = []): \Symfony\Config\Twig\GlobalConfig|static
    {
        if (!\is_array($value)) {
            $this->_usedProperties['globals'] = true;
            $this->globals[$key] = $value;

            return $this;
        }

        if (!isset($this->globals[$key]) || !$this->globals[$key] instanceof \Symfony\Config\Twig\GlobalConfig) {
            $this->_usedProperties['globals'] = true;
            $this->globals[$key] = new \Symfony\Config\Twig\GlobalConfig($value);
        } elseif (1 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "global()" has already been initialized. You cannot pass values the second time you call global().');
        }

        return $this->globals[$key];
    }

    /**
     * @default 'name'
     * @param ParamConfigurator|mixed $value
     * @deprecated Option "autoescape" at "twig" is deprecated, use autoescape_service[_method] instead.
     *
     * @return $this
     */
    public function autoescape(mixed $value = 'name'): static
    {
        $this->_usedProperties['autoescape'] = true;
        $this->autoescape = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function autoescapeService($value): static
    {
        $this->_usedProperties['autoescapeService'] = true;
        $this->autoescapeService = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function autoescapeServiceMethod($value): static
    {
        $this->_usedProperties['autoescapeServiceMethod'] = true;
        $this->autoescapeServiceMethod = $value;

        return $this;
    }

    /**
     * @example Twig\Template
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function baseTemplateClass($value): static
    {
        $this->_usedProperties['baseTemplateClass'] = true;
        $this->baseTemplateClass = $value;

        return $this;
    }

    /**
     * @default '%kernel.cache_dir%/twig'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function cache($value): static
    {
        $this->_usedProperties['cache'] = true;
        $this->cache = $value;

        return $this;
    }

    /**
     * @default '%kernel.charset%'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function charset($value): static
    {
        $this->_usedProperties['charset'] = true;
        $this->charset = $value;

        return $this;
    }

    /**
     * @default '%kernel.debug%'
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function debug($value): static
    {
        $this->_usedProperties['debug'] = true;
        $this->debug = $value;

        return $this;
    }

    /**
     * @default '%kernel.debug%'
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function strictVariables($value): static
    {
        $this->_usedProperties['strictVariables'] = true;
        $this->strictVariables = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function autoReload($value): static
    {
        $this->_usedProperties['autoReload'] = true;
        $this->autoReload = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|int $value
     * @return $this
     */
    public function optimizations($value): static
    {
        $this->_usedProperties['optimizations'] = true;
        $this->optimizations = $value;

        return $this;
    }

    /**
     * The default path used to load templates
     * @default '%kernel.project_dir%/templates'
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
     * @param mixed $value
     *
     * @return $this
     */
    public function fileNamePattern(mixed $value): static
    {
        $this->_usedProperties['fileNamePattern'] = true;
        $this->fileNamePattern = $value;

        return $this;
    }

    /**
     * @return $this
     */
    public function path(string $paths, mixed $value): static
    {
        $this->_usedProperties['paths'] = true;
        $this->paths[$paths] = $value;

        return $this;
    }

    /**
     * The default format options used by the date filter
     * @default {"format":"F j, Y H:i","interval_format":"%d days","timezone":null}
    */
    public function date(array $value = []): \Symfony\Config\Twig\DateConfig
    {
        if (null === $this->date) {
            $this->_usedProperties['date'] = true;
            $this->date = new \Symfony\Config\Twig\DateConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "date()" has already been initialized. You cannot pass values the second time you call date().');
        }

        return $this->date;
    }

    /**
     * The default format options for the number_format filter
     * @default {"decimals":0,"decimal_point":".","thousands_separator":","}
    */
    public function numberFormat(array $value = []): \Symfony\Config\Twig\NumberFormatConfig
    {
        if (null === $this->numberFormat) {
            $this->_usedProperties['numberFormat'] = true;
            $this->numberFormat = new \Symfony\Config\Twig\NumberFormatConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "numberFormat()" has already been initialized. You cannot pass values the second time you call numberFormat().');
        }

        return $this->numberFormat;
    }

    public function getExtensionAlias(): string
    {
        return 'twig';
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('form_themes', $value)) {
            $this->_usedProperties['formThemes'] = true;
            $this->formThemes = $value['form_themes'];
            unset($value['form_themes']);
        }

        if (array_key_exists('globals', $value)) {
            $this->_usedProperties['globals'] = true;
            $this->globals = array_map(function ($v) { return \is_array($v) ? new \Symfony\Config\Twig\GlobalConfig($v) : $v; }, $value['globals']);
            unset($value['globals']);
        }

        if (array_key_exists('autoescape', $value)) {
            $this->_usedProperties['autoescape'] = true;
            $this->autoescape = $value['autoescape'];
            unset($value['autoescape']);
        }

        if (array_key_exists('autoescape_service', $value)) {
            $this->_usedProperties['autoescapeService'] = true;
            $this->autoescapeService = $value['autoescape_service'];
            unset($value['autoescape_service']);
        }

        if (array_key_exists('autoescape_service_method', $value)) {
            $this->_usedProperties['autoescapeServiceMethod'] = true;
            $this->autoescapeServiceMethod = $value['autoescape_service_method'];
            unset($value['autoescape_service_method']);
        }

        if (array_key_exists('base_template_class', $value)) {
            $this->_usedProperties['baseTemplateClass'] = true;
            $this->baseTemplateClass = $value['base_template_class'];
            unset($value['base_template_class']);
        }

        if (array_key_exists('cache', $value)) {
            $this->_usedProperties['cache'] = true;
            $this->cache = $value['cache'];
            unset($value['cache']);
        }

        if (array_key_exists('charset', $value)) {
            $this->_usedProperties['charset'] = true;
            $this->charset = $value['charset'];
            unset($value['charset']);
        }

        if (array_key_exists('debug', $value)) {
            $this->_usedProperties['debug'] = true;
            $this->debug = $value['debug'];
            unset($value['debug']);
        }

        if (array_key_exists('strict_variables', $value)) {
            $this->_usedProperties['strictVariables'] = true;
            $this->strictVariables = $value['strict_variables'];
            unset($value['strict_variables']);
        }

        if (array_key_exists('auto_reload', $value)) {
            $this->_usedProperties['autoReload'] = true;
            $this->autoReload = $value['auto_reload'];
            unset($value['auto_reload']);
        }

        if (array_key_exists('optimizations', $value)) {
            $this->_usedProperties['optimizations'] = true;
            $this->optimizations = $value['optimizations'];
            unset($value['optimizations']);
        }

        if (array_key_exists('default_path', $value)) {
            $this->_usedProperties['defaultPath'] = true;
            $this->defaultPath = $value['default_path'];
            unset($value['default_path']);
        }

        if (array_key_exists('file_name_pattern', $value)) {
            $this->_usedProperties['fileNamePattern'] = true;
            $this->fileNamePattern = $value['file_name_pattern'];
            unset($value['file_name_pattern']);
        }

        if (array_key_exists('paths', $value)) {
            $this->_usedProperties['paths'] = true;
            $this->paths = $value['paths'];
            unset($value['paths']);
        }

        if (array_key_exists('date', $value)) {
            $this->_usedProperties['date'] = true;
            $this->date = new \Symfony\Config\Twig\DateConfig($value['date']);
            unset($value['date']);
        }

        if (array_key_exists('number_format', $value)) {
            $this->_usedProperties['numberFormat'] = true;
            $this->numberFormat = new \Symfony\Config\Twig\NumberFormatConfig($value['number_format']);
            unset($value['number_format']);
        }

        if ([] !== $value) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($value)));
        }
    }

    public function toArray(): array
    {
        $output = [];
        if (isset($this->_usedProperties['formThemes'])) {
            $output['form_themes'] = $this->formThemes;
        }
        if (isset($this->_usedProperties['globals'])) {
            $output['globals'] = array_map(function ($v) { return $v instanceof \Symfony\Config\Twig\GlobalConfig ? $v->toArray() : $v; }, $this->globals);
        }
        if (isset($this->_usedProperties['autoescape'])) {
            $output['autoescape'] = $this->autoescape;
        }
        if (isset($this->_usedProperties['autoescapeService'])) {
            $output['autoescape_service'] = $this->autoescapeService;
        }
        if (isset($this->_usedProperties['autoescapeServiceMethod'])) {
            $output['autoescape_service_method'] = $this->autoescapeServiceMethod;
        }
        if (isset($this->_usedProperties['baseTemplateClass'])) {
            $output['base_template_class'] = $this->baseTemplateClass;
        }
        if (isset($this->_usedProperties['cache'])) {
            $output['cache'] = $this->cache;
        }
        if (isset($this->_usedProperties['charset'])) {
            $output['charset'] = $this->charset;
        }
        if (isset($this->_usedProperties['debug'])) {
            $output['debug'] = $this->debug;
        }
        if (isset($this->_usedProperties['strictVariables'])) {
            $output['strict_variables'] = $this->strictVariables;
        }
        if (isset($this->_usedProperties['autoReload'])) {
            $output['auto_reload'] = $this->autoReload;
        }
        if (isset($this->_usedProperties['optimizations'])) {
            $output['optimizations'] = $this->optimizations;
        }
        if (isset($this->_usedProperties['defaultPath'])) {
            $output['default_path'] = $this->defaultPath;
        }
        if (isset($this->_usedProperties['fileNamePattern'])) {
            $output['file_name_pattern'] = $this->fileNamePattern;
        }
        if (isset($this->_usedProperties['paths'])) {
            $output['paths'] = $this->paths;
        }
        if (isset($this->_usedProperties['date'])) {
            $output['date'] = $this->date->toArray();
        }
        if (isset($this->_usedProperties['numberFormat'])) {
            $output['number_format'] = $this->numberFormat->toArray();
        }

        return $output;
    }

}
