<?php
return [
    // Auth
    [
        'path'       => '/register',
        'method'     => 'POST',
        'controller' => 'AuthController',
        'action'     => 'register',
        'auth'       => false
    ],
    [
        'path'       => '/login',
        'method'     => 'POST',
        'controller' => 'AuthController',
        'action'     => 'login',
        'auth'       => false
    ],

    // Orders (sample, future use)
    [
        'path'       => '/orders',
        'method'     => 'GET',
        'controller' => 'OrderController',
        'action'     => 'getOrders',
        'auth'       => true
    ],
    [
        'path'       => '/orders/{id}',
        'method'     => 'GET',
        'controller' => 'OrderController',
        'action'     => 'getOrderById',
        'auth'       => true
    ],
];
