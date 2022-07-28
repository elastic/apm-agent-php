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

/* @WebProfiler/Collector/mailer.html.twig */
class __TwigTemplate_e7aa3c044b24ab1805403830c6983509 extends Template
{
    private $source;
    private $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->blocks = [
            'toolbar' => [$this, 'block_toolbar'],
            'head' => [$this, 'block_head'],
            'menu' => [$this, 'block_menu'],
            'panel' => [$this, 'block_panel'],
        ];
    }

    protected function doGetParent(array $context)
    {
        // line 1
        return "@WebProfiler/Profiler/layout.html.twig";
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        $__internal_5a27a8ba21ca79b61932376b2fa922d2 = $this->extensions["Symfony\\Bundle\\WebProfilerBundle\\Twig\\WebProfilerExtension"];
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->enter($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "@WebProfiler/Collector/mailer.html.twig"));

        $__internal_6f47bbe9983af81f1e7450e9a3e3768f = $this->extensions["Symfony\\Bridge\\Twig\\Extension\\ProfilerExtension"];
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->enter($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "@WebProfiler/Collector/mailer.html.twig"));

        $this->parent = $this->loadTemplate("@WebProfiler/Profiler/layout.html.twig", "@WebProfiler/Collector/mailer.html.twig", 1);
        $this->parent->display($context, array_merge($this->blocks, $blocks));
        
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->leave($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof);

        
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->leave($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof);

    }

    // line 3
    public function block_toolbar($context, array $blocks = [])
    {
        $macros = $this->macros;
        $__internal_5a27a8ba21ca79b61932376b2fa922d2 = $this->extensions["Symfony\\Bundle\\WebProfilerBundle\\Twig\\WebProfilerExtension"];
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->enter($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "block", "toolbar"));

        $__internal_6f47bbe9983af81f1e7450e9a3e3768f = $this->extensions["Symfony\\Bridge\\Twig\\Extension\\ProfilerExtension"];
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->enter($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "block", "toolbar"));

        // line 4
        echo "    ";
        $context["events"] = twig_get_attribute($this->env, $this->source, (isset($context["collector"]) || array_key_exists("collector", $context) ? $context["collector"] : (function () { throw new RuntimeError('Variable "collector" does not exist.', 4, $this->source); })()), "events", [], "any", false, false, false, 4);
        // line 5
        echo "
    ";
        // line 6
        if (twig_length_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["events"]) || array_key_exists("events", $context) ? $context["events"] : (function () { throw new RuntimeError('Variable "events" does not exist.', 6, $this->source); })()), "messages", [], "any", false, false, false, 6))) {
            // line 7
            echo "        ";
            ob_start();
            // line 8
            echo "            ";
            echo twig_source($this->env, "@WebProfiler/Icon/mailer.svg");
            echo "
            <span class=\"sf-toolbar-value\">";
            // line 9
            echo twig_escape_filter($this->env, twig_length_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["events"]) || array_key_exists("events", $context) ? $context["events"] : (function () { throw new RuntimeError('Variable "events" does not exist.', 9, $this->source); })()), "messages", [], "any", false, false, false, 9)), "html", null, true);
            echo "</span>
        ";
            $context["icon"] = ('' === $tmp = ob_get_clean()) ? '' : new Markup($tmp, $this->env->getCharset());
            // line 11
            echo "
        ";
            // line 12
            ob_start();
            // line 13
            echo "            <div class=\"sf-toolbar-info-piece\">
                <b>Queued messages</b>
                <span class=\"sf-toolbar-status\">";
            // line 15
            echo twig_escape_filter($this->env, twig_length_filter($this->env, twig_array_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["events"]) || array_key_exists("events", $context) ? $context["events"] : (function () { throw new RuntimeError('Variable "events" does not exist.', 15, $this->source); })()), "events", [], "any", false, false, false, 15), function ($__e__) use ($context, $macros) { $context["e"] = $__e__; return twig_get_attribute($this->env, $this->source, (isset($context["e"]) || array_key_exists("e", $context) ? $context["e"] : (function () { throw new RuntimeError('Variable "e" does not exist.', 15, $this->source); })()), "isQueued", [], "method", false, false, false, 15); })), "html", null, true);
            echo "</span>
            </div>
            <div class=\"sf-toolbar-info-piece\">
                <b>Sent messages</b>
                <span class=\"sf-toolbar-status\">";
            // line 19
            echo twig_escape_filter($this->env, twig_length_filter($this->env, twig_array_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["events"]) || array_key_exists("events", $context) ? $context["events"] : (function () { throw new RuntimeError('Variable "events" does not exist.', 19, $this->source); })()), "events", [], "any", false, false, false, 19), function ($__e__) use ($context, $macros) { $context["e"] = $__e__; return  !twig_get_attribute($this->env, $this->source, (isset($context["e"]) || array_key_exists("e", $context) ? $context["e"] : (function () { throw new RuntimeError('Variable "e" does not exist.', 19, $this->source); })()), "isQueued", [], "method", false, false, false, 19); })), "html", null, true);
            echo "</span>
            </div>
        ";
            $context["text"] = ('' === $tmp = ob_get_clean()) ? '' : new Markup($tmp, $this->env->getCharset());
            // line 22
            echo "
        ";
            // line 23
            echo twig_include($this->env, $context, "@WebProfiler/Profiler/toolbar_item.html.twig", ["link" => (isset($context["profiler_url"]) || array_key_exists("profiler_url", $context) ? $context["profiler_url"] : (function () { throw new RuntimeError('Variable "profiler_url" does not exist.', 23, $this->source); })())]);
            echo "
    ";
        }
        
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->leave($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof);

        
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->leave($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof);

    }

    // line 27
    public function block_head($context, array $blocks = [])
    {
        $macros = $this->macros;
        $__internal_5a27a8ba21ca79b61932376b2fa922d2 = $this->extensions["Symfony\\Bundle\\WebProfilerBundle\\Twig\\WebProfilerExtension"];
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->enter($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "block", "head"));

        $__internal_6f47bbe9983af81f1e7450e9a3e3768f = $this->extensions["Symfony\\Bridge\\Twig\\Extension\\ProfilerExtension"];
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->enter($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "block", "head"));

        // line 28
        echo "    ";
        $this->displayParentBlock("head", $context, $blocks);
        echo "
    <style type=\"text/css\">
        /* utility classes */
        .m-t-0 { margin-top: 0 !important; }
        .m-t-10 { margin-top: 10px !important; }

        /* basic grid */
        .row {
            display: flex;
            flex-wrap: wrap;
            margin-right: -15px;
            margin-left: -15px;
        }
        .col {
            flex-basis: 0;
            flex-grow: 1;
            max-width: 100%;
            position: relative;
            width: 100%;
            min-height: 1px;
            padding-right: 15px;
            padding-left: 15px;
        }
        .col-4 {
            flex: 0 0 33.333333%;
            max-width: 33.333333%;
        }

        /* small tabs */
        .sf-tabs-sm .tab-navigation li {
            font-size: 14px;
            padding: .3em .5em;
        }
    </style>
";
        
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->leave($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof);

        
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->leave($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof);

    }

    // line 64
    public function block_menu($context, array $blocks = [])
    {
        $macros = $this->macros;
        $__internal_5a27a8ba21ca79b61932376b2fa922d2 = $this->extensions["Symfony\\Bundle\\WebProfilerBundle\\Twig\\WebProfilerExtension"];
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->enter($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "block", "menu"));

        $__internal_6f47bbe9983af81f1e7450e9a3e3768f = $this->extensions["Symfony\\Bridge\\Twig\\Extension\\ProfilerExtension"];
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->enter($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "block", "menu"));

        // line 65
        echo "    ";
        $context["events"] = twig_get_attribute($this->env, $this->source, (isset($context["collector"]) || array_key_exists("collector", $context) ? $context["collector"] : (function () { throw new RuntimeError('Variable "collector" does not exist.', 65, $this->source); })()), "events", [], "any", false, false, false, 65);
        // line 66
        echo "
    <span class=\"label ";
        // line 67
        echo ((twig_test_empty(twig_get_attribute($this->env, $this->source, (isset($context["events"]) || array_key_exists("events", $context) ? $context["events"] : (function () { throw new RuntimeError('Variable "events" does not exist.', 67, $this->source); })()), "messages", [], "any", false, false, false, 67))) ? ("disabled") : (""));
        echo "\">
        <span class=\"icon\">";
        // line 68
        echo twig_source($this->env, "@WebProfiler/Icon/mailer.svg");
        echo "</span>

        <strong>E-mails</strong>
        ";
        // line 71
        if ((twig_length_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["events"]) || array_key_exists("events", $context) ? $context["events"] : (function () { throw new RuntimeError('Variable "events" does not exist.', 71, $this->source); })()), "messages", [], "any", false, false, false, 71)) > 0)) {
            // line 72
            echo "            <span class=\"count\">
                <span>";
            // line 73
            echo twig_escape_filter($this->env, twig_length_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["events"]) || array_key_exists("events", $context) ? $context["events"] : (function () { throw new RuntimeError('Variable "events" does not exist.', 73, $this->source); })()), "messages", [], "any", false, false, false, 73)), "html", null, true);
            echo "</span>
            </span>
        ";
        }
        // line 76
        echo "    </span>
";
        
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->leave($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof);

        
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->leave($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof);

    }

    // line 79
    public function block_panel($context, array $blocks = [])
    {
        $macros = $this->macros;
        $__internal_5a27a8ba21ca79b61932376b2fa922d2 = $this->extensions["Symfony\\Bundle\\WebProfilerBundle\\Twig\\WebProfilerExtension"];
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->enter($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "block", "panel"));

        $__internal_6f47bbe9983af81f1e7450e9a3e3768f = $this->extensions["Symfony\\Bridge\\Twig\\Extension\\ProfilerExtension"];
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->enter($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "block", "panel"));

        // line 80
        echo "    ";
        $context["events"] = twig_get_attribute($this->env, $this->source, (isset($context["collector"]) || array_key_exists("collector", $context) ? $context["collector"] : (function () { throw new RuntimeError('Variable "collector" does not exist.', 80, $this->source); })()), "events", [], "any", false, false, false, 80);
        // line 81
        echo "
    <h2>Emails</h2>

    ";
        // line 84
        if ( !twig_length_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["events"]) || array_key_exists("events", $context) ? $context["events"] : (function () { throw new RuntimeError('Variable "events" does not exist.', 84, $this->source); })()), "messages", [], "any", false, false, false, 84))) {
            // line 85
            echo "        <div class=\"empty\">
            <p>No emails were sent.</p>
        </div>
    ";
        }
        // line 89
        echo "
    <div class=\"metrics\">
        <div class=\"metric\">
            <span class=\"value\">";
        // line 92
        echo twig_escape_filter($this->env, twig_length_filter($this->env, twig_array_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["events"]) || array_key_exists("events", $context) ? $context["events"] : (function () { throw new RuntimeError('Variable "events" does not exist.', 92, $this->source); })()), "events", [], "any", false, false, false, 92), function ($__e__) use ($context, $macros) { $context["e"] = $__e__; return twig_get_attribute($this->env, $this->source, (isset($context["e"]) || array_key_exists("e", $context) ? $context["e"] : (function () { throw new RuntimeError('Variable "e" does not exist.', 92, $this->source); })()), "isQueued", [], "method", false, false, false, 92); })), "html", null, true);
        echo "</span>
            <span class=\"label\">Queued</span>
        </div>

        <div class=\"metric\">
            <span class=\"value\">";
        // line 97
        echo twig_escape_filter($this->env, twig_length_filter($this->env, twig_array_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["events"]) || array_key_exists("events", $context) ? $context["events"] : (function () { throw new RuntimeError('Variable "events" does not exist.', 97, $this->source); })()), "events", [], "any", false, false, false, 97), function ($__e__) use ($context, $macros) { $context["e"] = $__e__; return  !twig_get_attribute($this->env, $this->source, (isset($context["e"]) || array_key_exists("e", $context) ? $context["e"] : (function () { throw new RuntimeError('Variable "e" does not exist.', 97, $this->source); })()), "isQueued", [], "method", false, false, false, 97); })), "html", null, true);
        echo "</span>
            <span class=\"label\">Sent</span>
        </div>
    </div>

    ";
        // line 102
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, (isset($context["events"]) || array_key_exists("events", $context) ? $context["events"] : (function () { throw new RuntimeError('Variable "events" does not exist.', 102, $this->source); })()), "transports", [], "any", false, false, false, 102));
        foreach ($context['_seq'] as $context["_key"] => $context["transport"]) {
            // line 103
            echo "        <div class=\"card-block\">
            <div class=\"sf-tabs sf-tabs-sm\">
                ";
            // line 105
            $context['_parent'] = $context;
            $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, (isset($context["events"]) || array_key_exists("events", $context) ? $context["events"] : (function () { throw new RuntimeError('Variable "events" does not exist.', 105, $this->source); })()), "events", [0 => $context["transport"]], "method", false, false, false, 105));
            foreach ($context['_seq'] as $context["_key"] => $context["event"]) {
                // line 106
                echo "                    ";
                $context["message"] = twig_get_attribute($this->env, $this->source, $context["event"], "message", [], "any", false, false, false, 106);
                // line 107
                echo "                    <div class=\"tab\">
                        <h3 class=\"tab-title\">Email ";
                // line 108
                ((twig_get_attribute($this->env, $this->source, $context["event"], "isQueued", [], "method", false, false, false, 108)) ? (print ("queued")) : (print (twig_escape_filter($this->env, ("sent via " . $context["transport"]), "html", null, true))));
                echo "</h3>
                        <div class=\"tab-content\">
                            <div class=\"card\">
                                ";
                // line 111
                if ( !twig_get_attribute($this->env, $this->source, ($context["message"] ?? null), "headers", [], "any", true, true, false, 111)) {
                    // line 112
                    echo "                                    ";
                    // line 113
                    echo "                                    <div class=\"card-block\">
                                        <pre class=\"prewrap\" style=\"max-height: 600px\">";
                    // line 114
                    echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["message"]) || array_key_exists("message", $context) ? $context["message"] : (function () { throw new RuntimeError('Variable "message" does not exist.', 114, $this->source); })()), "toString", [], "method", false, false, false, 114), "html", null, true);
                    echo "</pre>
                                    </div>
                                ";
                } else {
                    // line 117
                    echo "                                    ";
                    // line 118
                    echo "                                    <div class=\"card-block\">
                                        <div class=\"sf-tabs sf-tabs-sm\">
                                            <div class=\"tab\">
                                                <h3 class=\"tab-title\">Headers</h3>
                                                <div class=\"tab-content\">
                                                    <span class=\"label\">Subject</span>
                                                    <h2 class=\"m-t-10\">";
                    // line 124
                    (((twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["message"] ?? null), "headers", [], "any", false, true, false, 124), "get", [0 => "subject"], "method", false, true, false, 124), "bodyAsString", [], "method", true, true, false, 124) &&  !(null === twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["message"] ?? null), "headers", [], "any", false, true, false, 124), "get", [0 => "subject"], "method", false, true, false, 124), "bodyAsString", [], "method", false, false, false, 124)))) ? (print (twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["message"] ?? null), "headers", [], "any", false, true, false, 124), "get", [0 => "subject"], "method", false, true, false, 124), "bodyAsString", [], "method", false, false, false, 124), "html", null, true))) : (print ("(empty)")));
                    echo "</h2>
                                                    <div class=\"row\">
                                                        <div class=\"col col-4\">
                                                            <span class=\"label\">From</span>
                                                            <pre class=\"prewrap\">";
                    // line 128
                    echo twig_escape_filter($this->env, twig_replace_filter((((twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["message"] ?? null), "headers", [], "any", false, true, false, 128), "get", [0 => "from"], "method", false, true, false, 128), "bodyAsString", [], "method", true, true, false, 128) &&  !(null === twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["message"] ?? null), "headers", [], "any", false, true, false, 128), "get", [0 => "from"], "method", false, true, false, 128), "bodyAsString", [], "method", false, false, false, 128)))) ? (twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["message"] ?? null), "headers", [], "any", false, true, false, 128), "get", [0 => "from"], "method", false, true, false, 128), "bodyAsString", [], "method", false, false, false, 128)) : ("(empty)")), ["From:" => ""]), "html", null, true);
                    echo "</pre>

                                                            <span class=\"label\">To</span>
                                                            <pre class=\"prewrap\">";
                    // line 131
                    echo twig_escape_filter($this->env, twig_replace_filter((((twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["message"] ?? null), "headers", [], "any", false, true, false, 131), "get", [0 => "to"], "method", false, true, false, 131), "bodyAsString", [], "method", true, true, false, 131) &&  !(null === twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["message"] ?? null), "headers", [], "any", false, true, false, 131), "get", [0 => "to"], "method", false, true, false, 131), "bodyAsString", [], "method", false, false, false, 131)))) ? (twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["message"] ?? null), "headers", [], "any", false, true, false, 131), "get", [0 => "to"], "method", false, true, false, 131), "bodyAsString", [], "method", false, false, false, 131)) : ("(empty)")), ["To:" => ""]), "html", null, true);
                    echo "</pre>

                                                            ";
                    // line 133
                    if ((twig_length_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["event"], "envelope", [], "any", false, false, false, 133), "recipients", [], "any", false, false, false, 133)) > 0)) {
                        // line 134
                        echo "                                                                <span class=\"label\">Recipients</span>
                                                                ";
                        // line 135
                        $context['_parent'] = $context;
                        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["event"], "envelope", [], "any", false, false, false, 135), "recipients", [], "any", false, false, false, 135));
                        foreach ($context['_seq'] as $context["_key"] => $context["recipient"]) {
                            // line 136
                            echo "                                                                    <pre class=\"prewrap\">";
                            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["recipient"], "address", [], "any", false, false, false, 136), "html", null, true);
                            echo "</pre>
                                                                ";
                        }
                        $_parent = $context['_parent'];
                        unset($context['_seq'], $context['_iterated'], $context['_key'], $context['recipient'], $context['_parent'], $context['loop']);
                        $context = array_intersect_key($context, $_parent) + $_parent;
                        // line 138
                        echo "                                                            ";
                    }
                    // line 139
                    echo "                                                        </div>
                                                        <div class=\"col\">
                                                            <span class=\"label\">Headers</span>
                                                            <pre class=\"prewrap\">";
                    // line 142
                    $context['_parent'] = $context;
                    $context['_seq'] = twig_ensure_traversable(twig_array_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, (isset($context["message"]) || array_key_exists("message", $context) ? $context["message"] : (function () { throw new RuntimeError('Variable "message" does not exist.', 142, $this->source); })()), "headers", [], "any", false, false, false, 142), "all", [], "any", false, false, false, 142), function ($__header__) use ($context, $macros) { $context["header"] = $__header__; return !twig_in_filter((((twig_get_attribute($this->env, $this->source, $context["header"], "name", [], "any", true, true, false, 142) &&  !(null === twig_get_attribute($this->env, $this->source, $context["header"], "name", [], "any", false, false, false, 142)))) ? (twig_get_attribute($this->env, $this->source, $context["header"], "name", [], "any", false, false, false, 142)) : ("")), [0 => "Subject", 1 => "From", 2 => "To"]); }));
                    foreach ($context['_seq'] as $context["_key"] => $context["header"]) {
                        // line 143
                        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["header"], "toString", [], "any", false, false, false, 143), "html", null, true);
                        echo "
";
                    }
                    $_parent = $context['_parent'];
                    unset($context['_seq'], $context['_iterated'], $context['_key'], $context['header'], $context['_parent'], $context['loop']);
                    $context = array_intersect_key($context, $_parent) + $_parent;
                    // line 144
                    echo "</pre>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            ";
                    // line 149
                    if (twig_get_attribute($this->env, $this->source, ($context["message"] ?? null), "htmlBody", [], "any", true, true, false, 149)) {
                        // line 150
                        echo "                                                ";
                        // line 151
                        echo "                                                ";
                        $context["htmlBody"] = twig_get_attribute($this->env, $this->source, (isset($context["message"]) || array_key_exists("message", $context) ? $context["message"] : (function () { throw new RuntimeError('Variable "message" does not exist.', 151, $this->source); })()), "htmlBody", [], "method", false, false, false, 151);
                        // line 152
                        echo "                                                ";
                        if ( !(null === (isset($context["htmlBody"]) || array_key_exists("htmlBody", $context) ? $context["htmlBody"] : (function () { throw new RuntimeError('Variable "htmlBody" does not exist.', 152, $this->source); })()))) {
                            // line 153
                            echo "                                                    <div class=\"tab\">
                                                        <h3 class=\"tab-title\">HTML Preview</h3>
                                                        <div class=\"tab-content\">
                                                            <pre class=\"prewrap\" style=\"max-height: 600px\">
                                                                <iframe
                                                                    src=\"data:text/html;charset=utf-8;base64,";
                            // line 158
                            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["collector"]) || array_key_exists("collector", $context) ? $context["collector"] : (function () { throw new RuntimeError('Variable "collector" does not exist.', 158, $this->source); })()), "base64Encode", [0 => (isset($context["htmlBody"]) || array_key_exists("htmlBody", $context) ? $context["htmlBody"] : (function () { throw new RuntimeError('Variable "htmlBody" does not exist.', 158, $this->source); })())], "method", false, false, false, 158), "html", null, true);
                            echo "\"
                                                                    style=\"height: 80vh;width: 100%;\"
                                                                >
                                                                </iframe>
                                                            </pre>
                                                        </div>
                                                    </div>
                                                    <div class=\"tab\">
                                                        <h3 class=\"tab-title\">HTML Content</h3>
                                                        <div class=\"tab-content\">
                                                            <pre class=\"prewrap\" style=\"max-height: 600px\">";
                            // line 169
                            if (twig_get_attribute($this->env, $this->source, (isset($context["message"]) || array_key_exists("message", $context) ? $context["message"] : (function () { throw new RuntimeError('Variable "message" does not exist.', 169, $this->source); })()), "htmlCharset", [], "method", false, false, false, 169)) {
                                // line 170
                                echo twig_escape_filter($this->env, twig_convert_encoding((isset($context["htmlBody"]) || array_key_exists("htmlBody", $context) ? $context["htmlBody"] : (function () { throw new RuntimeError('Variable "htmlBody" does not exist.', 170, $this->source); })()), "UTF-8", twig_get_attribute($this->env, $this->source, (isset($context["message"]) || array_key_exists("message", $context) ? $context["message"] : (function () { throw new RuntimeError('Variable "message" does not exist.', 170, $this->source); })()), "htmlCharset", [], "method", false, false, false, 170)), "html", null, true);
                            } else {
                                // line 172
                                echo twig_escape_filter($this->env, (isset($context["htmlBody"]) || array_key_exists("htmlBody", $context) ? $context["htmlBody"] : (function () { throw new RuntimeError('Variable "htmlBody" does not exist.', 172, $this->source); })()), "html", null, true);
                            }
                            // line 174
                            echo "</pre>
                                                        </div>
                                                    </div>
                                                ";
                        }
                        // line 178
                        echo "                                                ";
                        $context["textBody"] = twig_get_attribute($this->env, $this->source, (isset($context["message"]) || array_key_exists("message", $context) ? $context["message"] : (function () { throw new RuntimeError('Variable "message" does not exist.', 178, $this->source); })()), "textBody", [], "method", false, false, false, 178);
                        // line 179
                        echo "                                                ";
                        if ( !(null === (isset($context["textBody"]) || array_key_exists("textBody", $context) ? $context["textBody"] : (function () { throw new RuntimeError('Variable "textBody" does not exist.', 179, $this->source); })()))) {
                            // line 180
                            echo "                                                    <div class=\"tab\">
                                                        <h3 class=\"tab-title\">Text Content</h3>
                                                        <div class=\"tab-content\">
                                                            <pre class=\"prewrap\" style=\"max-height: 600px\">";
                            // line 184
                            if (twig_get_attribute($this->env, $this->source, (isset($context["message"]) || array_key_exists("message", $context) ? $context["message"] : (function () { throw new RuntimeError('Variable "message" does not exist.', 184, $this->source); })()), "textCharset", [], "method", false, false, false, 184)) {
                                // line 185
                                echo twig_escape_filter($this->env, twig_convert_encoding((isset($context["textBody"]) || array_key_exists("textBody", $context) ? $context["textBody"] : (function () { throw new RuntimeError('Variable "textBody" does not exist.', 185, $this->source); })()), "UTF-8", twig_get_attribute($this->env, $this->source, (isset($context["message"]) || array_key_exists("message", $context) ? $context["message"] : (function () { throw new RuntimeError('Variable "message" does not exist.', 185, $this->source); })()), "textCharset", [], "method", false, false, false, 185)), "html", null, true);
                            } else {
                                // line 187
                                echo twig_escape_filter($this->env, (isset($context["textBody"]) || array_key_exists("textBody", $context) ? $context["textBody"] : (function () { throw new RuntimeError('Variable "textBody" does not exist.', 187, $this->source); })()), "html", null, true);
                            }
                            // line 189
                            echo "</pre>
                                                        </div>
                                                    </div>
                                                ";
                        }
                        // line 193
                        echo "                                                ";
                        $context['_parent'] = $context;
                        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, (isset($context["message"]) || array_key_exists("message", $context) ? $context["message"] : (function () { throw new RuntimeError('Variable "message" does not exist.', 193, $this->source); })()), "attachments", [], "any", false, false, false, 193));
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
                        foreach ($context['_seq'] as $context["_key"] => $context["attachment"]) {
                            // line 194
                            echo "                                                    <div class=\"tab\">
                                                        <h3 class=\"tab-title\">Attachment #";
                            // line 195
                            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["loop"], "index", [], "any", false, false, false, 195), "html", null, true);
                            echo "</h3>
                                                        <div class=\"tab-content\">
                                                            <p>
                                                                <a href=\"data:";
                            // line 198
                            echo twig_escape_filter($this->env, ((twig_get_attribute($this->env, $this->source, $context["attachment"], "contentType", [], "any", true, true, false, 198)) ? (_twig_default_filter(twig_get_attribute($this->env, $this->source, $context["attachment"], "contentType", [], "any", false, false, false, 198), "application/octet-stream")) : ("application/octet-stream")), "html", null, true);
                            echo ";base64,";
                            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["collector"]) || array_key_exists("collector", $context) ? $context["collector"] : (function () { throw new RuntimeError('Variable "collector" does not exist.', 198, $this->source); })()), "base64Encode", [0 => twig_get_attribute($this->env, $this->source, $context["attachment"], "body", [], "any", false, false, false, 198)], "method", false, false, false, 198), "html", null, true);
                            echo "\" download=\"";
                            echo twig_escape_filter($this->env, ((twig_get_attribute($this->env, $this->source, $context["attachment"], "filename", [], "any", true, true, false, 198)) ? (_twig_default_filter(twig_get_attribute($this->env, $this->source, $context["attachment"], "filename", [], "any", false, false, false, 198), "attachment")) : ("attachment")), "html", null, true);
                            echo "\">
                                                                    ";
                            // line 199
                            if (((twig_get_attribute($this->env, $this->source, $context["attachment"], "filename", [], "any", true, true, false, 199)) ? (_twig_default_filter(twig_get_attribute($this->env, $this->source, $context["attachment"], "filename", [], "any", false, false, false, 199))) : (""))) {
                                // line 200
                                echo "                                                                        ";
                                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["attachment"], "filename", [], "any", false, false, false, 200), "html", null, true);
                                echo "
                                                                    ";
                            } else {
                                // line 202
                                echo "                                                                        <em>(no filename)</em>
                                                                    ";
                            }
                            // line 204
                            echo "                                                                </a>
                                                            </p>
                                                            <pre class=\"prewrap\" style=\"max-height: 600px\">";
                            // line 206
                            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["attachment"], "toString", [], "method", false, false, false, 206), "html", null, true);
                            echo "</pre>
                                                        </div>
                                                    </div>
                                                ";
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
                        unset($context['_seq'], $context['_iterated'], $context['_key'], $context['attachment'], $context['_parent'], $context['loop']);
                        $context = array_intersect_key($context, $_parent) + $_parent;
                        // line 210
                        echo "                                            ";
                    }
                    // line 211
                    echo "                                            <div class=\"tab\">
                                                <h3 class=\"tab-title\">Parts Hierarchy</h3>
                                                <div class=\"tab-content\">
                                                    <pre class=\"prewrap\" style=\"max-height: 600px\">";
                    // line 214
                    echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, (isset($context["message"]) || array_key_exists("message", $context) ? $context["message"] : (function () { throw new RuntimeError('Variable "message" does not exist.', 214, $this->source); })()), "body", [], "method", false, false, false, 214), "asDebugString", [], "method", false, false, false, 214), "html", null, true);
                    echo "</pre>
                                                </div>
                                            </div>
                                            <div class=\"tab\">
                                                <h3 class=\"tab-title\">Raw</h3>
                                                <div class=\"tab-content\">
                                                    <pre class=\"prewrap\" style=\"max-height: 600px\">";
                    // line 220
                    echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["message"]) || array_key_exists("message", $context) ? $context["message"] : (function () { throw new RuntimeError('Variable "message" does not exist.', 220, $this->source); })()), "toString", [], "method", false, false, false, 220), "html", null, true);
                    echo "</pre>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                ";
                }
                // line 226
                echo "                            </div>
                        </div>
                    </div>
                ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['_key'], $context['event'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 230
            echo "            </div>
        </div>
    ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['_key'], $context['transport'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->leave($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof);

        
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->leave($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof);

    }

    public function getTemplateName()
    {
        return "@WebProfiler/Collector/mailer.html.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  564 => 230,  555 => 226,  546 => 220,  537 => 214,  532 => 211,  529 => 210,  511 => 206,  507 => 204,  503 => 202,  497 => 200,  495 => 199,  487 => 198,  481 => 195,  478 => 194,  460 => 193,  454 => 189,  451 => 187,  448 => 185,  446 => 184,  441 => 180,  438 => 179,  435 => 178,  429 => 174,  426 => 172,  423 => 170,  421 => 169,  408 => 158,  401 => 153,  398 => 152,  395 => 151,  393 => 150,  391 => 149,  384 => 144,  376 => 143,  372 => 142,  367 => 139,  364 => 138,  355 => 136,  351 => 135,  348 => 134,  346 => 133,  341 => 131,  335 => 128,  328 => 124,  320 => 118,  318 => 117,  312 => 114,  309 => 113,  307 => 112,  305 => 111,  299 => 108,  296 => 107,  293 => 106,  289 => 105,  285 => 103,  281 => 102,  273 => 97,  265 => 92,  260 => 89,  254 => 85,  252 => 84,  247 => 81,  244 => 80,  234 => 79,  223 => 76,  217 => 73,  214 => 72,  212 => 71,  206 => 68,  202 => 67,  199 => 66,  196 => 65,  186 => 64,  140 => 28,  130 => 27,  117 => 23,  114 => 22,  108 => 19,  101 => 15,  97 => 13,  95 => 12,  92 => 11,  87 => 9,  82 => 8,  79 => 7,  77 => 6,  74 => 5,  71 => 4,  61 => 3,  38 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends '@WebProfiler/Profiler/layout.html.twig' %}

{% block toolbar %}
    {% set events = collector.events %}

    {% if events.messages|length %}
        {% set icon %}
            {{ source('@WebProfiler/Icon/mailer.svg') }}
            <span class=\"sf-toolbar-value\">{{ events.messages|length }}</span>
        {% endset %}

        {% set text %}
            <div class=\"sf-toolbar-info-piece\">
                <b>Queued messages</b>
                <span class=\"sf-toolbar-status\">{{ events.events|filter(e => e.isQueued())|length }}</span>
            </div>
            <div class=\"sf-toolbar-info-piece\">
                <b>Sent messages</b>
                <span class=\"sf-toolbar-status\">{{ events.events|filter(e => not e.isQueued())|length }}</span>
            </div>
        {% endset %}

        {{ include('@WebProfiler/Profiler/toolbar_item.html.twig', { 'link': profiler_url }) }}
    {% endif %}
{% endblock %}

{% block head %}
    {{ parent() }}
    <style type=\"text/css\">
        /* utility classes */
        .m-t-0 { margin-top: 0 !important; }
        .m-t-10 { margin-top: 10px !important; }

        /* basic grid */
        .row {
            display: flex;
            flex-wrap: wrap;
            margin-right: -15px;
            margin-left: -15px;
        }
        .col {
            flex-basis: 0;
            flex-grow: 1;
            max-width: 100%;
            position: relative;
            width: 100%;
            min-height: 1px;
            padding-right: 15px;
            padding-left: 15px;
        }
        .col-4 {
            flex: 0 0 33.333333%;
            max-width: 33.333333%;
        }

        /* small tabs */
        .sf-tabs-sm .tab-navigation li {
            font-size: 14px;
            padding: .3em .5em;
        }
    </style>
{% endblock %}

{% block menu %}
    {% set events = collector.events %}

    <span class=\"label {{ events.messages is empty ? 'disabled' }}\">
        <span class=\"icon\">{{ source('@WebProfiler/Icon/mailer.svg') }}</span>

        <strong>E-mails</strong>
        {% if events.messages|length > 0 %}
            <span class=\"count\">
                <span>{{ events.messages|length }}</span>
            </span>
        {% endif %}
    </span>
{% endblock %}

{% block panel %}
    {% set events = collector.events %}

    <h2>Emails</h2>

    {% if not events.messages|length %}
        <div class=\"empty\">
            <p>No emails were sent.</p>
        </div>
    {% endif %}

    <div class=\"metrics\">
        <div class=\"metric\">
            <span class=\"value\">{{ events.events|filter(e => e.isQueued())|length }}</span>
            <span class=\"label\">Queued</span>
        </div>

        <div class=\"metric\">
            <span class=\"value\">{{ events.events|filter(e => not e.isQueued())|length }}</span>
            <span class=\"label\">Sent</span>
        </div>
    </div>

    {% for transport in events.transports %}
        <div class=\"card-block\">
            <div class=\"sf-tabs sf-tabs-sm\">
                {% for event in events.events(transport) %}
                    {% set message = event.message %}
                    <div class=\"tab\">
                        <h3 class=\"tab-title\">Email {{ event.isQueued() ? 'queued' : 'sent via ' ~ transport }}</h3>
                        <div class=\"tab-content\">
                            <div class=\"card\">
                                {% if message.headers is not defined %}
                                    {# RawMessage instance #}
                                    <div class=\"card-block\">
                                        <pre class=\"prewrap\" style=\"max-height: 600px\">{{ message.toString() }}</pre>
                                    </div>
                                {% else %}
                                    {# Message instance #}
                                    <div class=\"card-block\">
                                        <div class=\"sf-tabs sf-tabs-sm\">
                                            <div class=\"tab\">
                                                <h3 class=\"tab-title\">Headers</h3>
                                                <div class=\"tab-content\">
                                                    <span class=\"label\">Subject</span>
                                                    <h2 class=\"m-t-10\">{{ message.headers.get('subject').bodyAsString() ?? '(empty)' }}</h2>
                                                    <div class=\"row\">
                                                        <div class=\"col col-4\">
                                                            <span class=\"label\">From</span>
                                                            <pre class=\"prewrap\">{{ (message.headers.get('from').bodyAsString() ?? '(empty)')|replace({'From:': ''}) }}</pre>

                                                            <span class=\"label\">To</span>
                                                            <pre class=\"prewrap\">{{ (message.headers.get('to').bodyAsString() ?? '(empty)')|replace({'To:': ''}) }}</pre>

                                                            {% if event.envelope.recipients|length > 0 %}
                                                                <span class=\"label\">Recipients</span>
                                                                {% for recipient in event.envelope.recipients %}
                                                                    <pre class=\"prewrap\">{{ recipient.address }}</pre>
                                                                {% endfor %}
                                                            {% endif %}
                                                        </div>
                                                        <div class=\"col\">
                                                            <span class=\"label\">Headers</span>
                                                            <pre class=\"prewrap\">{% for header in message.headers.all|filter(header => (header.name ?? '') not in ['Subject', 'From', 'To']) %}
                                                                {{- header.toString }}
                                                            {%~ endfor %}</pre>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            {% if message.htmlBody is defined %}
                                                {# Email instance #}
                                                {% set htmlBody = message.htmlBody() %}
                                                {% if htmlBody is not null %}
                                                    <div class=\"tab\">
                                                        <h3 class=\"tab-title\">HTML Preview</h3>
                                                        <div class=\"tab-content\">
                                                            <pre class=\"prewrap\" style=\"max-height: 600px\">
                                                                <iframe
                                                                    src=\"data:text/html;charset=utf-8;base64,{{ collector.base64Encode(htmlBody) }}\"
                                                                    style=\"height: 80vh;width: 100%;\"
                                                                >
                                                                </iframe>
                                                            </pre>
                                                        </div>
                                                    </div>
                                                    <div class=\"tab\">
                                                        <h3 class=\"tab-title\">HTML Content</h3>
                                                        <div class=\"tab-content\">
                                                            <pre class=\"prewrap\" style=\"max-height: 600px\">
                                                                {%- if message.htmlCharset() %}
                                                                    {{- htmlBody|convert_encoding('UTF-8', message.htmlCharset()) }}
                                                                {%- else %}
                                                                    {{- htmlBody }}
                                                                {%- endif -%}
                                                            </pre>
                                                        </div>
                                                    </div>
                                                {% endif %}
                                                {% set textBody = message.textBody() %}
                                                {% if textBody is not null %}
                                                    <div class=\"tab\">
                                                        <h3 class=\"tab-title\">Text Content</h3>
                                                        <div class=\"tab-content\">
                                                            <pre class=\"prewrap\" style=\"max-height: 600px\">
                                                                {%- if message.textCharset() %}
                                                                    {{- textBody|convert_encoding('UTF-8', message.textCharset()) }}
                                                                {%- else %}
                                                                    {{- textBody }}
                                                                {%- endif -%}
                                                            </pre>
                                                        </div>
                                                    </div>
                                                {% endif %}
                                                {% for attachment in message.attachments %}
                                                    <div class=\"tab\">
                                                        <h3 class=\"tab-title\">Attachment #{{ loop.index }}</h3>
                                                        <div class=\"tab-content\">
                                                            <p>
                                                                <a href=\"data:{{ attachment.contentType|default('application/octet-stream') }};base64,{{ collector.base64Encode(attachment.body) }}\" download=\"{{ attachment.filename|default('attachment') }}\">
                                                                    {% if attachment.filename|default %}
                                                                        {{ attachment.filename }}
                                                                    {% else %}
                                                                        <em>(no filename)</em>
                                                                    {% endif %}
                                                                </a>
                                                            </p>
                                                            <pre class=\"prewrap\" style=\"max-height: 600px\">{{ attachment.toString() }}</pre>
                                                        </div>
                                                    </div>
                                                {% endfor %}
                                            {% endif %}
                                            <div class=\"tab\">
                                                <h3 class=\"tab-title\">Parts Hierarchy</h3>
                                                <div class=\"tab-content\">
                                                    <pre class=\"prewrap\" style=\"max-height: 600px\">{{ message.body().asDebugString() }}</pre>
                                                </div>
                                            </div>
                                            <div class=\"tab\">
                                                <h3 class=\"tab-title\">Raw</h3>
                                                <div class=\"tab-content\">
                                                    <pre class=\"prewrap\" style=\"max-height: 600px\">{{ message.toString() }}</pre>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                {% endif %}
                            </div>
                        </div>
                    </div>
                {% endfor %}
            </div>
        </div>
    {% endfor %}
{% endblock %}
", "@WebProfiler/Collector/mailer.html.twig", "/var/www/symfony/symfony-demo/vendor/symfony/web-profiler-bundle/Resources/views/Collector/mailer.html.twig");
    }
}
