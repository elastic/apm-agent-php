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

/* @WebProfiler/Icon/validator.svg */
class __TwigTemplate_fc80e5cd9b0de0e3679218c64c58684d extends Template
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
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->enter($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "@WebProfiler/Icon/validator.svg"));

        $__internal_6f47bbe9983af81f1e7450e9a3e3768f = $this->extensions["Symfony\\Bridge\\Twig\\Extension\\ProfilerExtension"];
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->enter($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "@WebProfiler/Icon/validator.svg"));

        // line 1
        echo "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" viewBox=\"0 0 24 24\"><path fill=\"#aaa\" d=\"M19.5 22.5H4.3a2.9 2.9 0 0 1-2.9-2.9V4.4a2.9 2.9 0 0 1 2.9-2.9h14.2a1 1 0 0 1 0 2H4.3a.9.9 0 0 0-.9.9v15.2a.9.9 0 0 0 .9.9h15.2a.9.9 0 0 0 1-.9v-8.3a1 1 0 1 1 2 0v8.3a2.9 2.9 0 0 1-3 2.9zM13 17.3L22.9 6a1.5 1.5 0 1 0-2.3-2L12 14 8 9.1A1.5 1.5 0 0 0 5.7 11l5 6.3a1.5 1.5 0 0 0 1.2.5 1.5 1.5 0 0 0 1.1-.5z\"/></svg>
";
        
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->leave($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof);

        
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->leave($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof);

    }

    public function getTemplateName()
    {
        return "@WebProfiler/Icon/validator.svg";
    }

    public function getDebugInfo()
    {
        return array (  43 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" viewBox=\"0 0 24 24\"><path fill=\"#aaa\" d=\"M19.5 22.5H4.3a2.9 2.9 0 0 1-2.9-2.9V4.4a2.9 2.9 0 0 1 2.9-2.9h14.2a1 1 0 0 1 0 2H4.3a.9.9 0 0 0-.9.9v15.2a.9.9 0 0 0 .9.9h15.2a.9.9 0 0 0 1-.9v-8.3a1 1 0 1 1 2 0v8.3a2.9 2.9 0 0 1-3 2.9zM13 17.3L22.9 6a1.5 1.5 0 1 0-2.3-2L12 14 8 9.1A1.5 1.5 0 0 0 5.7 11l5 6.3a1.5 1.5 0 0 0 1.2.5 1.5 1.5 0 0 0 1.1-.5z\"/></svg>
", "@WebProfiler/Icon/validator.svg", "/var/www/symfony/symfony-demo/vendor/symfony/web-profiler-bundle/Resources/views/Icon/validator.svg");
    }
}
