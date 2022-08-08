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

/* @WebProfiler/Icon/cache.svg */
class __TwigTemplate_cd83ce6fa9d2337f711e41369a2273cb extends Template
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
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->enter($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "@WebProfiler/Icon/cache.svg"));

        $__internal_6f47bbe9983af81f1e7450e9a3e3768f = $this->extensions["Symfony\\Bridge\\Twig\\Extension\\ProfilerExtension"];
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->enter($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "@WebProfiler/Icon/cache.svg"));

        // line 1
        echo "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" viewBox=\"0 0 24 24\"><path fill=\"#AAA\" d=\"M2.3 6l9-4.6a1.5 1.5 0 0 1 1.4 0l9 4.7a1.5 1.5 0 0 1 0 2.6l-9 4.7a1.5 1.5 0 0 1-1.4 0l-9-4.7a1.5 1.5 0 0 1 0-2.6zm18.3 5L12 15.4 3.4 11a1.4 1.4 0 0 0-1.2 2.4l9.2 4.8a1.4 1.4 0 0 0 1.2 0l9.2-4.8a1.4 1.4 0 0 0-1.3-2.4zm0 4.5L12 19.9l-8.6-4.4a1.4 1.4 0 0 0-1.2 2.4l9.2 4.7a1.4 1.4 0 0 0 1.2 0l9.2-4.7a1.4 1.4 0 0 0-1.3-2.5z\"/></svg>
";
        
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->leave($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof);

        
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->leave($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof);

    }

    public function getTemplateName()
    {
        return "@WebProfiler/Icon/cache.svg";
    }

    public function getDebugInfo()
    {
        return array (  43 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" viewBox=\"0 0 24 24\"><path fill=\"#AAA\" d=\"M2.3 6l9-4.6a1.5 1.5 0 0 1 1.4 0l9 4.7a1.5 1.5 0 0 1 0 2.6l-9 4.7a1.5 1.5 0 0 1-1.4 0l-9-4.7a1.5 1.5 0 0 1 0-2.6zm18.3 5L12 15.4 3.4 11a1.4 1.4 0 0 0-1.2 2.4l9.2 4.8a1.4 1.4 0 0 0 1.2 0l9.2-4.8a1.4 1.4 0 0 0-1.3-2.4zm0 4.5L12 19.9l-8.6-4.4a1.4 1.4 0 0 0-1.2 2.4l9.2 4.7a1.4 1.4 0 0 0 1.2 0l9.2-4.7a1.4 1.4 0 0 0-1.3-2.5z\"/></svg>
", "@WebProfiler/Icon/cache.svg", "/var/www/symfony/symfony-demo/vendor/symfony/web-profiler-bundle/Resources/views/Icon/cache.svg");
    }
}
