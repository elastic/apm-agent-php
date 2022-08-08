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

/* @Debug/Profiler/icon.svg */
class __TwigTemplate_348d901adc8939a61e1f8111a7ebb3dc extends Template
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
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->enter($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "@Debug/Profiler/icon.svg"));

        $__internal_6f47bbe9983af81f1e7450e9a3e3768f = $this->extensions["Symfony\\Bridge\\Twig\\Extension\\ProfilerExtension"];
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->enter($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "@Debug/Profiler/icon.svg"));

        // line 1
        echo "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" viewBox=\"0 0 24 24\"><path fill=\"#AAA\" d=\"M12 22.6c-5.8 0-10.5-4.7-10.5-10.5S6.2 1.5 12 1.5 22.5 6.2 22.5 12c0 5.9-4.7 10.6-10.5 10.6zm0-18.1c-4.2 0-7.5 3.4-7.5 7.5 0 4.2 3.4 7.5 7.5 7.5s7.5-3.4 7.5-7.5-3.3-7.5-7.5-7.5z\"/><path fill=\"#AAA\" d=\"M12 9.1c-.8 0-1.5-.7-1.5-1.5v-6c0-.8.7-1.5 1.5-1.5s1.5.7 1.5 1.5v6c0 .8-.7 1.5-1.5 1.5zm1.5 13.3v-6c0-.8-.7-1.5-1.5-1.5s-1.5.7-1.5 1.5v6c0 .8.7 1.5 1.5 1.5s1.5-.7 1.5-1.5zM23.9 12c0-.8-.7-1.5-1.5-1.5h-6c-.8 0-1.5.7-1.5 1.5s.7 1.5 1.5 1.5h6c.8 0 1.5-.7 1.5-1.5zM9.1 12c0-.8-.7-1.5-1.5-1.5h-6c-.8 0-1.5.7-1.5 1.5s.7 1.5 1.5 1.5h6c.8 0 1.5-.7 1.5-1.5z\"/></svg>
";
        
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->leave($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof);

        
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->leave($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof);

    }

    public function getTemplateName()
    {
        return "@Debug/Profiler/icon.svg";
    }

    public function getDebugInfo()
    {
        return array (  43 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" viewBox=\"0 0 24 24\"><path fill=\"#AAA\" d=\"M12 22.6c-5.8 0-10.5-4.7-10.5-10.5S6.2 1.5 12 1.5 22.5 6.2 22.5 12c0 5.9-4.7 10.6-10.5 10.6zm0-18.1c-4.2 0-7.5 3.4-7.5 7.5 0 4.2 3.4 7.5 7.5 7.5s7.5-3.4 7.5-7.5-3.3-7.5-7.5-7.5z\"/><path fill=\"#AAA\" d=\"M12 9.1c-.8 0-1.5-.7-1.5-1.5v-6c0-.8.7-1.5 1.5-1.5s1.5.7 1.5 1.5v6c0 .8-.7 1.5-1.5 1.5zm1.5 13.3v-6c0-.8-.7-1.5-1.5-1.5s-1.5.7-1.5 1.5v6c0 .8.7 1.5 1.5 1.5s1.5-.7 1.5-1.5zM23.9 12c0-.8-.7-1.5-1.5-1.5h-6c-.8 0-1.5.7-1.5 1.5s.7 1.5 1.5 1.5h6c.8 0 1.5-.7 1.5-1.5zM9.1 12c0-.8-.7-1.5-1.5-1.5h-6c-.8 0-1.5.7-1.5 1.5s.7 1.5 1.5 1.5h6c.8 0 1.5-.7 1.5-1.5z\"/></svg>
", "@Debug/Profiler/icon.svg", "/var/www/symfony/symfony-demo/vendor/symfony/debug-bundle/Resources/views/Profiler/icon.svg");
    }
}
