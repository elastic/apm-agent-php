<?php

namespace Symfony\Config\Framework\Workflows;

require_once __DIR__.\DIRECTORY_SEPARATOR.'WorkflowsConfig'.\DIRECTORY_SEPARATOR.'AuditTrailConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'WorkflowsConfig'.\DIRECTORY_SEPARATOR.'MarkingStoreConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'WorkflowsConfig'.\DIRECTORY_SEPARATOR.'PlaceConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'WorkflowsConfig'.\DIRECTORY_SEPARATOR.'TransitionConfig.php';

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Loader\ParamConfigurator;

/**
 * This class is automatically generated to help in creating a config.
 */
class WorkflowsConfig 
{
    private $auditTrail;
    private $type;
    private $markingStore;
    private $supports;
    private $supportStrategy;
    private $initialMarking;
    private $eventsToDispatch;
    private $places;
    private $transitions;
    private $metadata;
    private $_usedProperties = [];

    /**
     * @default {"enabled":false}
     * @return \Symfony\Config\Framework\Workflows\WorkflowsConfig\AuditTrailConfig|$this
     */
    public function auditTrail(mixed $value = []): \Symfony\Config\Framework\Workflows\WorkflowsConfig\AuditTrailConfig|static
    {
        if (!\is_array($value)) {
            $this->_usedProperties['auditTrail'] = true;
            $this->auditTrail = $value;

            return $this;
        }

        if (!$this->auditTrail instanceof \Symfony\Config\Framework\Workflows\WorkflowsConfig\AuditTrailConfig) {
            $this->_usedProperties['auditTrail'] = true;
            $this->auditTrail = new \Symfony\Config\Framework\Workflows\WorkflowsConfig\AuditTrailConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "auditTrail()" has already been initialized. You cannot pass values the second time you call auditTrail().');
        }

        return $this->auditTrail;
    }

    /**
     * @default 'state_machine'
     * @param ParamConfigurator|'workflow'|'state_machine' $value
     * @return $this
     */
    public function type($value): static
    {
        $this->_usedProperties['type'] = true;
        $this->type = $value;

        return $this;
    }

    public function markingStore(array $value = []): \Symfony\Config\Framework\Workflows\WorkflowsConfig\MarkingStoreConfig
    {
        if (null === $this->markingStore) {
            $this->_usedProperties['markingStore'] = true;
            $this->markingStore = new \Symfony\Config\Framework\Workflows\WorkflowsConfig\MarkingStoreConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "markingStore()" has already been initialized. You cannot pass values the second time you call markingStore().');
        }

        return $this->markingStore;
    }

    /**
     * @param mixed $value
     *
     * @return $this
     */
    public function supports(mixed $value): static
    {
        $this->_usedProperties['supports'] = true;
        $this->supports = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function supportStrategy($value): static
    {
        $this->_usedProperties['supportStrategy'] = true;
        $this->supportStrategy = $value;

        return $this;
    }

    /**
     * @param mixed $value
     *
     * @return $this
     */
    public function initialMarking(mixed $value): static
    {
        $this->_usedProperties['initialMarking'] = true;
        $this->initialMarking = $value;

        return $this;
    }

    /**
     * Select which Transition events should be dispatched for this Workflow
     * @example workflow.enter
     * @example workflow.transition
     * @default null
     * @param ParamConfigurator|mixed $value
     *
     * @return $this
     */
    public function eventsToDispatch(mixed $value = NULL): static
    {
        $this->_usedProperties['eventsToDispatch'] = true;
        $this->eventsToDispatch = $value;

        return $this;
    }

    /**
     * @return \Symfony\Config\Framework\Workflows\WorkflowsConfig\PlaceConfig|$this
     */
    public function place(mixed $value = []): \Symfony\Config\Framework\Workflows\WorkflowsConfig\PlaceConfig|static
    {
        $this->_usedProperties['places'] = true;
        if (!\is_array($value)) {
            $this->places[] = $value;

            return $this;
        }

        return $this->places[] = new \Symfony\Config\Framework\Workflows\WorkflowsConfig\PlaceConfig($value);
    }

    /**
     * @return \Symfony\Config\Framework\Workflows\WorkflowsConfig\TransitionConfig|$this
     */
    public function transition(mixed $value = []): \Symfony\Config\Framework\Workflows\WorkflowsConfig\TransitionConfig|static
    {
        $this->_usedProperties['transitions'] = true;
        if (!\is_array($value)) {
            $this->transitions[] = $value;

            return $this;
        }

        return $this->transitions[] = new \Symfony\Config\Framework\Workflows\WorkflowsConfig\TransitionConfig($value);
    }

    /**
     * @param ParamConfigurator|list<ParamConfigurator|mixed> $value
     *
     * @return $this
     */
    public function metadata(ParamConfigurator|array $value): static
    {
        $this->_usedProperties['metadata'] = true;
        $this->metadata = $value;

        return $this;
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('audit_trail', $value)) {
            $this->_usedProperties['auditTrail'] = true;
            $this->auditTrail = \is_array($value['audit_trail']) ? new \Symfony\Config\Framework\Workflows\WorkflowsConfig\AuditTrailConfig($value['audit_trail']) : $value['audit_trail'];
            unset($value['audit_trail']);
        }

        if (array_key_exists('type', $value)) {
            $this->_usedProperties['type'] = true;
            $this->type = $value['type'];
            unset($value['type']);
        }

        if (array_key_exists('marking_store', $value)) {
            $this->_usedProperties['markingStore'] = true;
            $this->markingStore = new \Symfony\Config\Framework\Workflows\WorkflowsConfig\MarkingStoreConfig($value['marking_store']);
            unset($value['marking_store']);
        }

        if (array_key_exists('supports', $value)) {
            $this->_usedProperties['supports'] = true;
            $this->supports = $value['supports'];
            unset($value['supports']);
        }

        if (array_key_exists('support_strategy', $value)) {
            $this->_usedProperties['supportStrategy'] = true;
            $this->supportStrategy = $value['support_strategy'];
            unset($value['support_strategy']);
        }

        if (array_key_exists('initial_marking', $value)) {
            $this->_usedProperties['initialMarking'] = true;
            $this->initialMarking = $value['initial_marking'];
            unset($value['initial_marking']);
        }

        if (array_key_exists('events_to_dispatch', $value)) {
            $this->_usedProperties['eventsToDispatch'] = true;
            $this->eventsToDispatch = $value['events_to_dispatch'];
            unset($value['events_to_dispatch']);
        }

        if (array_key_exists('places', $value)) {
            $this->_usedProperties['places'] = true;
            $this->places = array_map(function ($v) { return \is_array($v) ? new \Symfony\Config\Framework\Workflows\WorkflowsConfig\PlaceConfig($v) : $v; }, $value['places']);
            unset($value['places']);
        }

        if (array_key_exists('transitions', $value)) {
            $this->_usedProperties['transitions'] = true;
            $this->transitions = array_map(function ($v) { return \is_array($v) ? new \Symfony\Config\Framework\Workflows\WorkflowsConfig\TransitionConfig($v) : $v; }, $value['transitions']);
            unset($value['transitions']);
        }

        if (array_key_exists('metadata', $value)) {
            $this->_usedProperties['metadata'] = true;
            $this->metadata = $value['metadata'];
            unset($value['metadata']);
        }

        if ([] !== $value) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($value)));
        }
    }

    public function toArray(): array
    {
        $output = [];
        if (isset($this->_usedProperties['auditTrail'])) {
            $output['audit_trail'] = $this->auditTrail instanceof \Symfony\Config\Framework\Workflows\WorkflowsConfig\AuditTrailConfig ? $this->auditTrail->toArray() : $this->auditTrail;
        }
        if (isset($this->_usedProperties['type'])) {
            $output['type'] = $this->type;
        }
        if (isset($this->_usedProperties['markingStore'])) {
            $output['marking_store'] = $this->markingStore->toArray();
        }
        if (isset($this->_usedProperties['supports'])) {
            $output['supports'] = $this->supports;
        }
        if (isset($this->_usedProperties['supportStrategy'])) {
            $output['support_strategy'] = $this->supportStrategy;
        }
        if (isset($this->_usedProperties['initialMarking'])) {
            $output['initial_marking'] = $this->initialMarking;
        }
        if (isset($this->_usedProperties['eventsToDispatch'])) {
            $output['events_to_dispatch'] = $this->eventsToDispatch;
        }
        if (isset($this->_usedProperties['places'])) {
            $output['places'] = array_map(function ($v) { return $v instanceof \Symfony\Config\Framework\Workflows\WorkflowsConfig\PlaceConfig ? $v->toArray() : $v; }, $this->places);
        }
        if (isset($this->_usedProperties['transitions'])) {
            $output['transitions'] = array_map(function ($v) { return $v instanceof \Symfony\Config\Framework\Workflows\WorkflowsConfig\TransitionConfig ? $v->toArray() : $v; }, $this->transitions);
        }
        if (isset($this->_usedProperties['metadata'])) {
            $output['metadata'] = $this->metadata;
        }

        return $output;
    }

}
