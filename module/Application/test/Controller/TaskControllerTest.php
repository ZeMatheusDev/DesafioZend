<?php

declare(strict_types=1);

namespace ApplicationTest\Controller;

use Application\Controller\TaskController;
use Laminas\Test\PHPUnit\Controller\AbstractHttpControllerTestCase;
use Laminas\Session\Container;
use Laminas\Session\SessionManager;

class TaskControllerTest extends AbstractHttpControllerTestCase
{
    protected function setUp(): void
    {
        $this->setApplicationConfig(
            include __DIR__ . '/../../../../config/application.config.php'
        );
        parent::setUp();

        $sessionManager = new SessionManager();
        Container::setDefaultManager($sessionManager);
        $session = new Container('user', null, $sessionManager);
        $session->offsetSet('id', 1);
    }

    public function testIndexActionReturnsViewModel(): void
    {
        $this->dispatch('/task/index', 'GET');

        $this->assertResponseStatusCode(302);
        $this->assertModuleName('Application');
        $this->assertControllerName(TaskController::class);
        $this->assertMatchedRouteName('task');
        $this->assertActionName('index');
    }
}