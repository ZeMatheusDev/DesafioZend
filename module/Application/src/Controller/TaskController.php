<?php

declare(strict_types=1);

namespace Application\Controller;

use DateTime;
use Laminas\View\Model\ViewModel;
use Laminas\Db\Adapter\Adapter;
use Laminas\View\Model\JsonModel;
use Laminas\Validator\StringLength;
use Laminas\Validator\EmailAddress;
use Laminas\Validator\NotEmpty;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Insert;
use Laminas\Validator\Regex;
use Laminas\Session\Container;
use Laminas\Db\Sql\Expression;

class TaskController extends AbstractNoAuthController
{
    private Adapter $dbAdapter;

    public function __construct(Adapter $dbAdapter)
    {
        $this->dbAdapter = $dbAdapter;
    }

  public function indexAction()
    {
        $container = new Container();
        $session   = $container->user;
        $userId    = $session['id'];

        $page       = (int) $this->params()->fromQuery('page',       1);
        $perPage    = (int) $this->params()->fromQuery('perPage',   10);
        $offset     = ($page - 1) * $perPage;

        $titulo       = trim($this->params()->fromQuery('title', ''));
        $descricao    = trim($this->params()->fromQuery('description', ''));
        $taskDate     = trim($this->params()->fromQuery('task_date', ''));      
        $status       = trim($this->params()->fromQuery('status', ''));

        $sql = new Sql($this->dbAdapter);

        $userSelect = $sql->select()
            ->from('users')
            ->columns(['username'])
            ->where([ 'id' => $userId ]);
        $userResult = $sql
            ->prepareStatementForSqlObject($userSelect)
            ->execute()
            ->current();
        $initial = mb_substr($userResult['username'], 0, 1);

        $countSelect = $sql->select()
            ->from('tasks')
            ->columns([ 'count' => new Expression('COUNT(*)') ])
            ->where([
                'user_id'    => $userId,
                'deleted_at' => null,
            ]);
      
        if ($titulo !== '') {
            $countSelect->where->like('title', "%{$titulo}%");
        }
        if ($descricao !== '') {
            $countSelect->where->like('description', "%{$descricao}%");
        }
        if ($taskDate !== '') {
            $countSelect->where->equalTo(new Expression('DATE(task_date)'), $taskDate);
        }
        if ($status !== '') {
            $countSelect->where([ 'status' => $status ]);
        }

        $total = (int) $sql
            ->prepareStatementForSqlObject($countSelect)
            ->execute()
            ->current()['count'];
        $totalPages = (int) ceil($total / $perPage);

        $select = $sql->select()
            ->from('tasks')
            ->columns([ 'id','token','title','description','status','created_at','task_date' ])
            ->where([
                'user_id'    => $userId,
                'deleted_at' => null,
            ])
            ->order('created_at DESC')
            ->limit($perPage)
            ->offset($offset);

        if ($titulo !== '') {
            $select->where->like('title', "%{$titulo}%");
        }
        if ($descricao !== '') {
            $select->where->like('description', "%{$descricao}%");
        }
        if ($taskDate !== '') {
            $select->where->equalTo(new Expression('DATE(task_date)'), $taskDate);
        }
        if ($status !== '') {
            $select->where([ 'status' => $status ]);
        }

        $tasks = iterator_to_array(
            $sql->prepareStatementForSqlObject($select)->execute()
        );

        return new ViewModel([
            'initial'     => $initial,
            'tasks'       => $tasks,
            'currentPage' => $page,
            'totalPages'  => $totalPages,
            'perPage'     => $perPage,
            'titulo'      => $titulo,
            'description' => $descricao,
            'task_date'   => $taskDate,
            'status'      => $status,
        ]);
    }

    public function createAction(){
        $container = new Container();
        $session = $container->user;
        $userId = $session['id'];
        $sql = new Sql($this->dbAdapter);

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
            'initial' => $initial
        ]);
    }

    public function storeAction()
    {
        $container = new Container();
        $session   = $container->user;
        $userId    = $session['id'] ?? null;

        $request = $this->getRequest();
        if (!$request->isPost()) {
            return new JsonModel([
                'success'  => false,
                'messages' => ['Method not allowed.'],
            ]);
        }

        $data      = $request->getPost()->toArray();
        $messages  = [];
        $hasErrors = false;

        if (empty($data['title'])) {
            $messages['title'][] = 'Title is required';
            $hasErrors = true;
        }
        if (empty($data['task_date'])) {
            $messages['task_date'][] = 'Task date is required';
            $hasErrors = true;
        }

        if ($hasErrors) {
            return new JsonModel([
                'success'  => false,
                'messages' => $messages,
            ]);
        }

        $dateString = $data['task_date']; 
        $taskDate   = \DateTime::createFromFormat('Y-m-d', $dateString);
        $errors     = \DateTime::getLastErrors();
        if ($errors['warning_count'] > 0 || $errors['error_count'] > 0) {
            return new JsonModel([
                'success'  => false,
                'messages' => ['task_date' => ['Invalid format. Use YYYY-MM-DD.']],
            ]);
        }

        $dayOfWeek = (int) $taskDate->format('N');
        if ($dayOfWeek >= 6) {
            return new JsonModel([
                'success'  => false,
                'messages' => ['task_date' => ['Tasks can only be created on weekdays!']],
            ]);
        }

        $now = new \DateTime();
        if ($taskDate < $now) {
            return new JsonModel([
                'success'  => false,
                'messages' => ['task_date' => ['Task date cannot be in the past!']],
            ]);
        }

        $token = md5($now->format('Y-m-d H:i:s') . rand(0, 999999999));

        $sql    = new Sql($this->dbAdapter);
        $insert = $sql->insert('tasks')->values([
            'user_id'     => $userId,
            'title'       => $data['title'],
            'description' => $data['description'] ?? null,
            'task_date'   => $taskDate->format('Y-m-d'),
            'token'       => $token,
            'status'      => $data['status'] ?? 'pending',
            'created_at'  => $now->format('Y-m-d H:i:s'),
        ]);

        try {
            $stmt      = $sql->prepareStatementForSqlObject($insert);
            $result    = $stmt->execute();
            $newTaskId = $result->getGeneratedValue();

            return new JsonModel([
                'success' => true,
                'message' => 'Task has been created.',
                'data'    => [
                    'id'          => $newTaskId,
                    'title'       => $data['title'],
                    'description' => $data['description'] ?? null,
                    'task_date'   => $taskDate->format('Y-m-d'),
                    'status'      => $data['status'] ?? 'pending',
                    'token'       => $token,
                    'created_at'  => $now->format('Y-m-d H:i:s'),
                ],
            ]);
        } catch (\Exception $e) {
            return new JsonModel([
                'success'  => false,
                'messages' => ['database' => ['Error creating task: ' . $e->getMessage()]],
            ]);
        }
    }

    public function updateStatusAction()
    {
        $token = $this->params()->fromRoute('token');
        $newStatus = $this->params()->fromRoute('status');
        
        $validStatuses = ['pending', 'in_progress', 'completed'];

        if (!in_array($newStatus, $validStatuses)) {
            $this->flashMessenger()->addErrorMessage('Invalid status!');
            return $this->redirect()->toRoute('task');
        }

        $sql = new Sql($this->dbAdapter);
        $update = $sql->update('tasks')
            ->set(['status' => $newStatus])
            ->where(['token' => $token]);
        
        $statement = $sql->prepareStatementForSqlObject($update);
        $statement->execute();

        return $this->redirect()->toRoute('task');
    }

    public function editAction(){
        $container = new Container();
        $session = $container->user;
        $userId = $session['id'];
        $sql = new Sql($this->dbAdapter);
        $token = $this->params()->fromRoute('token');
        $task = $sql->select()
        ->from('tasks')
        ->columns(['title', 'description', 'task_date', 'status', 'token'])
        ->where(['token' => $token]);
        $statement = $sql->prepareStatementForSqlObject($task);
        $taskQuery = $statement->execute();
        $taskResult = iterator_to_array($taskQuery);
        if($taskResult[0]['status'] != 'pending'){
            return $this->redirect()->toRoute('task');
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
            'task' => $taskResult,
            'initial' => $initial
        ]);
    }

    public function updateAction()
    {
        $container = new Container();
        $session   = $container->user;
        $userId    = $session['id'] ?? null;

        $request = $this->getRequest();
        if (!$request->isPost()) {
            return new JsonModel([
                'success'  => false,
                'messages' => ['Method not allowed.'],
            ]);
        }

        $token     = $this->params()->fromRoute('token');
        $data      = $request->getPost()->toArray();
        $messages  = [];
        $hasErrors = false;

        if (empty($data['title'])) {
            $messages['title'][] = 'Title is required';
            $hasErrors = true;
        }
        if (empty($data['task_date'])) {
            $messages['task_date'][] = 'Task date is required';
            $hasErrors = true;
        }
        if ($hasErrors) {
            return new JsonModel([
                'success'  => false,
                'messages' => $messages,
            ]);
        }

        $taskDate = \DateTime::createFromFormat('Y-m-d', $data['task_date']);
        $errors   = \DateTime::getLastErrors();
        if ($errors['warning_count'] > 0 || $errors['error_count'] > 0) {
            return new JsonModel([
                'success'  => false,
                'messages' => ['task_date' => ['Invalid format. Use YYYY-MM-DD.']],
            ]);
        }

        $dow = (int) $taskDate->format('N');
        if ($dow >= 6) {
            return new JsonModel([
                'success'  => false,
                'messages' => ['task_date' => ['Tasks can only be on weekdays!']],
            ]);
        }

        $now = new \DateTime();
        if ($taskDate < $now) {
            return new JsonModel([
                'success'  => false,
                'messages' => ['task_date' => ['Task date cannot be in the past!']],
            ]);
        }

        $sql    = new Sql($this->dbAdapter);
        $update = $sql->update('tasks')
            ->set([
                'title'       => $data['title'],
                'description' => $data['description'] ?? null,
                'task_date'   => $taskDate->format('Y-m-d'),
                'updated_at'  => $now->format('Y-m-d H:i:s'),
            ])
            ->where([
                'token'   => $token,
                'status'  => 'pending',
                'user_id' => $userId,
            ]);

        try {
            $stmt   = $sql->prepareStatementForSqlObject($update);
            $result = $stmt->execute();

            if ($result->getAffectedRows() === 0) {
                return new JsonModel([
                    'success'  => false,
                    'messages' => ['token' => ['Task not found or not pending.']],
                ]);
            }

            return new JsonModel([
                'success' => true,
                'message' => 'Task has been updated.',
                'data'    => [
                    'title'       => $data['title'],
                    'description' => $data['description'] ?? null,
                    'task_date'   => $taskDate->format('Y-m-d'),
                    'updated_at'  => $now->format('Y-m-d H:i:s'),
                ],
            ]);
        } catch (\Exception $e) {
            return new JsonModel([
                'success'  => false,
                'messages' => ['database' => ['Error updating task: ' . $e->getMessage()]],
            ]);
        }
    }

    public function deleteAction()
    {
        $container = new Container();
        $session   = $container->user;
        $userId    = $session['id'] ?? null;

        $request = $this->getRequest();

        $token = $this->params()->fromRoute('token');
        $now   = new \DateTime();
        $limit = (clone $now)->modify('-5 days')->format('Y-m-d H:i:s');

        $sql    = new Sql($this->dbAdapter);
        $update = $sql->update('tasks')
            ->set(['deleted_at' => $now->format('Y-m-d H:i:s')])
            ->where([
                'token'   => $token,
                'user_id' => $userId,
                'status'  => 'pending',
            ]);
        $update->where->lessThanOrEqualTo('created_at', $limit);

        try {
            $stmt   = $sql->prepareStatementForSqlObject($update);
            $result = $stmt->execute();

            if ($result->getAffectedRows() === 0) {
                return new JsonModel([
                    'success'  => false,
                    'messages' => ['delete' => ['Task not found, not pending or less than 5 days old.']],
                ]);
            }

            return new JsonModel([
                'success' => true,
                'message' => 'Task deleted.',
            ]);
        } catch (\Exception $e) {
            return new JsonModel([
                'success'  => false,
                'messages' => ['database' => ['Error deleting task: ' . $e->getMessage()]],
            ]);
        }
    }
}
