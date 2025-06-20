<?php

declare(strict_types=1);

namespace Application\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Db\Adapter\Adapter;
use Laminas\View\Model\JsonModel;
use Laminas\Session\Container;
use Application\Middleware\AbstractAuthController;

class HomeController extends AbstractAuthController
{
    private Adapter $dbAdapter;

    public function __construct(Adapter $dbAdapter)
    {
        $this->dbAdapter = $dbAdapter;
    }

    public function indexAction()
    {
        $sql    = 'SELECT * FROM users ORDER BY created_at DESC';
        $result = $this->dbAdapter
                       ->query($sql, Adapter::QUERY_MODE_EXECUTE);

        $rows = [];
        foreach ($result as $row) {
            $rows[] = (array) $row;
        }

        return new ViewModel([
            'dados' => $rows,
        ]);
    }
}
