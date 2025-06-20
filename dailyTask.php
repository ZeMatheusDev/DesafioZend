#!/usr/bin/env php
<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Predicate\Between;
use Laminas\Mail\Message;
use Laminas\Mail\Transport\Smtp as SmtpTransport;
use Laminas\Mail\Transport\SmtpOptions;

$dbConfig = require __DIR__ . '/config/autoload/global.php';
$dbParams = [
    'driver'   => $dbConfig['db']['driver']   ?? 'Pdo_Mysql',
    'hostname' => $dbConfig['db']['hostname'] ?? '127.0.0.1',
    'database' => $dbConfig['db']['database'] ?? 'task',
    'username' => $dbConfig['db']['username'] ?? 'root',
    'password' => $dbConfig['db']['password'] ?? '',
    'charset'  => $dbConfig['db']['charset']  ?? 'utf8mb4',
];

$adapter   = new Adapter($dbParams);
$sql       = new Sql($adapter);
$hoje      = new \DateTime('now');
$amanha    = $hoje->modify('+1 day')->format('Y-m-d');
$start     = $amanha . ' 00:00:00';
$end       = $amanha . ' 23:59:59';

$select = $sql->select()
    ->from(['t' => 'tasks'])
    ->columns(['id', 'task_date', 'title'])
    ->join(['u' => 'users'], 't.user_id = u.id', ['email'])
    ->where(new Between('t.task_date', $start, $end));

$stmt    = $sql->prepareStatementForSqlObject($select);
$results = $stmt->execute();

$transport = new SmtpTransport();
$transport->setOptions(new SmtpOptions([
    'name'              => 'smtp.gmail.com',
    'host'              => 'smtp.gmail.com',
    'port'              => 587,
    'connection_class'  => 'login',
    'connection_config' => [
        'username' => 'ovelhoeomar123@gmail.com',
        'password' => 'ipth hqtu pffe dwcg', 
        'ssl'      => 'tls',
    ],
]));

$fromAddress = 'ovelhoeomar123@gmail.com';
$sentCount   = 0;

foreach ($results as $row) {
    $msg = new Message();
    $msg->addFrom($fromAddress)
        ->addTo($row['email'])
        ->setSubject('📆 Lembrete: você tem uma tarefa amanhã')
        ->setBody(sprintf(
            "Olá,\n\nA tarefa \"%s\" está agendada para %s.\n\nAté mais!",
            $row['title'],
            (new \DateTime($row['task_date']))->format('d/m/Y H:i')
        ));

    try {
        $transport->send($msg);
        echo sprintf("[%s] ✔ Email enviado para %s (task #%d)\n",
            date('Y-m-d H:i:s'),
            $row['email'],
            $row['id']
        );
        $sentCount++;
    } catch (\Throwable $e) {
        file_put_contents('php://stderr', sprintf(
            "[%s] ✖ Falha ao enviar para %s (task #%d): %s\n",
            date('c'),
            $row['email'],
            $row['id'],
            $e->getMessage()
        ));
    }
}

echo sprintf("[%s] Total de e‑mails enviados: %d\n",
    date('Y-m-d H:i:s'),
    $sentCount
);
