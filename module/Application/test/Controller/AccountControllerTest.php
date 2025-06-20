<?php

declare(strict_types=1);

namespace ApplicationTest\Controller;

use Application\Controller\AccountController;
use Laminas\Test\PHPUnit\Controller\AbstractHttpControllerTestCase;

class AccountControllerTest extends AbstractHttpControllerTestCase
{
    protected function setUp(): void
    {
        $this->setApplicationConfig(
            include __DIR__ . '/../../../../config/application.config.php'
        );
        parent::setUp();
    }

    public function testCreateActionReturnsViewModel(): void
    {
        $this->dispatch('/account/create', 'GET');

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('Application');
        $this->assertControllerName(AccountController::class);
        $this->assertMatchedRouteName('account');
        $this->assertActionName('create');
    }
}
