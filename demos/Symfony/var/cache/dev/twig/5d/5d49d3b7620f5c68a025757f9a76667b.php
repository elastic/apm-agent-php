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

/* @WebProfiler/Icon/notifier.svg */
class __TwigTemplate_967fdcafd34ffd37c90befbeb253cc45 extends Template
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
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->enter($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "@WebProfiler/Icon/notifier.svg"));

        $__internal_6f47bbe9983af81f1e7450e9a3e3768f = $this->extensions["Symfony\\Bridge\\Twig\\Extension\\ProfilerExtension"];
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->enter($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "@WebProfiler/Icon/notifier.svg"));

        // line 1
        echo "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" viewBox=\"0 0 24 24\"><path fill=\"#AAA\" d=\"M11.23,4.8c-3,.83-5.07,3.18-5.07,5.9H7.39c0-2.21,1.77-4,4.17-4.72ZM7.49,0A12.22,12.22,0,0,0,0,11.59H2.07A10.14,10.14,0,0,1,8.23,2Zm8.24,2a10.14,10.14,0,0,1,6.16,9.64H24A12.24,12.24,0,0,0,16.47,0ZM4.41,15.64V10.7a7.57,7.57,0,0,1,15.14,0v4.94l3.3,4.4H1.11Zm4.45,5.3A3.06,3.06,0,0,0,11.92,24H12a3.07,3.07,0,0,0,3.07-3.06Z\"/></svg>
";
        
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->leave($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof);

        
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->leave($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof);

    }

    public function getTemplateName()
    {
        return "@WebProfiler/Icon/notifier.svg";
    }

    public function getDebugInfo()
    {
        return array (  43 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" viewBox=\"0 0 24 24\"><path fill=\"#AAA\" d=\"M11.23,4.8c-3,.83-5.07,3.18-5.07,5.9H7.39c0-2.21,1.77-4,4.17-4.72ZM7.49,0A12.22,12.22,0,0,0,0,11.59H2.07A10.14,10.14,0,0,1,8.23,2Zm8.24,2a10.14,10.14,0,0,1,6.16,9.64H24A12.24,12.24,0,0,0,16.47,0ZM4.41,15.64V10.7a7.57,7.57,0,0,1,15.14,0v4.94l3.3,4.4H1.11Zm4.45,5.3A3.06,3.06,0,0,0,11.92,24H12a3.07,3.07,0,0,0,3.07-3.06Z\"/></svg>
", "@WebProfiler/Icon/notifier.svg", "/var/www/symfony/symfony-demo/vendor/symfony/web-profiler-bundle/Resources/views/Icon/notifier.svg");
    }
}
