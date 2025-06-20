<?php
namespace Application\Middleware;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Session\Container;

abstract class AbstractNoAuthController extends AbstractActionController
{

    public function onDispatch(\Laminas\Mvc\MvcEvent $e)
    {
        $session = new Container();
        
        if ($session->user) {
            return parent::onDispatch($e);
        }

        return $this->redirect()->toRoute('home');
    }
}
?>