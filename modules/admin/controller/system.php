<?php
/**
 * Created by PhpStorm.
 * User: mmunoz
 * Date: 13/06/17
 * Time: 11:52
 */

namespace GLFramework\Modules\Admin;


use GLFramework\Bootstrap;
use GLFramework\Controller\AuthController;
use GLFramework\DBStructure;
use GLFramework\Mail;
use GLFramework\Model;
use GLFramework\Model\Vars;
use GLFramework\Module\ModuleManager;

class system extends AuthController
{
    var $admin = true;
    var $name = "Sistema";
    var $subtemplate = "system/home.twig";
    public $vars;
    public $database;

    public function run()
    {
        parent::run(); // TODO: Change the autogenerated stub
        $section = $this->params['section'];
        if($section == "home")
        {
            $this->subtemplate = "system/home.twig";
        } elseif ($section == "mail") {
            $this->subtemplate = "system/mail.twig";
            $this->mailSection();
        } elseif ($section == "vars") {
            $this->subtemplate = "system/vars.twig";
            $this->varsSection();
        } elseif ($section == "database") {
            $this->subtemplate = "system/database.twig";
            $this->databaseSection();
        }elseif ($section == "info") {
            $this->subtemplate = "system/info.twig";
            $this->infoSection();
        }elseif ($section == "trans") {
            $this->subtemplate = "system/trans.twig";
            $this->transSection();
        }
    }

    public function varsSection()
    {
        $this->vars = new Vars();
        if (isset($_POST['save'])) {
            $key = $_POST['save'];
            $value = $_POST['vars'][$key];
            Vars::setVar($key, $value);
            $this->addMessage("Se ha actualizado correctamente");
        }

        if(isset($_POST['add']) && isset($_POST['add']['save'])) {
            $key = $_POST['add']['key'];
            $value = $_POST['add']['value'];
            Vars::setVar($key, $value);
            $this->addMessage("Se ha creado correctamente");
        }
    }

    public function mailSection()
    {
        $mail = new Mail();
        $this->transport = get_class($mail->getTransport());
        $this->mail_config = $mail->config['mail'];
        if (isset($_POST['send'])) {
            $content = $mail->render($this, 'mail/test.twig', array());
            try {
                if ($mail->send($_POST['email'], "Test Email", $content)) {
                    $this->addMessage("Se ha enviado correctamente el email");
                } else {
                    $this->addMessage("Se ha producido un error al enviar el email", "danger");
                }
            } catch (\Exception $ex) {
                $this->addMessage("Error al enviar el email: " . $ex->getMessage() . "\n" . display_exception($ex, 1, false));
            }
        }
    }

    public function databaseSection() {
        $this->database = array();

        if(isset($_GET['recreate'])) {
            $struct = new DBStructure();
            $count = $struct->executeModelChanges($this->getDb());
            $this->addMessage("Se han realizado $count cambios en la db!");
        }
        if(isset($_GET['optimize'])) {
            $res = $this->getDb()->exec("OPTIMIZE TABLE `{$_GET['optimize']}`");
            if ($res) {
                $this->addMessage("Tabla optimizada correctamente! ($res)");
            } else {
                $this->addMessage("Error optimizando tabla", "danger");
            }
        }
        $res = $this->getDb()->select("SHOW TABLES");
        foreach ($res as $item) {
            $table = $item[0];
            $this->database[] = array('model' => '', 'table' => $item[0], 'count' => $c, 't' => $t, 'module' => '');
        }
//        foreach (Bootstrap::getSingleton()->getModels() as $model) {
//            try{
//                $instance = Model::newInstance($model, array(), $module);
//                if($instance) {
//    //                $t = microtime(true);
//    //                $c = $instance->count();
//    //                $t = microtime(true) - $t;
//                    $this->database[] = array('model' => get_class($instance), 'table' => $instance->getTableName(),
//                        'count' => 0, 't' => $t, 'module' => $module->title
//                    );
//                }
//            } catch (\Exception $ex) {
//
//            }
//
//        }
    }

    public function transSection() {
        if(isset($_GET['scan'])) {
            $modules = ModuleManager::getInstance()->getModules();
            foreach ($modules as $module) {
                $files = $this->listDir($module->getDirectory());
                foreach ($files as $file) {
                    $data = file_get_contents($file);

//                    preg_match_all("#\"(.*?)\" |#")

                }
            }
        }
    }

    public function infoSection() {
    }

    public function phpInfo() {
        phpinfo();
    }


    private function listDir($dir, $depth = 128, &$list = []) {
        if($depth == 0) return $list;
        $files = scandir($dir);
        foreach ($files as $file) {
            if($file != "." && $file != "..") {
                $path = $dir . "/" . $file;
                if(is_dir($path)) {
                    $this->listDir($path, $depth - 1, $list);
                } else {
                    $list[] = $path;
                }
            }
        }
        return $list;
    }


}