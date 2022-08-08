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

/* @WebProfiler/Profiler/settings.html.twig */
class __TwigTemplate_2a132dfb8121006e103b665fbcb13767 extends Template
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
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->enter($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "@WebProfiler/Profiler/settings.html.twig"));

        $__internal_6f47bbe9983af81f1e7450e9a3e3768f = $this->extensions["Symfony\\Bridge\\Twig\\Extension\\ProfilerExtension"];
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->enter($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "@WebProfiler/Profiler/settings.html.twig"));

        // line 1
        echo "<style>
#open-settings {
    color: var(--color-muted);
    display: block;
    margin: 15px 15px 5px;
}

.modal-wrap {
    -webkit-transition: all 0.3s ease-in-out;
    align-items: center;
    background: rgba(0, 0, 0, 0.8);
    display: flex;
    height: 100%;
    justify-content: center;
    left: 0;
    opacity: 1;
    overflow: auto;
    position: fixed;
    top: 0;
    transition: all 0.3s ease-in-out;
    visibility: hidden;
    width: 100%;
    z-index: 100000;
}
.modal-wrap.visible {
    opacity: 1;
    visibility: visible;
}
.modal-wrap .modal-container {
    box-shadow: 5px 5px 10px 0px rgba(0, 0, 0, 0.5);
    color: var(--base-6);
    margin: 1em;
    max-width: 94%;
    width: 600px;
}

.modal-wrap .modal-header {
    align-items: center;
    background: var(--table-header);
    display: flex;
    justify-content: space-between;
    padding: 15px 30px;
}
.modal-wrap .modal-header h3 {
    color: var(--base-6);
    margin: 0;
}
.modal-wrap .modal-header .close-modal {
    background: transparent;
    border: 0;
    color: var(--base-4);
    cursor: pointer;
    font-size: 28px;
    line-height: 1;
}

.modal-wrap .modal-header .close-modal:hover { opacity: 1; }

.modal-wrap .modal-content {
  background: var(--base-1);
  margin: 0;
  padding: 15px 30px;
  width: 100%;
  z-index: 100000;
}
.modal-content h4 {
    border-bottom: var(--border);
    margin: 0 0 15px;
    padding: 0 0 5px;
}
.modal-content input, .modal-content .form-help {
    margin-left: 15px;
}
.modal-content label {
    cursor: pointer;
    font-size: 16px;
    margin-left: 3px;
}
.modal-content .form-help {
    color: var(--color-muted);
    font-size: 14px;
    margin: 5px 0 15px 33px;
}
.modal-content .form-help + h4 {
    margin-top: 45px;
}

@media (max-width: 768px) {
    #open-settings {
        color: transparent;
    }
    #sidebar:hover #open-settings, #sidebar.expanded #open-settings {
        color: var(--color-muted);
    }
    #open-settings:before {
        content: '\\2699';
        font-weight: bold;
        font-size: 25px;
        color: var(--color-muted);
    }
    #sidebar:hover #open-settings:before, #sidebar.expanded #open-settings:before {
        content: '';
    }
}
</style>

<a href=\"#\" id=\"open-settings\">Settings</a>

<div class=\"modal-wrap\" id=\"profiler-settings\">
    <div class=\"modal-container\">
        <div class=\"modal-header\">
            <h3>Configuration Settings</h3>
            <button class=\"close-modal\">&times;</button>
        </div>

        <div class=\"modal-content\">
            <h4>Theme</h4>

            <input class=\"config-option\" type=\"radio\" name=\"theme\" value=\"auto\" id=\"settings-theme-auto\">
            <label for=\"settings-theme-auto\">Auto</label>
            <p class=\"form-help\"><strong>Default theme</strong>. It switches between Light and Dark automatically to match the operating system theme.</p>

            <input class=\"config-option\" type=\"radio\" name=\"theme\" value=\"light\" id=\"settings-theme-light\">
            <label for=\"settings-theme-light\">Light</label>
            <p class=\"form-help\">Provides greatest readability, but requires a well-lit environment.</p>

            <input class=\"config-option\" type=\"radio\" name=\"theme\" value=\"dark\" id=\"settings-theme-dark\">
            <label for=\"settings-theme-dark\">Dark</label>
            <p class=\"form-help\">Reduces eye fatigue. Ideal for low light environments.</p>

            <h4>Page Width</h4>

            <input class=\"config-option\" type=\"radio\" name=\"width\" value=\"normal\" id=\"settings-width-normal\">
            <label for=\"settings-width-normal\">Normal</label>
            <p class=\"form-help\">Fixed page width. Improves readability.</p>

            <input class=\"config-option\" type=\"radio\" name=\"width\" value=\"full\" id=\"settings-width-full\">
            <label for=\"settings-width-full\">Full-page</label>
            <p class=\"form-help\">Dynamic page width. As wide as the browser window.</p>
        </div>
    </div>
</div>

<script>
(function() {
    const configOptions = document.querySelectorAll('.config-option');
    const allSettingValues = ['theme-auto', 'theme-ligh', 'theme-dark', 'width-normal', 'width-full'];
    [...configOptions].forEach(option => {
        option.addEventListener('change', function (event) {
            const optionName = option.name;
            const optionValue = option.value;
            const settingName = 'symfony/profiler/' + optionName;
            const settingValue = optionName + '-' + optionValue;

            localStorage.setItem(settingName, settingValue);

            document.body.classList.forEach((cssClass) => {
                if (cssClass.startsWith(optionName)) {
                    document.body.classList.remove(cssClass);
                }
            });

            if ('theme-auto' === settingValue) {
                document.body.classList.add((matchMedia('(prefers-color-scheme: dark)').matches ? 'theme-dark' : 'theme-light'));
            } else {
                document.body.classList.add(settingValue);
            }
        });
    });

    const openModalButton = document.getElementById('open-settings');
    const modalWindow = document.getElementById('profiler-settings');
    const closeModalButton = document.getElementsByClassName('close-modal')[0];
    const modalWrapper = document.getElementsByClassName('modal-wrap')[0]

    openModalButton.addEventListener('click', function(event) {
        document.getElementById('settings-' + (localStorage.getItem('symfony/profiler/theme') || 'theme-auto')).checked = 'checked';
        document.getElementById('settings-' + (localStorage.getItem('symfony/profiler/width') || 'width-normal')).checked = 'checked';

        modalWindow.classList.toggle('visible');
        event.preventDefault();
    });

    closeModalButton.addEventListener('click', function() {
        modalWindow.classList.remove('visible');
    });
    modalWrapper.addEventListener('click', function(event) {
        if (event.target == event.currentTarget) {
            modalWindow.classList.remove('visible');
        }
    });
})();
</script>
";
        
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->leave($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof);

        
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->leave($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof);

    }

    public function getTemplateName()
    {
        return "@WebProfiler/Profiler/settings.html.twig";
    }

    public function getDebugInfo()
    {
        return array (  43 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("<style>
#open-settings {
    color: var(--color-muted);
    display: block;
    margin: 15px 15px 5px;
}

.modal-wrap {
    -webkit-transition: all 0.3s ease-in-out;
    align-items: center;
    background: rgba(0, 0, 0, 0.8);
    display: flex;
    height: 100%;
    justify-content: center;
    left: 0;
    opacity: 1;
    overflow: auto;
    position: fixed;
    top: 0;
    transition: all 0.3s ease-in-out;
    visibility: hidden;
    width: 100%;
    z-index: 100000;
}
.modal-wrap.visible {
    opacity: 1;
    visibility: visible;
}
.modal-wrap .modal-container {
    box-shadow: 5px 5px 10px 0px rgba(0, 0, 0, 0.5);
    color: var(--base-6);
    margin: 1em;
    max-width: 94%;
    width: 600px;
}

.modal-wrap .modal-header {
    align-items: center;
    background: var(--table-header);
    display: flex;
    justify-content: space-between;
    padding: 15px 30px;
}
.modal-wrap .modal-header h3 {
    color: var(--base-6);
    margin: 0;
}
.modal-wrap .modal-header .close-modal {
    background: transparent;
    border: 0;
    color: var(--base-4);
    cursor: pointer;
    font-size: 28px;
    line-height: 1;
}

.modal-wrap .modal-header .close-modal:hover { opacity: 1; }

.modal-wrap .modal-content {
  background: var(--base-1);
  margin: 0;
  padding: 15px 30px;
  width: 100%;
  z-index: 100000;
}
.modal-content h4 {
    border-bottom: var(--border);
    margin: 0 0 15px;
    padding: 0 0 5px;
}
.modal-content input, .modal-content .form-help {
    margin-left: 15px;
}
.modal-content label {
    cursor: pointer;
    font-size: 16px;
    margin-left: 3px;
}
.modal-content .form-help {
    color: var(--color-muted);
    font-size: 14px;
    margin: 5px 0 15px 33px;
}
.modal-content .form-help + h4 {
    margin-top: 45px;
}

@media (max-width: 768px) {
    #open-settings {
        color: transparent;
    }
    #sidebar:hover #open-settings, #sidebar.expanded #open-settings {
        color: var(--color-muted);
    }
    #open-settings:before {
        content: '\\2699';
        font-weight: bold;
        font-size: 25px;
        color: var(--color-muted);
    }
    #sidebar:hover #open-settings:before, #sidebar.expanded #open-settings:before {
        content: '';
    }
}
</style>

<a href=\"#\" id=\"open-settings\">Settings</a>

<div class=\"modal-wrap\" id=\"profiler-settings\">
    <div class=\"modal-container\">
        <div class=\"modal-header\">
            <h3>Configuration Settings</h3>
            <button class=\"close-modal\">&times;</button>
        </div>

        <div class=\"modal-content\">
            <h4>Theme</h4>

            <input class=\"config-option\" type=\"radio\" name=\"theme\" value=\"auto\" id=\"settings-theme-auto\">
            <label for=\"settings-theme-auto\">Auto</label>
            <p class=\"form-help\"><strong>Default theme</strong>. It switches between Light and Dark automatically to match the operating system theme.</p>

            <input class=\"config-option\" type=\"radio\" name=\"theme\" value=\"light\" id=\"settings-theme-light\">
            <label for=\"settings-theme-light\">Light</label>
            <p class=\"form-help\">Provides greatest readability, but requires a well-lit environment.</p>

            <input class=\"config-option\" type=\"radio\" name=\"theme\" value=\"dark\" id=\"settings-theme-dark\">
            <label for=\"settings-theme-dark\">Dark</label>
            <p class=\"form-help\">Reduces eye fatigue. Ideal for low light environments.</p>

            <h4>Page Width</h4>

            <input class=\"config-option\" type=\"radio\" name=\"width\" value=\"normal\" id=\"settings-width-normal\">
            <label for=\"settings-width-normal\">Normal</label>
            <p class=\"form-help\">Fixed page width. Improves readability.</p>

            <input class=\"config-option\" type=\"radio\" name=\"width\" value=\"full\" id=\"settings-width-full\">
            <label for=\"settings-width-full\">Full-page</label>
            <p class=\"form-help\">Dynamic page width. As wide as the browser window.</p>
        </div>
    </div>
</div>

<script>
(function() {
    const configOptions = document.querySelectorAll('.config-option');
    const allSettingValues = ['theme-auto', 'theme-ligh', 'theme-dark', 'width-normal', 'width-full'];
    [...configOptions].forEach(option => {
        option.addEventListener('change', function (event) {
            const optionName = option.name;
            const optionValue = option.value;
            const settingName = 'symfony/profiler/' + optionName;
            const settingValue = optionName + '-' + optionValue;

            localStorage.setItem(settingName, settingValue);

            document.body.classList.forEach((cssClass) => {
                if (cssClass.startsWith(optionName)) {
                    document.body.classList.remove(cssClass);
                }
            });

            if ('theme-auto' === settingValue) {
                document.body.classList.add((matchMedia('(prefers-color-scheme: dark)').matches ? 'theme-dark' : 'theme-light'));
            } else {
                document.body.classList.add(settingValue);
            }
        });
    });

    const openModalButton = document.getElementById('open-settings');
    const modalWindow = document.getElementById('profiler-settings');
    const closeModalButton = document.getElementsByClassName('close-modal')[0];
    const modalWrapper = document.getElementsByClassName('modal-wrap')[0]

    openModalButton.addEventListener('click', function(event) {
        document.getElementById('settings-' + (localStorage.getItem('symfony/profiler/theme') || 'theme-auto')).checked = 'checked';
        document.getElementById('settings-' + (localStorage.getItem('symfony/profiler/width') || 'width-normal')).checked = 'checked';

        modalWindow.classList.toggle('visible');
        event.preventDefault();
    });

    closeModalButton.addEventListener('click', function() {
        modalWindow.classList.remove('visible');
    });
    modalWrapper.addEventListener('click', function(event) {
        if (event.target == event.currentTarget) {
            modalWindow.classList.remove('visible');
        }
    });
})();
</script>
", "@WebProfiler/Profiler/settings.html.twig", "/var/www/symfony/symfony-demo/vendor/symfony/web-profiler-bundle/Resources/views/Profiler/settings.html.twig");
    }
}
