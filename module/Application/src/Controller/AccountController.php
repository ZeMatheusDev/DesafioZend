<?php

declare(strict_types=1);

namespace Application\Controller;

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

class AccountController extends AbstractAuthController
{
    private Adapter $dbAdapter;

    public function __construct(Adapter $dbAdapter)
    {
        $this->dbAdapter = $dbAdapter;
    }

    public function createAction()
    {
        return new ViewModel();
    }

    public function editAction()
    {
        $container = new Container();
        $session   = $container->user;
        $userId    = $session['id'] ?? null;
        $sql = new Sql($this->dbAdapter);
        $userSelect = $sql->select()
        ->from('users')
        ->columns(['id', 'username', 'email', 'created_at'])
        ->where(['id' => $userId]);
        $statement = $sql->prepareStatementForSqlObject($userSelect);
        $userQuery = $statement->execute();
        $userResult = iterator_to_array($userQuery);
        $user = $userResult[0];
        return new ViewModel(['user' => $user]);
    }

    public function updateAction(): JsonModel
    {
        $container = new Container();
        $session   = $container->user;
        $sessionId = $session['id'] ?? null;

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

        if ((int)$data['id'] !== (int)$sessionId) {
            return new JsonModel([
                'success'  => false,
                'messages' => ['authorization' => ['Invalid user.']],
            ]);
        }

        $usernameValidator = new NotEmpty();
        $emailValidator    = new EmailAddress();
        $passwordValidator = new StringLength(['min' => 6]);

        if (empty($data['username']) || !$usernameValidator->isValid($data['username'])) {
            $messages = $usernameValidator->getMessages() ?: ['Username is required'];
            $hasErrors = true;
        }
        if (empty($data['email']) || !$emailValidator->isValid($data['email'])) {
            $messages = $emailValidator->getMessages() ?: ['Incorrect e-mail'];
            $hasErrors = true;
        }
        if (!empty($data['password']) && !$passwordValidator->isValid($data['password'])) {
            $messages = $passwordValidator->getMessages() ?: ['Incorrect password'];
            $hasErrors = true;
        }
        if ($hasErrors) {
            return new JsonModel([
                'success'  => false,
                'messages' => $messages,
            ]);
        }

        $sql    = new Sql($this->dbAdapter);
        $update = $sql->update('users')->set([
            'username' => $data['username'],
            'email'    => $data['email'],
        ])->where(['id' => $sessionId]);

        if (!empty($data['password'])) {
            $update->set(['password' => password_hash($data['password'], PASSWORD_BCRYPT)]);
        }

        try {
            $stmt   = $sql->prepareStatementForSqlObject($update);
            $result = $stmt->execute();

            if ($result->getAffectedRows() === 0) {
                return new JsonModel([
                    'success'  => false,
                    'messages' => ['update' => ['Nothing to update or user not found.']],
                ]);
            }

            return new JsonModel([
                'success' => true,
                'message' => 'Account updated successfully',
            ]);
        } catch (\Exception $e) {
            return new JsonModel([
                'success'  => false,
                'messages' => ['database' => ['Error updating account: ' . $e->getMessage()]],
            ]);
        }
    }

    public function storeAction(): JsonModel
    {
        $request = $this->getRequest();
        if (!$request->isPost()) {
            return new JsonModel([
                'success'  => false,
                'messages' => ['Method not allowed'],
            ]);
        }

        $data = $request->getPost()->toArray();
        $messages = [];
        $hasErrors = false;

        $usernameValidator = new NotEmpty();
        $emailValidator = new EmailAddress();
        $passwordValidator = new StringLength(['min' => 6]);
        $passwordNotEmpty = new NotEmpty();

        if (empty($data['username']) || !$usernameValidator->isValid($data['username'])) {
            $messages['username'] = $usernameValidator->getMessages() ?: ['Username is required'];
            $hasErrors = true;
        }
        if (empty($data['email']) || !$emailValidator->isValid($data['email'])) {
            $messages['email'] = $emailValidator->getMessages() ?: ['Incorrect e-mail'];
            $hasErrors = true;
        }
        if (empty($data['password']) || !$passwordValidator->isValid($data['password'])) {
            $messages['password'] = $passwordValidator->getMessages() ?: ['Incorrent password'];
            $hasErrors = true;
        }
        if ($hasErrors) {
            return new JsonModel([
                'success'  => false,
                'messages' => $messages,
            ]);
        }

        $passwordHash = password_hash($data['password'], PASSWORD_BCRYPT);
        $sql    = new Sql($this->dbAdapter);
        $insert = $sql->insert('users');
        $insert->values([
            'username'   => $data['username'],
            'email'      => $data['email'],
            'password'   => $passwordHash,
        ], Insert::VALUES_MERGE);

        try {
            $statement = $sql->prepareStatementForSqlObject($insert);
            $result    = $statement->execute();
            $newUserId = $result->getGeneratedValue();

            return new JsonModel([
                'success' => true,
                'message' => 'Account has been created!',
                'user_id' => $newUserId,
            ]);
        } catch (\Exception $e) {
            return new JsonModel([
                'success'  => false,
                'messages' => ['Contact to support: ' . $e->getMessage()],
            ]);
        }
    }

    public function loginAction(): JsonModel
    {
        $request = $this->getRequest();
        if (! $request->isPost()) {
            return new JsonModel([
                'success'  => false,
                'messages' => ['Method not allowed'],
            ]);
        }

        $data = $request->getPost()->toArray();
        $messages = [];
        $hasErrors = false;

        $acessoNotEmpty  = new NotEmpty();
        $emailValidator  = new EmailAddress();
        $usernameRegex   = new Regex([ 'pattern' => '/^[a-zA-Z0-9\- _.]{3,50}$/' ]);
        $passwordNotEmpty= new NotEmpty();
        $passwordLength  = new StringLength(['min' => 6, 'max' => 128]);

        $acesso   = trim($data['acesso'] ?? '');
        $password = $data['password'] ?? '';

        if (! $acessoNotEmpty->isValid($acesso)) {
            $messages['acesso'] = ['The field is required.'];
            $hasErrors = true;
        } else {
            if (strpos($acesso, '@') !== false) {
                if (! $emailValidator->isValid($acesso)) {
                    $messages['acesso'] = ['Incorrect e-mail.'];
                    $hasErrors = true;
                }
            } elseif (! $usernameRegex->isValid($acesso)) {
                $messages['acesso'] = ['Incorrect username or e-mail: use 3-50 characteres or -_.'];
                $hasErrors = true;
            }
        }

        if (! $passwordNotEmpty->isValid($password)) {
            $messages['password'] = ['The field password is required.'];
            $hasErrors = true;
        } elseif (! $passwordLength->isValid($password)) {
            $messages['password'] = ['Password must be between 6 and 128 characters long.'];
            $hasErrors = true;
        }

        if ($hasErrors) {
            return new JsonModel([ 
                'success' => false, 
                'messages' => $messages 
            ]);
        }

        $sql    = new Sql($this->dbAdapter);
        $select = $sql->select()->from('users')
                       ->columns(['id','username','email','password']);
        $select->where
               ->nest()
                   ->equalTo('username', $acesso)
                   ->or
                   ->equalTo('email', $acesso)
               ->unnest();

        $statement = $sql->prepareStatementForSqlObject($select);
        $user      = $statement->execute()->current();
  
        if (! $user || ! password_verify($password, $user['password'])) {
            return new JsonModel([
                'success'  => false,
                'messages' => ['Incorrect credentials.'],
            ]);
        }

        $session = new Container();

        $session->user = [
                'id'       => $user['id'],
                'username' => $user['username'],
                'email'    => $user['email'],
            ];

        return new JsonModel([
            'success' => true,
            'message' => 'Login has been successfull.',
        ]);
    }

    public function logoutAction()
    {
        $session = new Container();
        $manager = $session->getManager();
        $manager->destroy();

        return $this->redirect()->toRoute('home');
    }
}
