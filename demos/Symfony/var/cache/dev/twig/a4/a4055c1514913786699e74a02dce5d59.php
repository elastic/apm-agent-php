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

/* @WebProfiler/Icon/router.svg */
class __TwigTemplate_1ec2b31ad39c84bf4ad1f4749b85bcf8 extends Template
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
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->enter($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "@WebProfiler/Icon/router.svg"));

        $__internal_6f47bbe9983af81f1e7450e9a3e3768f = $this->extensions["Symfony\\Bridge\\Twig\\Extension\\ProfilerExtension"];
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->enter($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "@WebProfiler/Icon/router.svg"));

        // line 1
        echo "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" viewBox=\"0 0 24 24\"><path fill=\"#AAA\" d=\"M13 3v18c0 1.1-.9 2-2 2s-2-.9-2-2V3c0-1.1.9-2 2-2s2 .9 2 2zm10.2 1.6l-1.8-1.4c-.2-.3-.6-.2-1-.2H14v5h6.4c.4 0 .8-.3 1.1-.5l1.8-1.6c.3-.3.3-1-.1-1.3zm-3.7 4.8c-.3-.3-.7-.4-1.1-.4H14v5h4.4a2 2 0 0 0 1.1-.3l1.8-1.5c.4-.3.4-.9 0-1.3l-1.8-1.5zM3.5 7c-.4 0-.7 0-1 .3L.7 8.8c-.4.3-.4.9 0 1.3l1.8 1.6c.3.2.6.3 1 .3H8V7H3.5z\"/></svg>
";
        
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->leave($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof);

        
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->leave($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof);

    }

    public function getTemplateName()
    {
        return "@WebProfiler/Icon/router.svg";
    }

    public function getDebugInfo()
    {
        return array (  43 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" viewBox=\"0 0 24 24\"><path fill=\"#AAA\" d=\"M13 3v18c0 1.1-.9 2-2 2s-2-.9-2-2V3c0-1.1.9-2 2-2s2 .9 2 2zm10.2 1.6l-1.8-1.4c-.2-.3-.6-.2-1-.2H14v5h6.4c.4 0 .8-.3 1.1-.5l1.8-1.6c.3-.3.3-1-.1-1.3zm-3.7 4.8c-.3-.3-.7-.4-1.1-.4H14v5h4.4a2 2 0 0 0 1.1-.3l1.8-1.5c.4-.3.4-.9 0-1.3l-1.8-1.5zM3.5 7c-.4 0-.7 0-1 .3L.7 8.8c-.4.3-.4.9 0 1.3l1.8 1.6c.3.2.6.3 1 .3H8V7H3.5z\"/></svg>
", "@WebProfiler/Icon/router.svg", "/var/www/symfony/symfony-demo/vendor/symfony/web-profiler-bundle/Resources/views/Icon/router.svg");
    }
}
