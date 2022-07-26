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

/* @WebProfiler/Profiler/toolbar_js.html.twig */
class __TwigTemplate_1b867dbf76e4e782c17b489d918a29c1 extends Template
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
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->enter($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "@WebProfiler/Profiler/toolbar_js.html.twig"));

        $__internal_6f47bbe9983af81f1e7450e9a3e3768f = $this->extensions["Symfony\\Bridge\\Twig\\Extension\\ProfilerExtension"];
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->enter($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "@WebProfiler/Profiler/toolbar_js.html.twig"));

        // line 1
        echo "<div id=\"sfwdt";
        echo twig_escape_filter($this->env, (isset($context["token"]) || array_key_exists("token", $context) ? $context["token"] : (function () { throw new RuntimeError('Variable "token" does not exist.', 1, $this->source); })()), "html", null, true);
        echo "\" class=\"sf-toolbar sf-display-none\" role=\"region\" aria-label=\"Symfony Web Debug Toolbar\">
    ";
        // line 2
        echo twig_include($this->env, $context, "@WebProfiler/Profiler/toolbar.html.twig", ["templates" => ["request" => "@WebProfiler/Profiler/cancel.html.twig"], "profile" => null, "profiler_url" => $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getUrl("_profiler", ["token" =>         // line 7
(isset($context["token"]) || array_key_exists("token", $context) ? $context["token"] : (function () { throw new RuntimeError('Variable "token" does not exist.', 7, $this->source); })())]), "profiler_markup_version" => 2]);
        // line 9
        echo "
</div>

";
        // line 12
        echo twig_include($this->env, $context, "@WebProfiler/Profiler/base_js.html.twig");
        echo "

<style";
        // line 14
        if ((isset($context["csp_style_nonce"]) || array_key_exists("csp_style_nonce", $context) ? $context["csp_style_nonce"] : (function () { throw new RuntimeError('Variable "csp_style_nonce" does not exist.', 14, $this->source); })())) {
            echo " nonce=\"";
            echo twig_escape_filter($this->env, (isset($context["csp_style_nonce"]) || array_key_exists("csp_style_nonce", $context) ? $context["csp_style_nonce"] : (function () { throw new RuntimeError('Variable "csp_style_nonce" does not exist.', 14, $this->source); })()), "html", null, true);
            echo "\"";
        }
        echo ">
    ";
        // line 15
        echo twig_include($this->env, $context, "@WebProfiler/Profiler/toolbar.css.twig");
        echo "
</style>
<script";
        // line 17
        if ((isset($context["csp_script_nonce"]) || array_key_exists("csp_script_nonce", $context) ? $context["csp_script_nonce"] : (function () { throw new RuntimeError('Variable "csp_script_nonce" does not exist.', 17, $this->source); })())) {
            echo " nonce=\"";
            echo twig_escape_filter($this->env, (isset($context["csp_script_nonce"]) || array_key_exists("csp_script_nonce", $context) ? $context["csp_script_nonce"] : (function () { throw new RuntimeError('Variable "csp_script_nonce" does not exist.', 17, $this->source); })()), "html", null, true);
            echo "\"";
        }
        echo ">/*<![CDATA[*/
    (function () {
        Sfjs.loadToolbar('";
        // line 19
        echo twig_escape_filter($this->env, (isset($context["token"]) || array_key_exists("token", $context) ? $context["token"] : (function () { throw new RuntimeError('Variable "token" does not exist.', 19, $this->source); })()), "html", null, true);
        echo "');
    })();
/*]]>*/</script>
";
        
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->leave($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof);

        
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->leave($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof);

    }

    public function getTemplateName()
    {
        return "@WebProfiler/Profiler/toolbar_js.html.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  83 => 19,  74 => 17,  69 => 15,  61 => 14,  56 => 12,  51 => 9,  49 => 7,  48 => 2,  43 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("<div id=\"sfwdt{{ token }}\" class=\"sf-toolbar sf-display-none\" role=\"region\" aria-label=\"Symfony Web Debug Toolbar\">
    {{ include('@WebProfiler/Profiler/toolbar.html.twig', {
        templates: {
            'request': '@WebProfiler/Profiler/cancel.html.twig'
        },
        profile: null,
        profiler_url: url('_profiler', {token: token}),
        profiler_markup_version: 2,
    }) }}
</div>

{{ include('@WebProfiler/Profiler/base_js.html.twig') }}

<style{% if csp_style_nonce %} nonce=\"{{ csp_style_nonce }}\"{% endif %}>
    {{ include('@WebProfiler/Profiler/toolbar.css.twig') }}
</style>
<script{% if csp_script_nonce %} nonce=\"{{ csp_script_nonce }}\"{% endif %}>/*<![CDATA[*/
    (function () {
        Sfjs.loadToolbar('{{ token }}');
    })();
/*]]>*/</script>
", "@WebProfiler/Profiler/toolbar_js.html.twig", "/var/www/symfony/symfony-demo/vendor/symfony/web-profiler-bundle/Resources/views/Profiler/toolbar_js.html.twig");
    }
}
