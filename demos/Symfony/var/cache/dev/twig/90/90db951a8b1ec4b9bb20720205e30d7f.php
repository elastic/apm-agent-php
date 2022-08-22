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

/* @WebProfiler/Profiler/toolbar.html.twig */
class __TwigTemplate_a2a3988efb327b066e85c58b06acdb57 extends Template
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
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->enter($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "@WebProfiler/Profiler/toolbar.html.twig"));

        $__internal_6f47bbe9983af81f1e7450e9a3e3768f = $this->extensions["Symfony\\Bridge\\Twig\\Extension\\ProfilerExtension"];
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->enter($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "@WebProfiler/Profiler/toolbar.html.twig"));

        // line 1
        echo "<!-- START of Symfony Web Debug Toolbar -->
<div id=\"sfMiniToolbar-";
        // line 2
        echo twig_escape_filter($this->env, (isset($context["token"]) || array_key_exists("token", $context) ? $context["token"] : (function () { throw new RuntimeError('Variable "token" does not exist.', 2, $this->source); })()), "html", null, true);
        echo "\" class=\"sf-minitoolbar\" data-no-turbolink>
    <button type=\"button\" title=\"Show Symfony toolbar\" id=\"sfToolbarMiniToggler-";
        // line 3
        echo twig_escape_filter($this->env, (isset($context["token"]) || array_key_exists("token", $context) ? $context["token"] : (function () { throw new RuntimeError('Variable "token" does not exist.', 3, $this->source); })()), "html", null, true);
        echo "\" accesskey=\"D\" aria-expanded=\"false\" aria-controls=\"sfToolbarMainContent-";
        echo twig_escape_filter($this->env, (isset($context["token"]) || array_key_exists("token", $context) ? $context["token"] : (function () { throw new RuntimeError('Variable "token" does not exist.', 3, $this->source); })()), "html", null, true);
        echo "\">
        ";
        // line 4
        echo twig_source($this->env, "@WebProfiler/Icon/symfony.svg");
        echo "
    </button>
</div>
<div id=\"sfToolbarClearer-";
        // line 7
        echo twig_escape_filter($this->env, (isset($context["token"]) || array_key_exists("token", $context) ? $context["token"] : (function () { throw new RuntimeError('Variable "token" does not exist.', 7, $this->source); })()), "html", null, true);
        echo "\" class=\"sf-toolbar-clearer\"></div>

<div id=\"sfToolbarMainContent-";
        // line 9
        echo twig_escape_filter($this->env, (isset($context["token"]) || array_key_exists("token", $context) ? $context["token"] : (function () { throw new RuntimeError('Variable "token" does not exist.', 9, $this->source); })()), "html", null, true);
        echo "\" class=\"sf-toolbarreset clear-fix\" data-no-turbolink>
    ";
        // line 10
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable((isset($context["templates"]) || array_key_exists("templates", $context) ? $context["templates"] : (function () { throw new RuntimeError('Variable "templates" does not exist.', 10, $this->source); })()));
        $context['loop'] = [
          'parent' => $context['_parent'],
          'index0' => 0,
          'index'  => 1,
          'first'  => true,
        ];
        if (is_array($context['_seq']) || (is_object($context['_seq']) && $context['_seq'] instanceof \Countable)) {
            $length = count($context['_seq']);
            $context['loop']['revindex0'] = $length - 1;
            $context['loop']['revindex'] = $length;
            $context['loop']['length'] = $length;
            $context['loop']['last'] = 1 === $length;
        }
        foreach ($context['_seq'] as $context["name"] => $context["template"]) {
            // line 11
            echo "        ";
            if (            $this->loadTemplate($context["template"], "@WebProfiler/Profiler/toolbar.html.twig", 11)->hasBlock("toolbar", $context)) {
                // line 12
                echo "            ";
                $__internal_compile_0 = $context;
                $__internal_compile_1 = ["collector" => ((                // line 13
(isset($context["profile"]) || array_key_exists("profile", $context) ? $context["profile"] : (function () { throw new RuntimeError('Variable "profile" does not exist.', 13, $this->source); })())) ? (twig_get_attribute($this->env, $this->source, (isset($context["profile"]) || array_key_exists("profile", $context) ? $context["profile"] : (function () { throw new RuntimeError('Variable "profile" does not exist.', 13, $this->source); })()), "getcollector", [0 => $context["name"]], "method", false, false, false, 13)) : (null)), "profiler_url" =>                 // line 14
(isset($context["profiler_url"]) || array_key_exists("profiler_url", $context) ? $context["profiler_url"] : (function () { throw new RuntimeError('Variable "profiler_url" does not exist.', 14, $this->source); })()), "token" => ((                // line 15
$context["token"]) ?? ((((isset($context["profile"]) || array_key_exists("profile", $context) ? $context["profile"] : (function () { throw new RuntimeError('Variable "profile" does not exist.', 15, $this->source); })())) ? (twig_get_attribute($this->env, $this->source, (isset($context["profile"]) || array_key_exists("profile", $context) ? $context["profile"] : (function () { throw new RuntimeError('Variable "profile" does not exist.', 15, $this->source); })()), "token", [], "any", false, false, false, 15)) : (null)))), "name" =>                 // line 16
$context["name"], "profiler_markup_version" =>                 // line 17
(isset($context["profiler_markup_version"]) || array_key_exists("profiler_markup_version", $context) ? $context["profiler_markup_version"] : (function () { throw new RuntimeError('Variable "profiler_markup_version" does not exist.', 17, $this->source); })()), "csp_script_nonce" =>                 // line 18
(isset($context["csp_script_nonce"]) || array_key_exists("csp_script_nonce", $context) ? $context["csp_script_nonce"] : (function () { throw new RuntimeError('Variable "csp_script_nonce" does not exist.', 18, $this->source); })()), "csp_style_nonce" =>                 // line 19
(isset($context["csp_style_nonce"]) || array_key_exists("csp_style_nonce", $context) ? $context["csp_style_nonce"] : (function () { throw new RuntimeError('Variable "csp_style_nonce" does not exist.', 19, $this->source); })())];
                if (!twig_test_iterable($__internal_compile_1)) {
                    throw new RuntimeError('Variables passed to the "with" tag must be a hash.', 13, $this->getSourceContext());
                }
                $__internal_compile_1 = twig_to_array($__internal_compile_1);
                $context = $this->env->mergeGlobals(array_merge($context, $__internal_compile_1));
                // line 21
                echo "                ";
                $this->loadTemplate($context["template"], "@WebProfiler/Profiler/toolbar.html.twig", 21)->displayBlock("toolbar", $context);
                echo "
            ";
                $context = $__internal_compile_0;
                // line 23
                echo "        ";
            }
            // line 24
            echo "    ";
            ++$context['loop']['index0'];
            ++$context['loop']['index'];
            $context['loop']['first'] = false;
            if (isset($context['loop']['length'])) {
                --$context['loop']['revindex0'];
                --$context['loop']['revindex'];
                $context['loop']['last'] = 0 === $context['loop']['revindex0'];
            }
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['name'], $context['template'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 25
        echo "    ";
        if ((isset($context["full_stack"]) || array_key_exists("full_stack", $context) ? $context["full_stack"] : (function () { throw new RuntimeError('Variable "full_stack" does not exist.', 25, $this->source); })())) {
            // line 26
            echo "        <div class=\"sf-full-stack sf-toolbar-block sf-toolbar-block-full-stack sf-toolbar-status-red sf-toolbar-block-right\">
            <div class=\"sf-toolbar-icon\">
                <span class=\"sf-toolbar-value\">Using symfony/symfony is NOT supported</span>
            </div>
            <div class=\"sf-toolbar-info sf-toolbar-status-red\">
                <p>This project is using Symfony via the \"symfony/symfony\" package.</p>
                <p>This is NOT supported anymore since Symfony 4.0.</p>
                <p>Even if it seems to work well, it has some important limitations with no workarounds.</p>
                <p>Using this package also makes your project slower.</p>

                <strong>Please, stop using this package and replace it with individual packages instead.</strong>
            </div>
            <div></div>
        </div>
    ";
        }
        // line 41
        echo "
    <button class=\"hide-button\" type=\"button\" id=\"sfToolbarHideButton-";
        // line 42
        echo twig_escape_filter($this->env, (isset($context["token"]) || array_key_exists("token", $context) ? $context["token"] : (function () { throw new RuntimeError('Variable "token" does not exist.', 42, $this->source); })()), "html", null, true);
        echo "\" title=\"Close Toolbar\" accesskey=\"D\" aria-expanded=\"true\" aria-controls=\"sfToolbarMainContent-";
        echo twig_escape_filter($this->env, (isset($context["token"]) || array_key_exists("token", $context) ? $context["token"] : (function () { throw new RuntimeError('Variable "token" does not exist.', 42, $this->source); })()), "html", null, true);
        echo "\">
        ";
        // line 43
        echo twig_source($this->env, "@WebProfiler/Icon/close.svg");
        echo "
    </button>
</div>
<!-- END of Symfony Web Debug Toolbar -->
";
        
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->leave($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof);

        
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->leave($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof);

    }

    public function getTemplateName()
    {
        return "@WebProfiler/Profiler/toolbar.html.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  159 => 43,  153 => 42,  150 => 41,  133 => 26,  130 => 25,  116 => 24,  113 => 23,  107 => 21,  100 => 19,  99 => 18,  98 => 17,  97 => 16,  96 => 15,  95 => 14,  94 => 13,  91 => 12,  88 => 11,  71 => 10,  67 => 9,  62 => 7,  56 => 4,  50 => 3,  46 => 2,  43 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("<!-- START of Symfony Web Debug Toolbar -->
<div id=\"sfMiniToolbar-{{ token }}\" class=\"sf-minitoolbar\" data-no-turbolink>
    <button type=\"button\" title=\"Show Symfony toolbar\" id=\"sfToolbarMiniToggler-{{ token }}\" accesskey=\"D\" aria-expanded=\"false\" aria-controls=\"sfToolbarMainContent-{{ token }}\">
        {{ source('@WebProfiler/Icon/symfony.svg') }}
    </button>
</div>
<div id=\"sfToolbarClearer-{{ token }}\" class=\"sf-toolbar-clearer\"></div>

<div id=\"sfToolbarMainContent-{{ token }}\" class=\"sf-toolbarreset clear-fix\" data-no-turbolink>
    {% for name, template in templates %}
        {% if block('toolbar', template) is defined %}
            {% with {
                collector: profile ? profile.getcollector(name) : null,
                profiler_url: profiler_url,
                token: token ?? (profile ? profile.token : null),
                name: name,
                profiler_markup_version: profiler_markup_version,
                csp_script_nonce: csp_script_nonce,
                csp_style_nonce: csp_style_nonce
              } %}
                {{ block('toolbar', template) }}
            {% endwith %}
        {% endif %}
    {% endfor %}
    {% if full_stack %}
        <div class=\"sf-full-stack sf-toolbar-block sf-toolbar-block-full-stack sf-toolbar-status-red sf-toolbar-block-right\">
            <div class=\"sf-toolbar-icon\">
                <span class=\"sf-toolbar-value\">Using symfony/symfony is NOT supported</span>
            </div>
            <div class=\"sf-toolbar-info sf-toolbar-status-red\">
                <p>This project is using Symfony via the \"symfony/symfony\" package.</p>
                <p>This is NOT supported anymore since Symfony 4.0.</p>
                <p>Even if it seems to work well, it has some important limitations with no workarounds.</p>
                <p>Using this package also makes your project slower.</p>

                <strong>Please, stop using this package and replace it with individual packages instead.</strong>
            </div>
            <div></div>
        </div>
    {% endif %}

    <button class=\"hide-button\" type=\"button\" id=\"sfToolbarHideButton-{{ token }}\" title=\"Close Toolbar\" accesskey=\"D\" aria-expanded=\"true\" aria-controls=\"sfToolbarMainContent-{{ token }}\">
        {{ source('@WebProfiler/Icon/close.svg') }}
    </button>
</div>
<!-- END of Symfony Web Debug Toolbar -->
", "@WebProfiler/Profiler/toolbar.html.twig", "/var/www/symfony/symfony-demo/vendor/symfony/web-profiler-bundle/Resources/views/Profiler/toolbar.html.twig");
    }
}
