<?php

declare(strict_types=1);

namespace Application\Controller;

use Laminas\View\Model\ViewModel;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Sql;
use Laminas\Session\Container;

class DashboardController extends AbstractNoAuthController
{
    private Adapter $dbAdapter;

    public function __construct(Adapter $dbAdapter)
    {
        $this->dbAdapter = $dbAdapter;
    }

    public function indexAction()
    {
        $container = new Container();
        $session = $container->user;
        $userId = $session['id'];
        $sql = new Sql($this->dbAdapter);
 
        $statsSelect = $sql->select()
            ->from('tasks')
            ->columns([
                'status',
                'count' => new \Laminas\Db\Sql\Expression('COUNT(*)')
            ])
            ->where([
                'user_id' => $userId,
                'deleted_at' => null
            ])
            ->group('status');

        $tasksSelect = $sql->select()
            ->from('tasks')
            ->columns(['id', 'title', 'description', 'status', 'created_at', 'task_date'])
            ->where([
                'user_id' => $userId,
                'deleted_at' => null
            ])
            ->order('created_at DESC')
            ->limit(5);

        $userSelect = $sql->select()
        ->from('users')
        ->columns(['id', 'username', 'email', 'created_at'])
        ->where(['id' => $userId]);

        $statement = $sql->prepareStatementForSqlObject($userSelect);
        $userQuery = $statement->execute();
        $userResult = iterator_to_array($userQuery);
        $username = $userResult[0]['username'];
        $initial = mb_substr($username, 0, 1);

        $statement = $sql->prepareStatementForSqlObject($statsSelect);
        $statsResult = $statement->execute();

        $statement = $sql->prepareStatementForSqlObject($tasksSelect);
        $tasksResult = $statement->execute();

        $stats = [
            'pending' => 0,
            'in_progress' => 0,
            'completed' => 0,
            'total' => 0
        ];

        foreach ($statsResult as $row) {
            $stats[$row['status']] = (int)$row['count'];
            $stats['total'] += (int)$row['count'];
        }

        $recentTasks = iterator_to_array($tasksResult);
   
        return new ViewModel([
            'initial' => $initial,
            'stats' => $stats,
            'recentTasks' => $recentTasks
        ]);
    }
}