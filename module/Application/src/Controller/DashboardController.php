<?php

declare(strict_types=1);

namespace Application\Controller;

use Laminas\View\Model\ViewModel;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Sql;
use Laminas\Session\Container;
use Application\Middleware\AbstractNoAuthController;

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

    public function calendarAction()
    {
        $container = new Container();
        $session = $container->user;
        $userId = $session['id'];
        $sql = new Sql($this->dbAdapter);

        $select = $sql->select()
            ->from('tasks')
            ->columns(['id', 'title', 'task_date', 'status'])
            ->where([
                'user_id' => $userId,
                'deleted_at' => null
            ])
            ->where('task_date IS NOT NULL'); 

        $statement = $sql->prepareStatementForSqlObject($select);
        $results = $statement->execute();

        $tasks = [];
        foreach ($results as $row) {
            $date = new \DateTime($row['task_date']);
            $row['iso_date'] = $date->format('c');
            
            $row['status_class'] = match($row['status']) {
                'pending' => 'fc-event-pending',
                'in_progress' => 'fc-event-in-progress',
                'completed' => 'fc-event-completed',
                default => ''
            };
            
            $tasks[] = $row;
        }
        $userSelect = $sql->select()
        ->from('users')
        ->columns(['id', 'username', 'email', 'created_at'])
        ->where(['id' => $userId]);

        $statement = $sql->prepareStatementForSqlObject($userSelect);
        $userQuery = $statement->execute();
        $userResult = iterator_to_array($userQuery);
        $username = $userResult[0]['username'];
        $initial = mb_substr($username, 0, 1);
        return new ViewModel([
            'tasks' => $tasks,
            'initial' => $initial,
        ]);
    }
}