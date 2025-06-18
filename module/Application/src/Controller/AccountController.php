<?php

declare(strict_types=1);

namespace Application\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Db\Adapter\Adapter;
use Laminas\View\Model\JsonModel;
use Laminas\Validator\StringLength;
use Laminas\Validator\EmailAddress;
use Laminas\Validator\NotEmpty;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Insert;

class AccountController extends AbstractActionController
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

    public function storeAction(): JsonModel
    {
        $request = $this->getRequest();
        if (!$request->isPost()) {
            return new JsonModel([
                'success'  => false,
                'messages' => ['Método não permitido'],
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
            $messages['username'] = $usernameValidator->getMessages() ?: ['Nome de usuário é obrigatório'];
            $hasErrors = true;
        }
        if (empty($data['email']) || !$emailValidator->isValid($data['email'])) {
            $messages['email'] = $emailValidator->getMessages() ?: ['E-mail inválido'];
            $hasErrors = true;
        }
        if (empty($data['password']) || !$passwordValidator->isValid($data['password'])) {
            $messages['password'] = $passwordValidator->getMessages() ?: ['Senha inválida'];
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
                'message' => 'Cadastro realizado com sucesso!',
                'user_id' => $newUserId,
            ]);
        } catch (\Exception $e) {
            return new JsonModel([
                'success'  => false,
                'messages' => ['Erro ao salvar no banco: ' . $e->getMessage()],
            ]);
        }
    }
}
