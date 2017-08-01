<?php
/**
 * Created by PhpStorm.
 * User: manus
 * Date: 24/02/16
 * Time: 15:02
 */

namespace GLFramework\Modules\Admin;


use GLFramework\Bootstrap;
use GLFramework\Controller;
use GLFramework\Controller\AuthController;
use GLFramework\Events;
use GLFramework\Log;
use GLFramework\Model\Page;
use GLFramework\Model\User;
use GLFramework\Model\UserPage;
use GLFramework\Module\ModuleManager;

class admin extends AuthController
{
    var $name = "Administracion Interna";
    var $admin = true;

    var $controllers = array();
    var $info = array();
    public function run()
    {
        if (!parent::run()) return false; // TODO: Change the autogenerated stub
        $this->users_count = $this->user->get_all()->count();
        $this->modules_count = count(ModuleManager::getInstance()->getModules());
        $this->info['version'] = Bootstrap::getSingleton()->getVersion();
        $this->info['php'] = PHP_VERSION;
        $this->info['server']['software'] = $_SERVER['SERVER_SOFTWARE'];
        $this->info['server']['host'] = gethostname();
        $this->info['server']['ip'] = gethostbyname(gethostname());
        $this->info['server']['name'] = $_SERVER['SERVER_NAME'];
        $this->info['server']['load'] = implode(", ", sys_getloadavg());
        $this->info['extensions'] = implode(", ", get_loaded_extensions());
        $controllers = Events::dispatch('getAdminControllers')->getArray();
        foreach ($controllers as $controller)
        {
            if(!is_array($controller)) $controller = array($controller);
            foreach ($controller as $item)
            {
                if($instance = ModuleManager::instanceController($item))
                {
                    $this->controllers[] = $instance;
                }
                else
                {
                    Log::w("The controller for admin '$item' Not found!");
                }
            }
        }
        if(isset($_GET['update']))
        {
            $code = exec("composer update", $result);
            $this->addMessage("Resultado: " . $code . " " . implode("\n", $result));
        }
        
        
    }

    /**
     * @param $controller Controller
     * @param $user User
     * @return bool
     */
    public static function isUserAllowed($controller, $user)
    {
        $context = Events::getContext();
        $config = $context->getConfig();
        if($user->admin) return true;
        $page = new Page();
        $pageUser = new UserPage();
        $current = $page->get_by_controller($controller)->getModel();
        if ($current->id > 0) {
            $pageUser = $pageUser->getByPageUser($user->id, $current->id)->getModel();
            if($pageUser->id > 0)
            {
                return $pageUser->allowed?true:false;
            }
        }
        if($controller->admin) return false;
        if($controller->allowed) return true;
        return $config['allowDefault']?:null;
    }

}