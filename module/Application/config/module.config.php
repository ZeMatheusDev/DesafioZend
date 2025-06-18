<?php

declare(strict_types=1);

namespace Application;

use Application\Controller\HomeController;
use Application\Controller\AccountController;
use Laminas\Router\Http\Literal;
use Laminas\Router\Http\Segment;
use Laminas\ServiceManager\Factory\InvokableFactory;
use Laminas\Db\Adapter\Adapter;
use Laminas\Router\Http\Method;

return [
    'router' => [
        'routes' => [
            'application' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/application[/:action]',
                    'defaults' => [
                        'controller' => Controller\IndexController::class,
                        'action'     => 'index',
                    ],
                ],
            ],
            'home' => [
                'type'    => Literal::class,
                'options' => [
                    'route'    => '/',
                    'defaults' => [
                        'controller' => Controller\HomeController::class,
                        'action'     => 'index',
                    ],
                ],
            ],
            'createAccount' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/createAccount[/:action]',
                    'defaults' => [
                        'controller' => Controller\AccountController::class,
                        'action'     => 'create',
                    ],
                ],
                'may_terminate' => true,
                'child_routes' => [
                    'processar' => [
                        'type' => Method::class,
                        'options' => [
                            'verb' => 'post',
                            'route' => '/processar',
                            'defaults' => [
                                'action' => 'processar',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'controllers' => [
        'factories' => [
            HomeController::class => function($c) {
                return new HomeController(
                    $c->get(Adapter::class)
                );
            },
            AccountController::class => function($c) {
                return new AccountController(
                    $c->get(Adapter::class)
                );
            },
        ],
    ],
    'view_manager' => [
        'display_not_found_reason' => true,
        'display_exceptions'       => true,
        'doctype'                  => 'HTML5',
        'not_found_template'       => 'error/404',
        'exception_template'       => 'error/index',
        'strategies' => [
            'ViewJsonStrategy',
        ],
        'template_map' => [
            'layout/layout'           => __DIR__ . '/../view/layout/layout.phtml',
            'application/index/index' => __DIR__ . '/../view/application/index/index.phtml',
            'error/404'               => __DIR__ . '/../view/error/404.phtml',
            'error/index'             => __DIR__ . '/../view/error/index.phtml',
        ],
        'template_path_stack' => [
            __DIR__ . '/../view',
        ],
    ],
];
