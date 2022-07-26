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

/* blog/_delete_post_confirmation.html.twig */
class __TwigTemplate_74b9afeeea6e8cf026decc75646bb4e0 extends Template
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
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->enter($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "blog/_delete_post_confirmation.html.twig"));

        $__internal_6f47bbe9983af81f1e7450e9a3e3768f = $this->extensions["Symfony\\Bridge\\Twig\\Extension\\ProfilerExtension"];
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->enter($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "blog/_delete_post_confirmation.html.twig"));

        // line 2
        echo "<div class=\"modal fade\" id=\"confirmationModal\" tabindex=\"-1\">
    <div class=\"modal-dialog\">
        <div class=\"modal-content\">
            <div class=\"modal-body\">
                <h4>";
        // line 6
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\TranslationExtension']->trans("delete_post_modal.title"), "html", null, true);
        echo "</h4>
                <p>";
        // line 7
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\TranslationExtension']->trans("delete_post_modal.body"), "html", null, true);
        echo "</p>
            </div>
            <div class=\"modal-footer\">
                <button type=\"button\" class=\"btn btn-default\" id=\"btnNo\" data-dismiss=\"modal\">
                    <i class=\"fa fa-ban\" aria-hidden=\"true\"></i> ";
        // line 11
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\TranslationExtension']->trans("label.cancel"), "html", null, true);
        echo "
                </button>
                <button type=\"button\" class=\"btn btn-danger\" id=\"btnYes\" data-dismiss=\"modal\">
                    <i class=\"fa fa-trash\" aria-hidden=\"true\"></i> ";
        // line 14
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\TranslationExtension']->trans("label.delete_post"), "html", null, true);
        echo "
                </button>
            </div>
        </div>
    </div>
</div>
";
        
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->leave($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof);

        
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->leave($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof);

    }

    public function getTemplateName()
    {
        return "blog/_delete_post_confirmation.html.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  66 => 14,  60 => 11,  53 => 7,  49 => 6,  43 => 2,);
    }

    public function getSourceContext()
    {
        return new Source("{# Bootstrap modal, see https://getbootstrap.com/docs/3.4/javascript/#modals #}
<div class=\"modal fade\" id=\"confirmationModal\" tabindex=\"-1\">
    <div class=\"modal-dialog\">
        <div class=\"modal-content\">
            <div class=\"modal-body\">
                <h4>{{ 'delete_post_modal.title'|trans }}</h4>
                <p>{{ 'delete_post_modal.body'|trans }}</p>
            </div>
            <div class=\"modal-footer\">
                <button type=\"button\" class=\"btn btn-default\" id=\"btnNo\" data-dismiss=\"modal\">
                    <i class=\"fa fa-ban\" aria-hidden=\"true\"></i> {{ 'label.cancel'|trans }}
                </button>
                <button type=\"button\" class=\"btn btn-danger\" id=\"btnYes\" data-dismiss=\"modal\">
                    <i class=\"fa fa-trash\" aria-hidden=\"true\"></i> {{ 'label.delete_post'|trans }}
                </button>
            </div>
        </div>
    </div>
</div>
", "blog/_delete_post_confirmation.html.twig", "/var/www/symfony/symfony-demo/templates/blog/_delete_post_confirmation.html.twig");
    }
}
