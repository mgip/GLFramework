<?php

namespace Core\Installer;
use GLFramework\Bootstrap;
use GLFramework\ConfigurationManager;
use GLFramework\Controller;
use GLFramework\DatabaseManager;
use GLFramework\Events;
use GLFramework\Mail;
use GLFramework\Model;
use GLFramework\Model\User;
use GLFramework\Module\Module;
use GLFramework\Module\ModuleManager;
use GLFramework\Module\ModuleScanner;
use GLFramework\Upload\Upload;
use Symfony\Component\Yaml\Yaml;

/**
 * Created by PhpStorm.
 * User: manus
 * Date: 26/5/16
 * Time: 23:10
 */
class setup extends Controller
{
    /**
     * @var ConfigurationManager
     */
    var $configManager;
    var $step;
    var $db_config;
    var $hasAdmin;
    var $steps = array();
    var $view = "steps/1.twig";
    /**
     * @var Module[]
     */
    var $plugins;

    /**
     * Implementar aqui el código que ejecutara nuestra aplicación
     * @return mixed
     */
    public function run()
    {
        $this->configManager = new ConfigurationManager(ConfigurationManager::getAutogeneratedFile());
        // TODO: Implement run() method.
        if(isset($this->mainConfig['app']['configured']) && isset($this->config['configured']) && $this->mainConfig['app']['configured'] && $this->config['configured'])
        {
            $this->addMessage("El sitio ya esta configurado! Acceda con un administrador al panel de control y reinicie la instalacion.", "warning");
            $this->quit("/");
            return true;
        }
        $this->step = $this->params['step'];
        $this->steps = $this->getSteps();
        if(!$this->step) $this->step = "1";
        $index = intval($this->step) - 1;
        if(!isset($this->steps[$index]))
        {
            $this->addMessage("Este paso no esta disponible en el instalador!");
            return $this->quit($this->getLink($this, array('step' => '1')));
        }
        $call = $this->steps[$index]['function'];
        if(($view = call_user_func($call, $this)) === true)
        {
            return $this->quit($this->getLink($this, array('step' => $index + 2)));
        }
        $this->view = $view;
    }

    public function parseUploads() {
        if(isset($_FILES['settings']['name'])) {

            foreach ($_FILES['settings']['name'] as $key => $value) {
                $upload = new Upload($this->getUploads(), $_FILES['settings']);
                if($upload->isSuccess($key)) {

                    $upload->move($key);
                    $_POST['settings'][$key] = $upload->getFilename($key);
                }
            }
        }

    }
    private function step1()
    {

        if(isset($_POST['save']))
        {
            $upload = $this->getUploads()->allocate('banner');
            $config = $this->loadCurrentConfig();
            $this->parseUploads();
            $config['app'] = array_merge($config['app'], $_POST['settings']);
            if($upload->isSuccess())
            {
                if($upload->move())
                {
                    $file = $upload->getFilename();
                    $config['app']['banner'] = "/" . $file;
                }
                else
                {
                    $this->addMessage("Se ha producido un error al guardar la imagen", "danger");
                }
            }
            $config['app']['name'] = $_POST['site_name'];
            $config['app']['debug'] = $_POST['debug']?true:false;
            if($this->saveConfig($config))
            {
                return true;
            }
            else
            {
                $this->addMessage("No se puede generar el archivo de configuracion: " . $this->configManager->getFilename());
            }
        }
        return "steps/1.twig";
    }

    private function step2()
    {
        if(isset($_POST['save']) || isset($_POST['create_database']))
        {
            $config = $this->loadCurrentConfig();
            $config['database']['hostname'] = $_POST['hostname'];
            $config['database']['username'] = $_POST['username'];
            $config['database']['password'] = $_POST['password'];
            $config['database']['database'] = $_POST['database'];
            try
            {
                if(DatabaseManager::isSelected())
                    $this->getDb()->disconnect();
                $this->db_config = new DatabaseManager($config);
                if(isset($_POST['create_database']))
                {
                    if($this->db_config->exec("CREATE DATABASE `{$config['database']['database']}`"))
                    {
                        $this->db_config->reset();
                        if($this->db_config->connect())
                        {
                            $this->addMessage("Se ha creado la base de datos con éxito");
                        }
                    }
                    else
                    {
                        $this->addMessage("No se ha podido crear la base de datos", "danger");
                    }
                }

                if($this->db_config->isSelected())
                {
                    $this->addMessage("Se ha conectado correctamente con la base de datos");
                    $this->saveConfig($config);

                    return true;
                }
                else
                {
                    $this->addMessage("No se puede econtrar la base de datos", "danger");
                }

            }
            catch (\Exception $ex)
            {
                Events::dispatch('onException', $ex);
                $this->addMessage($ex->getMessage(), "danger");
            }
        }
        return "steps/2.twig";
    }

    private function step3()
    {
        // TODO: Buscar a otros conectores.
        $this->mails = array(
            'Mail' => 'Mail command',
            'PHPMailer' => 'PHPMailer (SMTP)',
        );
        if(isset($_POST['save']) || isset($_POST['email']))
        {
            $config = $this->loadCurrentConfig();
            $config['mail']['mailsystem'] = $_POST['mailsystem'];
            if($_POST['mailsystem'] == "PHPMailer")
            {
                $config['mail']['hostname'] = $_POST['hostname'];
                $config['mail']['port'] = $_POST['port'];
                $config['mail']['username'] = $_POST['username'];
                $config['mail']['password'] = $_POST['password'];
            }

            $config['mail']['from']['title'] = $_POST['from']['title'];
            $config['mail']['from']['email'] = $_POST['from']['email'];

            if($this->saveConfig($config))
            {
                if($_POST['save']) return true;
                $mail = new Mail(null, null, $config);
                $message = $mail->render($this, "mail.twig", array());
                if($mail->send($_POST['from']['email'], "Email test", $message))
                {
                    $this->addMessage("Se ha enviado un email de prueba");
                }
                else{
                    $this->addMessage("Se ha producido un error al enviar el mensaje", "danger");
                }
            }
        }
        return "steps/3.twig";
    }

    private function step4()
    {
        $user = Model::newInstance('User'); // Allow Model Override
        $this->hasAdmin = $user->get(array('admin' => '1'))->count() > 0;
        if(!$this->hasAdmin)
        {
            if(isset($_POST['save']))
            {
                if($_POST['password'] == $_POST['password_retype'])
                {
                    $user->admin = 1;
                    $user->user_name = $_POST['username'];
                    $user->nombre = $_POST['nombre'];
                    $user->password = $user->encrypt($_POST['password']);
                    $user->email = $_POST['email'];
                    $user->id = null;
                    if($user->save())
                    {
                        $this->addMessage("Se ha creado la cuenta correctamente. Login: " . $user->user_name);
                        return true;
                    }
                    else
                    {
                        $this->addMessage("Se ha producido un error al crear la cuenta", "danger");
                    }
                }
                else
                {
                    $this->addMessage("Las contraseñas no coinciden", "danger");
                }

            }
        }
        return "steps/4.twig";
    }

    private function step5()
    {
        $moduleScanner = new ModuleScanner();
        $this->plugins = $moduleScanner->scan(Bootstrap::getSingleton()->getDirectory());
        if(isset($_POST['save']))
        {
            $config = $this->loadCurrentConfig();
            $this->configManager->clearModules($config);
            foreach ($_POST['module'] as $module => $k)
            {
                foreach ($this->plugins as $plugin)
                {
                    if($plugin->title == $module)
                    {
                        $this->configManager->enableModule($config, $plugin);
                        break;
                    }
                }
            }
            if($this->saveConfig($config))
            {
                return true;
            }
            else
            {
                $this->addMessage("No se puede generar el archivo de configuracion: " . $this->configManager->getFilename());
            }
        }
        return "steps/5.twig";
    }
    private function step6()
    {
        if(isset($_POST['save']))
        {
            $config = $this->loadCurrentConfig();
            $config['app']['configured'] = true;
            $this->configManager->setModuleSettings($config, $this->module, array('configured' => true));
            $this->saveConfig($config);
            $this->quit("/");
        }
        return "steps/6.twig";
    }
    
    public function next()
    {
        return $this->getLink($this, array('step' => $this->step + 1));
    }

    public function loadCurrentConfig()
    {
        return $this->configManager->load();
    }

    public function saveConfig($config)
    {
        if(!$this->configManager->save($config))
        {
            $this->addMessage("No se puede escribir sobre '{$this->configManager->getFilename()}'", "danger");
            return false;

        }
        return true;
    }

    public function getSteps()
    {
        $steps = array();
        $steps[] = array(
            'title' => 'Configuracion Inicial',
            'function' => array($this, 'step1')
        );
        $steps[] = array(
            'title' => 'Configuracion Base de datos',
            'function' => array($this, 'step2')
        );
        $steps[] = array(
            'title' => 'Configuracion Correo',
            'function' => array($this, 'step3')
        );
        $steps[] = array(
            'title' => 'Cuenta Administrador',
            'function' => array($this, 'step4')
        );
        $steps[] = array(
            'title' => 'Seleccionar módulos',
            'function' => array($this, 'step5')
        );
        foreach ($this->getInstallers() as $installer)
        {
            $instance = new $installer();

            $steps[] = array(
                'title' => $instance->name,
                'function' => array($instance, 'install')
            );
        }

        $steps[] = array(
            'title' => 'Finalizar configuracion',
            'function' => array($this, 'step6')
        );

        return $steps;
    }

    public function getInstallers()
    {
        $result = array();
        $items = Events::dispatch('getInstallersControllers')->getArray();
        foreach ($items as $item)
        {
            if(!is_array($item)) $item = array($item);
            foreach ($item as $controller)
            {
                $result[] = $controller;
            }
        }
        return $result;
    }
}