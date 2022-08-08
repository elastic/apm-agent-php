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

/* @WebProfiler/Collector/http_client.html.twig */
class __TwigTemplate_1c9f47683e0e5b7e11ec5c630b6d55ab extends Template
{
    private $source;
    private $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->blocks = [
            'toolbar' => [$this, 'block_toolbar'],
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
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->enter($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "@WebProfiler/Collector/http_client.html.twig"));

        $__internal_6f47bbe9983af81f1e7450e9a3e3768f = $this->extensions["Symfony\\Bridge\\Twig\\Extension\\ProfilerExtension"];
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->enter($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "@WebProfiler/Collector/http_client.html.twig"));

        $this->parent = $this->loadTemplate("@WebProfiler/Profiler/layout.html.twig", "@WebProfiler/Collector/http_client.html.twig", 1);
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
        if (twig_get_attribute($this->env, $this->source, (isset($context["collector"]) || array_key_exists("collector", $context) ? $context["collector"] : (function () { throw new RuntimeError('Variable "collector" does not exist.', 4, $this->source); })()), "requestCount", [], "any", false, false, false, 4)) {
            // line 5
            echo "        ";
            ob_start();
            // line 6
            echo "            ";
            echo twig_source($this->env, "@WebProfiler/Icon/http-client.svg");
            echo "
            ";
            // line 7
            $context["status_color"] = "";
            // line 8
            echo "            <span class=\"sf-toolbar-value\">";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["collector"]) || array_key_exists("collector", $context) ? $context["collector"] : (function () { throw new RuntimeError('Variable "collector" does not exist.', 8, $this->source); })()), "requestCount", [], "any", false, false, false, 8), "html", null, true);
            echo "</span>
        ";
            $context["icon"] = ('' === $tmp = ob_get_clean()) ? '' : new Markup($tmp, $this->env->getCharset());
            // line 10
            echo "
        ";
            // line 11
            ob_start();
            // line 12
            echo "            <div class=\"sf-toolbar-info-piece\">
                <b>Total requests</b>
                <span>";
            // line 14
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["collector"]) || array_key_exists("collector", $context) ? $context["collector"] : (function () { throw new RuntimeError('Variable "collector" does not exist.', 14, $this->source); })()), "requestCount", [], "any", false, false, false, 14), "html", null, true);
            echo "</span>
            </div>
            <div class=\"sf-toolbar-info-piece\">
                <b>HTTP errors</b>
                <span class=\"sf-toolbar-status ";
            // line 18
            echo (((twig_get_attribute($this->env, $this->source, (isset($context["collector"]) || array_key_exists("collector", $context) ? $context["collector"] : (function () { throw new RuntimeError('Variable "collector" does not exist.', 18, $this->source); })()), "errorCount", [], "any", false, false, false, 18) > 0)) ? ("sf-toolbar-status-red") : (""));
            echo "\">";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["collector"]) || array_key_exists("collector", $context) ? $context["collector"] : (function () { throw new RuntimeError('Variable "collector" does not exist.', 18, $this->source); })()), "errorCount", [], "any", false, false, false, 18), "html", null, true);
            echo "</span>
            </div>
        ";
            $context["text"] = ('' === $tmp = ob_get_clean()) ? '' : new Markup($tmp, $this->env->getCharset());
            // line 21
            echo "
        ";
            // line 22
            echo twig_include($this->env, $context, "@WebProfiler/Profiler/toolbar_item.html.twig", ["link" => (isset($context["profiler_url"]) || array_key_exists("profiler_url", $context) ? $context["profiler_url"] : (function () { throw new RuntimeError('Variable "profiler_url" does not exist.', 22, $this->source); })()), "status" => (isset($context["status_color"]) || array_key_exists("status_color", $context) ? $context["status_color"] : (function () { throw new RuntimeError('Variable "status_color" does not exist.', 22, $this->source); })())]);
            echo "
    ";
        }
        
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->leave($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof);

        
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->leave($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof);

    }

    // line 26
    public function block_menu($context, array $blocks = [])
    {
        $macros = $this->macros;
        $__internal_5a27a8ba21ca79b61932376b2fa922d2 = $this->extensions["Symfony\\Bundle\\WebProfilerBundle\\Twig\\WebProfilerExtension"];
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->enter($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "block", "menu"));

        $__internal_6f47bbe9983af81f1e7450e9a3e3768f = $this->extensions["Symfony\\Bridge\\Twig\\Extension\\ProfilerExtension"];
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->enter($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "block", "menu"));

        // line 27
        echo "<span class=\"label ";
        echo (((twig_get_attribute($this->env, $this->source, (isset($context["collector"]) || array_key_exists("collector", $context) ? $context["collector"] : (function () { throw new RuntimeError('Variable "collector" does not exist.', 27, $this->source); })()), "requestCount", [], "any", false, false, false, 27) == 0)) ? ("disabled") : (""));
        echo "\">
    <span class=\"icon\">";
        // line 28
        echo twig_source($this->env, "@WebProfiler/Icon/http-client.svg");
        echo "</span>
    <strong>HTTP Client</strong>
    ";
        // line 30
        if (twig_get_attribute($this->env, $this->source, (isset($context["collector"]) || array_key_exists("collector", $context) ? $context["collector"] : (function () { throw new RuntimeError('Variable "collector" does not exist.', 30, $this->source); })()), "requestCount", [], "any", false, false, false, 30)) {
            // line 31
            echo "        <span class=\"count\">
            ";
            // line 32
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["collector"]) || array_key_exists("collector", $context) ? $context["collector"] : (function () { throw new RuntimeError('Variable "collector" does not exist.', 32, $this->source); })()), "requestCount", [], "any", false, false, false, 32), "html", null, true);
            echo "
        </span>
    ";
        }
        // line 35
        echo "</span>
";
        
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->leave($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof);

        
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->leave($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof);

    }

    // line 38
    public function block_panel($context, array $blocks = [])
    {
        $macros = $this->macros;
        $__internal_5a27a8ba21ca79b61932376b2fa922d2 = $this->extensions["Symfony\\Bundle\\WebProfilerBundle\\Twig\\WebProfilerExtension"];
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->enter($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "block", "panel"));

        $__internal_6f47bbe9983af81f1e7450e9a3e3768f = $this->extensions["Symfony\\Bridge\\Twig\\Extension\\ProfilerExtension"];
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->enter($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "block", "panel"));

        // line 39
        echo "    <h2>HTTP Client</h2>
    ";
        // line 40
        if ((twig_get_attribute($this->env, $this->source, (isset($context["collector"]) || array_key_exists("collector", $context) ? $context["collector"] : (function () { throw new RuntimeError('Variable "collector" does not exist.', 40, $this->source); })()), "requestCount", [], "any", false, false, false, 40) == 0)) {
            // line 41
            echo "        <div class=\"empty\">
            <p>No HTTP requests were made.</p>
        </div>
    ";
        } else {
            // line 45
            echo "        <div class=\"metrics\">
            <div class=\"metric\">
                <span class=\"value\">";
            // line 47
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["collector"]) || array_key_exists("collector", $context) ? $context["collector"] : (function () { throw new RuntimeError('Variable "collector" does not exist.', 47, $this->source); })()), "requestCount", [], "any", false, false, false, 47), "html", null, true);
            echo "</span>
                <span class=\"label\">Total requests</span>
            </div>
            <div class=\"metric\">
                <span class=\"value\">";
            // line 51
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["collector"]) || array_key_exists("collector", $context) ? $context["collector"] : (function () { throw new RuntimeError('Variable "collector" does not exist.', 51, $this->source); })()), "errorCount", [], "any", false, false, false, 51), "html", null, true);
            echo "</span>
                <span class=\"label\">HTTP errors</span>
            </div>
        </div>
        <h2>Clients</h2>
        <div class=\"sf-tabs\">
            ";
            // line 57
            $context['_parent'] = $context;
            $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, (isset($context["collector"]) || array_key_exists("collector", $context) ? $context["collector"] : (function () { throw new RuntimeError('Variable "collector" does not exist.', 57, $this->source); })()), "clients", [], "any", false, false, false, 57));
            foreach ($context['_seq'] as $context["name"] => $context["client"]) {
                // line 58
                echo "                <div class=\"tab ";
                echo (((twig_length_filter($this->env, twig_get_attribute($this->env, $this->source, $context["client"], "traces", [], "any", false, false, false, 58)) == 0)) ? ("disabled") : (""));
                echo "\">
                    <h3 class=\"tab-title\">";
                // line 59
                echo twig_escape_filter($this->env, $context["name"], "html", null, true);
                echo " <span class=\"badge\">";
                echo twig_escape_filter($this->env, twig_length_filter($this->env, twig_get_attribute($this->env, $this->source, $context["client"], "traces", [], "any", false, false, false, 59)), "html", null, true);
                echo "</span></h3>
                    <div class=\"tab-content\">
                        ";
                // line 61
                if ((twig_length_filter($this->env, twig_get_attribute($this->env, $this->source, $context["client"], "traces", [], "any", false, false, false, 61)) == 0)) {
                    // line 62
                    echo "                            <div class=\"empty\">
                                <p>No requests were made with the \"";
                    // line 63
                    echo twig_escape_filter($this->env, $context["name"], "html", null, true);
                    echo "\" service.</p>
                            </div>
                        ";
                } else {
                    // line 66
                    echo "                            <h4>Requests</h4>
                            ";
                    // line 67
                    $context['_parent'] = $context;
                    $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, $context["client"], "traces", [], "any", false, false, false, 67));
                    foreach ($context['_seq'] as $context["_key"] => $context["trace"]) {
                        // line 68
                        echo "                                ";
                        $context["profiler_token"] = "";
                        // line 69
                        echo "                                ";
                        $context["profiler_link"] = "";
                        // line 70
                        echo "                                ";
                        if (twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["trace"], "info", [], "any", false, true, false, 70), "response_headers", [], "any", true, true, false, 70)) {
                            // line 71
                            echo "                                    ";
                            $context['_parent'] = $context;
                            $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["trace"], "info", [], "any", false, false, false, 71), "response_headers", [], "any", false, false, false, 71));
                            foreach ($context['_seq'] as $context["_key"] => $context["header"]) {
                                // line 72
                                echo "                                        ";
                                if (preg_match("/^x-debug-token: .*\$/i", $context["header"])) {
                                    // line 73
                                    echo "                                            ";
                                    $context["profiler_token"] = twig_slice($this->env, twig_get_attribute($this->env, $this->source, $context["header"], "getValue", [], "any", false, false, false, 73), twig_length_filter($this->env, "x-debug-token: "));
                                    // line 74
                                    echo "                                        ";
                                }
                                // line 75
                                echo "                                        ";
                                if (preg_match("/^x-debug-token-link: .*\$/i", $context["header"])) {
                                    // line 76
                                    echo "                                            ";
                                    $context["profiler_link"] = twig_slice($this->env, twig_get_attribute($this->env, $this->source, $context["header"], "getValue", [], "any", false, false, false, 76), twig_length_filter($this->env, "x-debug-token-link: "));
                                    // line 77
                                    echo "                                        ";
                                }
                                // line 78
                                echo "                                    ";
                            }
                            $_parent = $context['_parent'];
                            unset($context['_seq'], $context['_iterated'], $context['_key'], $context['header'], $context['_parent'], $context['loop']);
                            $context = array_intersect_key($context, $_parent) + $_parent;
                            // line 79
                            echo "                                ";
                        }
                        // line 80
                        echo "                                <table>
                                    <thead>
                                    <tr>
                                        <th>
                                            <span class=\"label\">";
                        // line 84
                        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["trace"], "method", [], "any", false, false, false, 84), "html", null, true);
                        echo "</span>
                                        </th>
                                        <th class=\"full-width\">
                                            ";
                        // line 87
                        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["trace"], "url", [], "any", false, false, false, 87), "html", null, true);
                        echo "
                                            ";
                        // line 88
                        if ( !twig_test_empty(twig_get_attribute($this->env, $this->source, $context["trace"], "options", [], "any", false, false, false, 88))) {
                            // line 89
                            echo "                                                ";
                            echo $this->extensions['Symfony\Bundle\WebProfilerBundle\Twig\WebProfilerExtension']->dumpData($this->env, twig_get_attribute($this->env, $this->source, $context["trace"], "options", [], "any", false, false, false, 89), 1);
                            echo "
                                            ";
                        }
                        // line 91
                        echo "                                        </th>
                                        ";
                        // line 92
                        if (((isset($context["profiler_token"]) || array_key_exists("profiler_token", $context) ? $context["profiler_token"] : (function () { throw new RuntimeError('Variable "profiler_token" does not exist.', 92, $this->source); })()) && (isset($context["profiler_link"]) || array_key_exists("profiler_link", $context) ? $context["profiler_link"] : (function () { throw new RuntimeError('Variable "profiler_link" does not exist.', 92, $this->source); })()))) {
                            // line 93
                            echo "                                            <th>
                                                Profile
                                            </th>
                                        ";
                        }
                        // line 97
                        echo "                                        ";
                        if ( !(null === twig_get_attribute($this->env, $this->source, $context["trace"], "curlCommand", [], "any", false, false, false, 97))) {
                            // line 98
                            echo "                                            <th>
                                                <button class=\"btn btn-sm hidden label\" title=\"Copy as curl\" data-clipboard-text=\"";
                            // line 99
                            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["trace"], "curlCommand", [], "any", false, false, false, 99), "html", null, true);
                            echo "\">curl</button>
                                            </th>
                                        ";
                        }
                        // line 102
                        echo "                                    </tr>
                                    </thead>
                                    <tbody>
                                    <tr>
                                        <th>
                                            ";
                        // line 107
                        if ((twig_get_attribute($this->env, $this->source, $context["trace"], "http_code", [], "any", false, false, false, 107) >= 500)) {
                            // line 108
                            echo "                                                ";
                            $context["responseStatus"] = "error";
                            // line 109
                            echo "                                            ";
                        } elseif ((twig_get_attribute($this->env, $this->source, $context["trace"], "http_code", [], "any", false, false, false, 109) >= 400)) {
                            // line 110
                            echo "                                                ";
                            $context["responseStatus"] = "warning";
                            // line 111
                            echo "                                            ";
                        } else {
                            // line 112
                            echo "                                                ";
                            $context["responseStatus"] = "success";
                            // line 113
                            echo "                                            ";
                        }
                        // line 114
                        echo "                                            <span class=\"label status-";
                        echo twig_escape_filter($this->env, (isset($context["responseStatus"]) || array_key_exists("responseStatus", $context) ? $context["responseStatus"] : (function () { throw new RuntimeError('Variable "responseStatus" does not exist.', 114, $this->source); })()), "html", null, true);
                        echo "\">
                                                ";
                        // line 115
                        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["trace"], "http_code", [], "any", false, false, false, 115), "html", null, true);
                        echo "
                                            </span>
                                        </th>
                                        <td";
                        // line 118
                        if (twig_get_attribute($this->env, $this->source, $context["trace"], "curlCommand", [], "any", false, false, false, 118)) {
                            echo " colspan=\"2\"";
                        }
                        echo ">
                                            ";
                        // line 119
                        echo $this->extensions['Symfony\Bundle\WebProfilerBundle\Twig\WebProfilerExtension']->dumpData($this->env, twig_get_attribute($this->env, $this->source, $context["trace"], "info", [], "any", false, false, false, 119), 1);
                        echo "
                                        </td>
                                        ";
                        // line 121
                        if (((isset($context["profiler_token"]) || array_key_exists("profiler_token", $context) ? $context["profiler_token"] : (function () { throw new RuntimeError('Variable "profiler_token" does not exist.', 121, $this->source); })()) && (isset($context["profiler_link"]) || array_key_exists("profiler_link", $context) ? $context["profiler_link"] : (function () { throw new RuntimeError('Variable "profiler_link" does not exist.', 121, $this->source); })()))) {
                            // line 122
                            echo "                                            <td>
                                                <span><a href=\"";
                            // line 123
                            echo twig_escape_filter($this->env, (isset($context["profiler_link"]) || array_key_exists("profiler_link", $context) ? $context["profiler_link"] : (function () { throw new RuntimeError('Variable "profiler_link" does not exist.', 123, $this->source); })()), "html", null, true);
                            echo "\" target=\"_blank\">";
                            echo twig_escape_filter($this->env, (isset($context["profiler_token"]) || array_key_exists("profiler_token", $context) ? $context["profiler_token"] : (function () { throw new RuntimeError('Variable "profiler_token" does not exist.', 123, $this->source); })()), "html", null, true);
                            echo "</a></span>
                                            </td>
                                        ";
                        }
                        // line 126
                        echo "                                    </tr>
                                    </tbody>
                                </table>
                            ";
                    }
                    $_parent = $context['_parent'];
                    unset($context['_seq'], $context['_iterated'], $context['_key'], $context['trace'], $context['_parent'], $context['loop']);
                    $context = array_intersect_key($context, $_parent) + $_parent;
                    // line 130
                    echo "                        ";
                }
                // line 131
                echo "                    </div>
                </div>
            ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['name'], $context['client'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 134
            echo "        ";
        }
        // line 135
        echo "    </div>
";
        
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->leave($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof);

        
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->leave($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof);

    }

    public function getTemplateName()
    {
        return "@WebProfiler/Collector/http_client.html.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  419 => 135,  416 => 134,  408 => 131,  405 => 130,  396 => 126,  388 => 123,  385 => 122,  383 => 121,  378 => 119,  372 => 118,  366 => 115,  361 => 114,  358 => 113,  355 => 112,  352 => 111,  349 => 110,  346 => 109,  343 => 108,  341 => 107,  334 => 102,  328 => 99,  325 => 98,  322 => 97,  316 => 93,  314 => 92,  311 => 91,  305 => 89,  303 => 88,  299 => 87,  293 => 84,  287 => 80,  284 => 79,  278 => 78,  275 => 77,  272 => 76,  269 => 75,  266 => 74,  263 => 73,  260 => 72,  255 => 71,  252 => 70,  249 => 69,  246 => 68,  242 => 67,  239 => 66,  233 => 63,  230 => 62,  228 => 61,  221 => 59,  216 => 58,  212 => 57,  203 => 51,  196 => 47,  192 => 45,  186 => 41,  184 => 40,  181 => 39,  171 => 38,  160 => 35,  154 => 32,  151 => 31,  149 => 30,  144 => 28,  139 => 27,  129 => 26,  116 => 22,  113 => 21,  105 => 18,  98 => 14,  94 => 12,  92 => 11,  89 => 10,  83 => 8,  81 => 7,  76 => 6,  73 => 5,  70 => 4,  60 => 3,  37 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends '@WebProfiler/Profiler/layout.html.twig' %}

{% block toolbar %}
    {% if collector.requestCount %}
        {% set icon %}
            {{ source('@WebProfiler/Icon/http-client.svg') }}
            {% set status_color = '' %}
            <span class=\"sf-toolbar-value\">{{ collector.requestCount }}</span>
        {% endset %}

        {% set text %}
            <div class=\"sf-toolbar-info-piece\">
                <b>Total requests</b>
                <span>{{ collector.requestCount }}</span>
            </div>
            <div class=\"sf-toolbar-info-piece\">
                <b>HTTP errors</b>
                <span class=\"sf-toolbar-status {{ collector.errorCount > 0 ? 'sf-toolbar-status-red' }}\">{{ collector.errorCount }}</span>
            </div>
        {% endset %}

        {{ include('@WebProfiler/Profiler/toolbar_item.html.twig', { link: profiler_url, status: status_color }) }}
    {% endif %}
{% endblock %}

{% block menu %}
<span class=\"label {{ collector.requestCount == 0 ? 'disabled' }}\">
    <span class=\"icon\">{{ source('@WebProfiler/Icon/http-client.svg') }}</span>
    <strong>HTTP Client</strong>
    {% if collector.requestCount %}
        <span class=\"count\">
            {{ collector.requestCount }}
        </span>
    {% endif %}
</span>
{% endblock %}

{% block panel %}
    <h2>HTTP Client</h2>
    {% if collector.requestCount == 0 %}
        <div class=\"empty\">
            <p>No HTTP requests were made.</p>
        </div>
    {% else %}
        <div class=\"metrics\">
            <div class=\"metric\">
                <span class=\"value\">{{ collector.requestCount }}</span>
                <span class=\"label\">Total requests</span>
            </div>
            <div class=\"metric\">
                <span class=\"value\">{{ collector.errorCount }}</span>
                <span class=\"label\">HTTP errors</span>
            </div>
        </div>
        <h2>Clients</h2>
        <div class=\"sf-tabs\">
            {% for name, client in collector.clients %}
                <div class=\"tab {{ client.traces|length == 0 ? 'disabled' }}\">
                    <h3 class=\"tab-title\">{{ name }} <span class=\"badge\">{{ client.traces|length }}</span></h3>
                    <div class=\"tab-content\">
                        {% if client.traces|length == 0 %}
                            <div class=\"empty\">
                                <p>No requests were made with the \"{{ name }}\" service.</p>
                            </div>
                        {% else %}
                            <h4>Requests</h4>
                            {% for trace in client.traces %}
                                {% set profiler_token = '' %}
                                {% set profiler_link = '' %}
                                {% if trace.info.response_headers is defined %}
                                    {% for header in trace.info.response_headers %}
                                        {% if header matches '/^x-debug-token: .*\$/i' %}
                                            {% set profiler_token = (header.getValue | slice('x-debug-token: ' | length)) %}
                                        {% endif %}
                                        {% if header matches '/^x-debug-token-link: .*\$/i' %}
                                            {% set profiler_link = (header.getValue | slice('x-debug-token-link: ' | length)) %}
                                        {% endif %}
                                    {% endfor %}
                                {% endif %}
                                <table>
                                    <thead>
                                    <tr>
                                        <th>
                                            <span class=\"label\">{{ trace.method }}</span>
                                        </th>
                                        <th class=\"full-width\">
                                            {{ trace.url }}
                                            {% if trace.options is not empty %}
                                                {{ profiler_dump(trace.options, maxDepth=1) }}
                                            {% endif %}
                                        </th>
                                        {% if profiler_token and profiler_link %}
                                            <th>
                                                Profile
                                            </th>
                                        {% endif %}
                                        {% if trace.curlCommand is not null %}
                                            <th>
                                                <button class=\"btn btn-sm hidden label\" title=\"Copy as curl\" data-clipboard-text=\"{{ trace.curlCommand }}\">curl</button>
                                            </th>
                                        {% endif %}
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <tr>
                                        <th>
                                            {% if trace.http_code >= 500 %}
                                                {% set responseStatus = 'error' %}
                                            {% elseif trace.http_code >= 400 %}
                                                {% set responseStatus = 'warning' %}
                                            {% else %}
                                                {% set responseStatus = 'success' %}
                                            {% endif %}
                                            <span class=\"label status-{{ responseStatus }}\">
                                                {{ trace.http_code }}
                                            </span>
                                        </th>
                                        <td{% if trace.curlCommand %} colspan=\"2\"{% endif %}>
                                            {{ profiler_dump(trace.info, maxDepth=1) }}
                                        </td>
                                        {% if profiler_token and profiler_link %}
                                            <td>
                                                <span><a href=\"{{ profiler_link }}\" target=\"_blank\">{{ profiler_token }}</a></span>
                                            </td>
                                        {% endif %}
                                    </tr>
                                    </tbody>
                                </table>
                            {% endfor %}
                        {% endif %}
                    </div>
                </div>
            {% endfor %}
        {% endif %}
    </div>
{% endblock %}
", "@WebProfiler/Collector/http_client.html.twig", "/var/www/symfony/symfony-demo/vendor/symfony/web-profiler-bundle/Resources/views/Collector/http_client.html.twig");
    }
}
