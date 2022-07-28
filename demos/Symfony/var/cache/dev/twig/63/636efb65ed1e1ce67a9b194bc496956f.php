<?php

use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Extension\SandboxExtension;
use Twig\Markup;
use Twig\Sandbox\SecurityError;
use Twig\Sandbox\SecurityNotAllowedTagError;
use Twig\Sandbox\SecurityNotAllowedFilterError;
use Twig\Sandbox\SecurityNotAllowedFunctionError;
use Twig\Source;
use Twig\Template;

/* @WebProfiler/Collector/time.css.twig */
class __TwigTemplate_3c1d1f557b73f8efe8ccb717fc143d3c extends Template
{
    private $source;
    private $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->parent = false;

        $this->blocks = [
        ];
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        $__internal_5a27a8ba21ca79b61932376b2fa922d2 = $this->extensions["Symfony\\Bundle\\WebProfilerBundle\\Twig\\WebProfilerExtension"];
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->enter($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "@WebProfiler/Collector/time.css.twig"));

        $__internal_6f47bbe9983af81f1e7450e9a3e3768f = $this->extensions["Symfony\\Bridge\\Twig\\Extension\\ProfilerExtension"];
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->enter($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "@WebProfiler/Collector/time.css.twig"));

        // line 1
        echo "/* Legend */

.sf-profiler-timeline .legends .timeline-category {
    border: none;
    background: none;
    border-left: 1em solid transparent;
    line-height: 1em;
    margin: 0 1em 0 0;
    padding: 0 0.5em;
    display: none;
    opacity: 0.5;
}

.sf-profiler-timeline .legends .timeline-category.active {
    opacity: 1;
}

.sf-profiler-timeline .legends .timeline-category.present {
    display: inline-block;
}

.timeline-graph {
    margin: 1em 0;
    width: 100%;
    background-color: var(--table-background);
    border: 1px solid var(--table-border);
}

/* Typography */

.timeline-graph .timeline-label {
    font-family: var(--font-sans-serif);
    font-size: 12px;
    line-height: 12px;
    font-weight: normal;
    fill: var(--color-text);
}

.timeline-graph .timeline-label .timeline-sublabel {
    margin-left: 1em;
    fill: var(--color-muted);
}

.timeline-graph .timeline-subrequest,
.timeline-graph .timeline-border {
    fill: none;
    stroke: var(--table-border);
    stroke-width: 1px;
}

.timeline-graph .timeline-subrequest {
    fill: url(#subrequest);
    fill-opacity: 0.5;
}

.timeline-subrequest-pattern {
    fill: var(--table-border);
}

/* Timeline periods */

.timeline-graph .timeline-period {
    stroke-width: 0;
}
";
        
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->leave($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof);

        
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->leave($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof);

    }

    public function getTemplateName()
    {
        return "@WebProfiler/Collector/time.css.twig";
    }

    public function getDebugInfo()
    {
        return array (  43 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("/* Legend */

.sf-profiler-timeline .legends .timeline-category {
    border: none;
    background: none;
    border-left: 1em solid transparent;
    line-height: 1em;
    margin: 0 1em 0 0;
    padding: 0 0.5em;
    display: none;
    opacity: 0.5;
}

.sf-profiler-timeline .legends .timeline-category.active {
    opacity: 1;
}

.sf-profiler-timeline .legends .timeline-category.present {
    display: inline-block;
}

.timeline-graph {
    margin: 1em 0;
    width: 100%;
    background-color: var(--table-background);
    border: 1px solid var(--table-border);
}

/* Typography */

.timeline-graph .timeline-label {
    font-family: var(--font-sans-serif);
    font-size: 12px;
    line-height: 12px;
    font-weight: normal;
    fill: var(--color-text);
}

.timeline-graph .timeline-label .timeline-sublabel {
    margin-left: 1em;
    fill: var(--color-muted);
}

.timeline-graph .timeline-subrequest,
.timeline-graph .timeline-border {
    fill: none;
    stroke: var(--table-border);
    stroke-width: 1px;
}

.timeline-graph .timeline-subrequest {
    fill: url(#subrequest);
    fill-opacity: 0.5;
}

.timeline-subrequest-pattern {
    fill: var(--table-border);
}

/* Timeline periods */

.timeline-graph .timeline-period {
    stroke-width: 0;
}
", "@WebProfiler/Collector/time.css.twig", "/var/www/symfony/symfony-demo/vendor/symfony/web-profiler-bundle/Resources/views/Collector/time.css.twig");
    }
}
