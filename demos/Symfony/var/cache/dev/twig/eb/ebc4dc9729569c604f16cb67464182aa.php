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

/* @WebProfiler/Icon/logger.svg */
class __TwigTemplate_3d308fc5bcad8024e3ab8ef5e9f0cba5 extends Template
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
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->enter($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "@WebProfiler/Icon/logger.svg"));

        $__internal_6f47bbe9983af81f1e7450e9a3e3768f = $this->extensions["Symfony\\Bridge\\Twig\\Extension\\ProfilerExtension"];
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->enter($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "@WebProfiler/Icon/logger.svg"));

        // line 1
        echo "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" viewBox=\"0 0 24 24\"><path fill=\"#AAA\" d=\"M21 4v13.8c0 2.7-2.5 5.2-5.2 5.2H6c-.6 0-1-.4-1-1s.4-1 1-1h9.8c1.6 0 3.2-1.7 3.2-3.2V4c0-.6.4-1 1-1s1 .4 1 1zM5.5 20A2.5 2.5 0 0 1 3 17.5v-14C3 2.1 4.1 1 5.5 1h10.1C16.9 1 18 2.1 18 3.5v14.1c0 1.4-1.1 2.5-2.5 2.5h-10zM9 11.4c0 .3.3.6.6.6h1.8c.3 0 .6-.3.6-.6V4.6c0-.3-.3-.6-.6-.6H9.6c-.3 0-.6.3-.6.6v6.8zm0 5c0 .3.3.6.6.6h1.8c.3 0 .6-.3.6-.6v-1.8c0-.3-.3-.6-.6-.6H9.6c-.3 0-.6.3-.6.6v1.8z\"/></svg>
";
        
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->leave($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof);

        
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->leave($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof);

    }

    public function getTemplateName()
    {
        return "@WebProfiler/Icon/logger.svg";
    }

    public function getDebugInfo()
    {
        return array (  43 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" viewBox=\"0 0 24 24\"><path fill=\"#AAA\" d=\"M21 4v13.8c0 2.7-2.5 5.2-5.2 5.2H6c-.6 0-1-.4-1-1s.4-1 1-1h9.8c1.6 0 3.2-1.7 3.2-3.2V4c0-.6.4-1 1-1s1 .4 1 1zM5.5 20A2.5 2.5 0 0 1 3 17.5v-14C3 2.1 4.1 1 5.5 1h10.1C16.9 1 18 2.1 18 3.5v14.1c0 1.4-1.1 2.5-2.5 2.5h-10zM9 11.4c0 .3.3.6.6.6h1.8c.3 0 .6-.3.6-.6V4.6c0-.3-.3-.6-.6-.6H9.6c-.3 0-.6.3-.6.6v6.8zm0 5c0 .3.3.6.6.6h1.8c.3 0 .6-.3.6-.6v-1.8c0-.3-.3-.6-.6-.6H9.6c-.3 0-.6.3-.6.6v1.8z\"/></svg>
", "@WebProfiler/Icon/logger.svg", "/var/www/symfony/symfony-demo/vendor/symfony/web-profiler-bundle/Resources/views/Icon/logger.svg");
    }
}
