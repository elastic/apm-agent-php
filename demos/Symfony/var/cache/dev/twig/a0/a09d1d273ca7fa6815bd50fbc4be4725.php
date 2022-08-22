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

/* @WebProfiler/Icon/translation.svg */
class __TwigTemplate_417728ce9ee33ea4712687b282640e50 extends Template
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
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->enter($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "@WebProfiler/Icon/translation.svg"));

        $__internal_6f47bbe9983af81f1e7450e9a3e3768f = $this->extensions["Symfony\\Bridge\\Twig\\Extension\\ProfilerExtension"];
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->enter($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "@WebProfiler/Icon/translation.svg"));

        // line 1
        echo "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" viewBox=\"0 0 24 24\"><path fill=\"#AAA\" d=\"M5.4 6H7v.3c0 1.2-.7 1.9-1.7 1.9-1.1 0-1.4-.4-1.4-1.1 0-.9.6-1.1 1.5-1.1zm3.8-6H2.7C1.2 0 0 .9 0 2.4v6.5C0 10.4 1.2 11 2.7 11h1.2l3.3 3.2c.4.2.8.3.8-.1V9.9l.1-1.1h-.4c-.3.1-.7-.1-.7-.4v-.5c0 .7-1 1-1.8 1-1.5 0-2.4-.7-2.4-2s1.1-2 2.6-2H7v-.4c0-1-.4-1.6-1.5-1.6-.7 0-1.1.2-1.5.7l-.3.2a.4.4 0 0 1-.4-.4l.1-.2c.4-.7 1-1.2 2.2-1.2C7.2 2 8 3 8 4.5v3c1-1.4 1.8-2.4 4-2.4V2.4C12 .9 10.7 0 9.2 0zm11.5 6h-8C10.8 6 9 7.2 9 9v8c0 1.8 2 3.3 4 3.3v3c0 .5.5.7.9.3l4-3.7h2.7c1.8 0 3.3-1.2 3.3-3V9c.1-1.8-1.4-3-3.2-3zM13 9h7v.8s-.7.3-1.1.3h-4.8a10 10 0 0 1-1.1-.4V9zm-.5 7.9l-.6-.5c1-.8 1.8-2.1 2.2-3.4l.7.3c-.6 1.5-1.4 2.6-2.3 3.6zM17 12v4.8c0 .7-.2.8-1.2.8l-1.4-.1-.2-.7 1.4.1c.4 0 .4 0 .4-.3V12h-2.9l-1.1.1v-.8s.7-.4 1.1-.4h6.8c.5 0 1.1.4 1.1.4v.8l-1.1-.1H17zm3.7 4.8c-1-1.1-1.6-1.9-2.3-3.6l.6-.2a9 9 0 0 0 2.2 3.3l-.5.5z\"/></svg>
";
        
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->leave($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof);

        
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->leave($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof);

    }

    public function getTemplateName()
    {
        return "@WebProfiler/Icon/translation.svg";
    }

    public function getDebugInfo()
    {
        return array (  43 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" viewBox=\"0 0 24 24\"><path fill=\"#AAA\" d=\"M5.4 6H7v.3c0 1.2-.7 1.9-1.7 1.9-1.1 0-1.4-.4-1.4-1.1 0-.9.6-1.1 1.5-1.1zm3.8-6H2.7C1.2 0 0 .9 0 2.4v6.5C0 10.4 1.2 11 2.7 11h1.2l3.3 3.2c.4.2.8.3.8-.1V9.9l.1-1.1h-.4c-.3.1-.7-.1-.7-.4v-.5c0 .7-1 1-1.8 1-1.5 0-2.4-.7-2.4-2s1.1-2 2.6-2H7v-.4c0-1-.4-1.6-1.5-1.6-.7 0-1.1.2-1.5.7l-.3.2a.4.4 0 0 1-.4-.4l.1-.2c.4-.7 1-1.2 2.2-1.2C7.2 2 8 3 8 4.5v3c1-1.4 1.8-2.4 4-2.4V2.4C12 .9 10.7 0 9.2 0zm11.5 6h-8C10.8 6 9 7.2 9 9v8c0 1.8 2 3.3 4 3.3v3c0 .5.5.7.9.3l4-3.7h2.7c1.8 0 3.3-1.2 3.3-3V9c.1-1.8-1.4-3-3.2-3zM13 9h7v.8s-.7.3-1.1.3h-4.8a10 10 0 0 1-1.1-.4V9zm-.5 7.9l-.6-.5c1-.8 1.8-2.1 2.2-3.4l.7.3c-.6 1.5-1.4 2.6-2.3 3.6zM17 12v4.8c0 .7-.2.8-1.2.8l-1.4-.1-.2-.7 1.4.1c.4 0 .4 0 .4-.3V12h-2.9l-1.1.1v-.8s.7-.4 1.1-.4h6.8c.5 0 1.1.4 1.1.4v.8l-1.1-.1H17zm3.7 4.8c-1-1.1-1.6-1.9-2.3-3.6l.6-.2a9 9 0 0 0 2.2 3.3l-.5.5z\"/></svg>
", "@WebProfiler/Icon/translation.svg", "/var/www/symfony/symfony-demo/vendor/symfony/web-profiler-bundle/Resources/views/Icon/translation.svg");
    }
}
