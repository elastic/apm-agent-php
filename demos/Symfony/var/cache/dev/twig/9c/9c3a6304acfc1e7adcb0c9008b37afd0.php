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

/* blog/index.xml.twig */
class __TwigTemplate_c46ee2c77d6ac0cc960bd5dc36813060 extends Template
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
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->enter($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "blog/index.xml.twig"));

        $__internal_6f47bbe9983af81f1e7450e9a3e3768f = $this->extensions["Symfony\\Bridge\\Twig\\Extension\\ProfilerExtension"];
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->enter($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "blog/index.xml.twig"));

        // line 1
        echo "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>
<rss version=\"2.0\">
    <channel>
        <title>";
        // line 4
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\TranslationExtension']->trans("rss.title"), "html", null, true);
        echo "</title>
        <description>";
        // line 5
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\TranslationExtension']->trans("rss.description"), "html", null, true);
        echo "</description>
        <pubDate>";
        // line 6
        echo twig_escape_filter($this->env, twig_date_format_filter($this->env, "now", "r", "GMT"), "html", null, true);
        echo "</pubDate>
        <lastBuildDate>";
        // line 7
        echo twig_escape_filter($this->env, twig_date_format_filter($this->env, ((twig_get_attribute($this->env, $this->source, twig_last($this->env, twig_get_attribute($this->env, $this->source, (isset($context["paginator"]) || array_key_exists("paginator", $context) ? $context["paginator"] : (function () { throw new RuntimeError('Variable "paginator" does not exist.', 7, $this->source); })()), "results", [], "any", false, false, false, 7)), "publishedAt", [], "any", true, true, false, 7)) ? (_twig_default_filter(twig_get_attribute($this->env, $this->source, twig_last($this->env, twig_get_attribute($this->env, $this->source, (isset($context["paginator"]) || array_key_exists("paginator", $context) ? $context["paginator"] : (function () { throw new RuntimeError('Variable "paginator" does not exist.', 7, $this->source); })()), "results", [], "any", false, false, false, 7)), "publishedAt", [], "any", false, false, false, 7), "now")) : ("now")), "r", "GMT"), "html", null, true);
        echo "</lastBuildDate>
        <link>";
        // line 8
        echo $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getUrl("blog_index");
        echo "</link>
        <language>";
        // line 9
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, (isset($context["app"]) || array_key_exists("app", $context) ? $context["app"] : (function () { throw new RuntimeError('Variable "app" does not exist.', 9, $this->source); })()), "request", [], "any", false, false, false, 9), "locale", [], "any", false, false, false, 9), "html", null, true);
        echo "</language>

        ";
        // line 11
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, (isset($context["paginator"]) || array_key_exists("paginator", $context) ? $context["paginator"] : (function () { throw new RuntimeError('Variable "paginator" does not exist.', 11, $this->source); })()), "results", [], "any", false, false, false, 11));
        foreach ($context['_seq'] as $context["_key"] => $context["post"]) {
            // line 12
            echo "            <item>
                <title>";
            // line 13
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["post"], "title", [], "any", false, false, false, 13), "html", null, true);
            echo "</title>
                <description>";
            // line 14
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["post"], "summary", [], "any", false, false, false, 14), "html", null, true);
            echo "</description>
                <link>";
            // line 15
            echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getUrl("blog_post", ["slug" => twig_get_attribute($this->env, $this->source, $context["post"], "slug", [], "any", false, false, false, 15)]), "html", null, true);
            echo "</link>
                <guid>";
            // line 16
            echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getUrl("blog_post", ["slug" => twig_get_attribute($this->env, $this->source, $context["post"], "slug", [], "any", false, false, false, 16)]), "html", null, true);
            echo "</guid>
                <pubDate>";
            // line 17
            echo twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, $context["post"], "publishedAt", [], "any", false, false, false, 17), "r", "GMT"), "html", null, true);
            echo "</pubDate>
                <author>";
            // line 18
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["post"], "author", [], "any", false, false, false, 18), "email", [], "any", false, false, false, 18), "html", null, true);
            echo "</author>
                ";
            // line 19
            $context['_parent'] = $context;
            $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, $context["post"], "tags", [], "any", false, false, false, 19));
            foreach ($context['_seq'] as $context["_key"] => $context["tag"]) {
                // line 20
                echo "                    <category>";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["tag"], "name", [], "any", false, false, false, 20), "html", null, true);
                echo "</category>
                ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['_key'], $context['tag'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 22
            echo "            </item>
        ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['_key'], $context['post'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 24
        echo "    </channel>
</rss>
";
        
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->leave($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof);

        
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->leave($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof);

    }

    public function getTemplateName()
    {
        return "blog/index.xml.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  124 => 24,  117 => 22,  108 => 20,  104 => 19,  100 => 18,  96 => 17,  92 => 16,  88 => 15,  84 => 14,  80 => 13,  77 => 12,  73 => 11,  68 => 9,  64 => 8,  60 => 7,  56 => 6,  52 => 5,  48 => 4,  43 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("<?xml version=\"1.0\" encoding=\"UTF-8\" ?>
<rss version=\"2.0\">
    <channel>
        <title>{{ 'rss.title'|trans }}</title>
        <description>{{ 'rss.description'|trans }}</description>
        <pubDate>{{ 'now'|date('r', timezone='GMT') }}</pubDate>
        <lastBuildDate>{{ (paginator.results|last).publishedAt|default('now')|date('r', timezone='GMT') }}</lastBuildDate>
        <link>{{ url('blog_index') }}</link>
        <language>{{ app.request.locale }}</language>

        {% for post in paginator.results %}
            <item>
                <title>{{ post.title }}</title>
                <description>{{ post.summary }}</description>
                <link>{{ url('blog_post', {'slug': post.slug}) }}</link>
                <guid>{{ url('blog_post', {'slug': post.slug}) }}</guid>
                <pubDate>{{ post.publishedAt|date(format='r', timezone='GMT') }}</pubDate>
                <author>{{ post.author.email }}</author>
                {% for tag in post.tags %}
                    <category>{{ tag.name }}</category>
                {% endfor %}
            </item>
        {% endfor %}
    </channel>
</rss>
", "blog/index.xml.twig", "/var/www/symfony/symfony-demo/templates/blog/index.xml.twig");
    }
}
