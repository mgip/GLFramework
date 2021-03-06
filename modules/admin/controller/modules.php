<?php
/**
 * Created by PhpStorm.
 * User: manus
 * Date: 06/04/17
 * Time: 12:07
 */

namespace GLFramework\Modules\Admin;


use GLFramework\Bootstrap;
use GLFramework\ConfigurationManager;
use GLFramework\Controller\AuthController;
use GLFramework\Filesystem;
use GLFramework\Module\Module;
use GLFramework\Module\ModuleManager;
use GLFramework\Module\ModuleScanner;
use GLFramework\Upload\Upload;
use GLFramework\Upload\Uploads;
use GLFramework\Zip\ZipManager;

class modules extends AuthController {

    public $admin = true;
    public $name = "Gestor de modulos";
    /**
     * @var Module[]
     */
    public $modules;
    public $module;

    public $configurationManager;
    public function run()
    {
        parent::run(); // TODO: Change the autogenerated stub
        $this->setTemplate("modules/modules.twig");
        $moduleScanner = new ModuleScanner();
        $this->modules = $moduleScanner->scan(".");

        $this->configurationManager = $configManager = new ConfigurationManager(ConfigurationManager::getAutogeneratedFile());
        if (isset($this->params['name'])) {
            $module = null;
            $name = urldecode($this->params['name']);
            foreach ($this->modules as $module1) {
                if ($module1->title == $name) {
                    $module = $module1;
                    break;
                }
            }
            if ($module != null) {
                if (isset($this->params['state'])) {
                    $config = $configManager->load();
                    if ($this->params['state'] == "enable") {
                        if($configManager->canEnableModule($module, $reason)) {
                            $configManager->enableModule($config, $module);
                            $this->setModuleStateResponse(true);
                        } else {
                            $this->setModuleStateResponse("Can't enable this module because: $reason");
                        }
                    } elseif ($this->params['state'] == "disable") {
                        $configManager->disableModule($config, $module);
                    }
                    if ($configManager->save($config)) {
                        $this->addMessage("Se ha guardado correctamente");
                        $this->quit($this->getLink($this, array('name' => $this->params['name'])));
                    } else {
                        $this->addMessage("No se ha podido guardar la configuracion", "danger");
                    }
                }
                $this->module = $module;
                $this->module->init();
                if (isset($_POST['settings'])) {
                    if($this->module->isEnabled()) {
                        $config = $configManager->load();
                        $this->parseUploads();
                        $configManager->setModuleSettings($config, $module, $_POST['settings']);
                        if ($configManager->save($config)) {
                            $this->addMessage("Se ha guardado correctamente");
                            $this->quit($this->getLink($this, array('name' => $this->params['name'])));
                        } else {
                            $this->addMessage("No se ha podido guardar la configuracion", "danger");
                        }
                    } else {
                        $this->addMessage("No se puede configurar modulos desactivados", "danger");
                    }
                }
                $this->setTemplate("modules/module.twig");
            } else {
                $this->addMessage("No se ha encontrado el módulo", "danger");
            }
        } elseif (isset($_GET['add'])) {
            $this->setTemplate("modules/add.twig");
            if (isset($_POST['add'])) {
                $zip = $this->getUploads()->allocate('zip');
                if (!$zip->isEmpty()) {
                    if ($zip->isSuccess()) {
                        $manager = new ZipManager($zip->tmpName());
                        $fs = new Filesystem(remove_file_extension($zip->name()));
                        $fs->mkdir();
                        $manager->extractAll($fs->getAbsolutePath());
                        $this->addMessage("Extraido a: ". $fs->getAbsolutePath());
                    } else {
                        $this->addMessage("Error al subir el archivo ZIP: " . $zip->error(), "danger");
                    }
                }
            }
        }
    }

    public function getModuleInManager($module) {
        return ModuleManager::getInstance()->getModuleInstanceByName($module->title);
    }

    public function setModuleStateResponse($result) {

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

    /**
     * @param $config
     * @param $module Module
     * @return mixed
     */
    public function getModuleConfiguration($config, $module)
    {
        return $config['modules'][$module->getFolderContainer()][$module->getListName()];
    }

    /**
     * @param $config
     * @param $module Module
     * @param $settings
     * @return bool
     */
    public function setModuleConfiguration(&$config, $module, $settings)
    {
        $keys = &$config['modules'][$module->getFolderContainer()];
        foreach ($keys as $key => &$value) {
            if (is_array($value) && isset($value[$module->getListName()])) {
                $value[$module->getListName()] = $settings;
                return true;
            } elseif (strval($value) == $module->getListName()) {
                $value =  array($module->getListName() => $settings);
//                print_debug($config);
                return true;
            }
        }
        $keys[] = array($module->getListName() => $settings);
        return false;
    }


}