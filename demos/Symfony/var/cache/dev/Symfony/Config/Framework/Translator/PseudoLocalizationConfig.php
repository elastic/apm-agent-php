<?php

namespace Symfony\Config\Framework\Translator;

use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class PseudoLocalizationConfig 
{
    private $enabled;
    private $accents;
    private $expansionFactor;
    private $brackets;
    private $parseHtml;
    private $localizableHtmlAttributes;
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
    public function accents($value): static
    {
        $this->_usedProperties['accents'] = true;
        $this->accents = $value;

        return $this;
    }

    /**
     * @default 1.0
     * @param ParamConfigurator|float $value
     * @return $this
     */
    public function expansionFactor($value): static
    {
        $this->_usedProperties['expansionFactor'] = true;
        $this->expansionFactor = $value;

        return $this;
    }

    /**
     * @default true
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function brackets($value): static
    {
        $this->_usedProperties['brackets'] = true;
        $this->brackets = $value;

        return $this;
    }

    /**
     * @default false
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function parseHtml($value): static
    {
        $this->_usedProperties['parseHtml'] = true;
        $this->parseHtml = $value;

        return $this;
    }

    /**
     * @param ParamConfigurator|list<ParamConfigurator|mixed> $value
     *
     * @return $this
     */
    public function localizableHtmlAttributes(ParamConfigurator|array $value): static
    {
        $this->_usedProperties['localizableHtmlAttributes'] = true;
        $this->localizableHtmlAttributes = $value;

        return $this;
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('enabled', $value)) {
            $this->_usedProperties['enabled'] = true;
            $this->enabled = $value['enabled'];
            unset($value['enabled']);
        }

        if (array_key_exists('accents', $value)) {
            $this->_usedProperties['accents'] = true;
            $this->accents = $value['accents'];
            unset($value['accents']);
        }

        if (array_key_exists('expansion_factor', $value)) {
            $this->_usedProperties['expansionFactor'] = true;
            $this->expansionFactor = $value['expansion_factor'];
            unset($value['expansion_factor']);
        }

        if (array_key_exists('brackets', $value)) {
            $this->_usedProperties['brackets'] = true;
            $this->brackets = $value['brackets'];
            unset($value['brackets']);
        }

        if (array_key_exists('parse_html', $value)) {
            $this->_usedProperties['parseHtml'] = true;
            $this->parseHtml = $value['parse_html'];
            unset($value['parse_html']);
        }

        if (array_key_exists('localizable_html_attributes', $value)) {
            $this->_usedProperties['localizableHtmlAttributes'] = true;
            $this->localizableHtmlAttributes = $value['localizable_html_attributes'];
            unset($value['localizable_html_attributes']);
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
        if (isset($this->_usedProperties['accents'])) {
            $output['accents'] = $this->accents;
        }
        if (isset($this->_usedProperties['expansionFactor'])) {
            $output['expansion_factor'] = $this->expansionFactor;
        }
        if (isset($this->_usedProperties['brackets'])) {
            $output['brackets'] = $this->brackets;
        }
        if (isset($this->_usedProperties['parseHtml'])) {
            $output['parse_html'] = $this->parseHtml;
        }
        if (isset($this->_usedProperties['localizableHtmlAttributes'])) {
            $output['localizable_html_attributes'] = $this->localizableHtmlAttributes;
        }

        return $output;
    }

}
