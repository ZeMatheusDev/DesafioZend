<?php
namespace Application\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Session\Container;

abstract class AbstractAuthController extends AbstractActionController
{

    public function onDispatch(\Laminas\Mvc\MvcEvent $e)
    {
        $session = new Container();
        
        if (empty($session->user)) {
            return parent::onDispatch($e);
        }

        if($e->getRouteMatch()->getParam('action') == 'logout' || $e->getRouteMatch()->getParam('action') == 'edit' || $e->getRouteMatch()->getParam('action') == 'update'){
            if($session->user){
                return parent::onDispatch($e);
            }
        }

        return $this->redirect()->toRoute('dashboard');
    }
}
?>