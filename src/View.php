<?php
/**
 *     GLFramework, small web application framework.
 *     Copyright (C) 2016.  Manuel Muñoz Rosa
 *
 *     This program is free software: you can redistribute it and/or modify
 *     it under the terms of the GNU General Public License as published by
 *     the Free Software Foundation, either version 3 of the License, or
 *     (at your option) any later version.
 *
 *     This program is distributed in the hope that it will be useful,
 *     but WITHOUT ANY WARRANTY; without even the implied warranty of
 *     MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *     GNU General Public License for more details.
 *
 *     You should have received a copy of the GNU General Public License
 *     along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * Created by PhpStorm.
 * User: manus
 * Date: 13/1/16
 * Time: 17:26
 */

namespace GLFramework;

use GLFramework\Media\JavascriptMedia;
use GLFramework\Media\StylesheetMedia;
use GLFramework\Module\Module;
use GLFramework\Module\ModuleManager;
use GLFramework\Modules\Debugbar\Debugbar;
use GLFramework\Twig\FrameworkExtras;
use GLFramework\Twig\IExtra;

/**
 * Class View
 * @package GLFramework
 */
class View
{
    private $filters = array();
    private $twig;
    /**
     * @var Controller
     */
    private $controller;
    /**
     * @var JavascriptMedia[]
     */
    private $javascriptMedia = array();
    /**
     * @var StylesheetMedia[]
     */
    private $stylesheetMedia = array();
    private $directories;
    private $config;
    /**
     * View constructor.
     *
     * @param $controller Controller|Module
     */
    public function __construct($controller)
    {
        $this->controller = $controller;
        $this->directories = ModuleManager::getInstance()->getViews($controller->module);
        $loader = new \Twig_Loader_Filesystem($this->directories);
        $this->config = $this->getController()->mainConfig;
        $config = isset($this->config['twig_config'])?$this->config['twig_config']:[];
        if(isset($this->config['app']['twig_cache']) && $this->config['app']['twig_cache'])
        {
            $fs = new Filesystem('twig_cache');
            $fs->mkdir();
            $config['cache'] = $fs->getAbsolutePath();
        }
        $this->twig = new \Twig_Environment($loader, $config);
        Events::dispatch('onViewCreated', array(&$this->twig));
        Events::getInstance()->listen('displayScripts', array($this, 'getJavascripts'));
        Events::getInstance()->listen('displayStyle', array($this, 'getStylesheets'));
        Log::d('Register listener');
        $this->addExtras();
    }

    /**
     * Devuelve la vista renderizada
     *
     * @param null $data
     * @param array $params
     * @return array|null|string
     */
    public function render($data = array(), $params = array())
    {
        if ($this->controller->getTemplate() !== null) {
            foreach ($this->controller->filters as $filter) {
                $this->twig->addFilter($filter);
            }
            $this->twig->addGlobal('params', $params);
            $key = "twig" . microtime(true);
//            Debugbar::timer($key, $this->controller->getTemplate());
            $template = $this->twig->load($this->controller->getTemplate());
            $data = $template->render($this->getData($data));
//            Debugbar::stopTimer($key);
        }
        return $data;
    }

    private function getData($data) {
        if($data === true) return array();
        if($data === false) return array();
        if($data) return $data;
        return array();
    }

    /**
     * TODO
     */
    public function addExtras()
    {
        $this->addExtra(new FrameworkExtras($this));
        $extras = Bootstrap::getSingleton()->getTwigExtras();
        foreach ($extras as $extra) {
            $this->addExtra(new $extra($this));
        }
    }

    /**
     * TODO
     *
     * @param $extra IExtra
     */
    public function addExtra($extra)
    {
        $functions = $extra->getFunctions();
        if ($functions && is_array($functions)) {
            foreach ($functions as $function) {
                $this->twig->addFunction($function);
            }
        }
        $filters = $extra->getFilters();
        if ($filters && is_array($filters)) {
            foreach ($filters as $filter) {
                $this->twig->addFilter($filter);
            }
        }
        $globals = $extra->getGlobals();
        if ($globals && is_array($globals)) {
            foreach ($globals as $key => $value) {
                $this->twig->addGlobal($key, $value);
            }
        }
    }

    /**
     * Devuelve la plantilla renderizada
     *
     * @param $template
     * @param array $data
     * @return string
     */
    public function display($template, $data = array())
    {
        return $this->getTwig()->render($template, $data);
    }

    /**
     * Devulve una vista compatible con los clientes de correo
     *
     * @param $template
     * @param array $data
     * @param array $css
     * @return string
     */
    public function mail($template, $data = array(), &$css = array())
    {
        $this->twig->addFunction(new \Twig_SimpleFunction('css', function ($file) use (&$css) {
            $css[] = $file;
        }));
        $template = $this->twig->loadTemplate($template);
        return $template->render($data);
    }

    /**
     * TODO
     *
     * @param $filter
     */
    public function addFilter($filter)
    {
        $this->filters[] = $filter;
    }

    /**
     * TODO
     *
     * @return \Twig_Environment
     */
    public function getTwig()
    {
        return $this->twig;
    }

    /**
     * TODO
     *
     * @return Controller
     */
    public function getController()
    {
        return $this->controller;
    }

    /**
     * TODO
     *
     * @param Controller $controller
     */
    public function setController($controller)
    {
        $this->controller = $controller;
    }

    /**
     * TODO
     *
     * @param $js
     * @param array $options
     */
    public function addJS($js, $options = array())
    {
        $this->javascriptMedia[] = new JavascriptMedia($js, $options);
    }

    /**
     * TODO
     *
     * @param $css
     * @param array $options
     */
    public function addCSS($css, $options = array())
    {
        $this->stylesheetMedia[] = new StylesheetMedia($css, $options);
    }

    /**
     * TODO
     *
     * @param $view View
     * @return array|string
     */
    public function getJavascripts($view)
    {
        $result = '';
        foreach ($view->javascriptMedia as $js) {
            $result .= $js->getBrowserCode();
        }
        $view->javascriptMedia = array();
        return $result;
    }

    /**
     * TODO
     *
     * @param $view View
     * @return array|string
     */
    public function getStylesheets($view = null)
    {
        if($view === null) {
            $view = $this;
        }
        $result = '';
        foreach ($view->stylesheetMedia as $css) {
            $result .= $css->getBrowserCode() . "\n";
        }
        $view->stylesheetMedia = array();
        return $result;
    }

    /**
     * @return JavascriptMedia[]
     */
    public function getJavascriptMedia()
    {
        return $this->javascriptMedia;
    }

    /**
     * @return StylesheetMedia[]
     */
    public function getStylesheetMedia()
    {
        return $this->stylesheetMedia;
    }



    /**
     * TODO
     *
     * @return array
     */
    public function getDirectories()
    {
        return $this->directories;
    }
}
