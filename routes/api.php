<?php
return [
    // Auth
    ['path' => '/register', 'method' => 'POST', 'controller' => 'AuthController', 'action' => 'register', 'auth' => false],
    ['path' => '/login', 'method' => 'POST', 'controller' => 'AuthController', 'action' => 'login', 'auth' => false],
    ['path' => '/forgot-password', 'method' => 'POST', 'controller' => 'AuthController', 'action' => 'forgotPassword', 'auth' => false],
    ['path' => '/verify-reset-code', 'method' => 'POST', 'controller' => 'AuthController', 'action' => 'verifyResetCode', 'auth' => false],
    ['path' => '/reset-password', 'method' => 'POST', 'controller' => 'AuthController', 'action' => 'resetPassword', 'auth' => false],

    // Containers
    ['path' => '/containers', 'method' => 'GET', 'controller' => 'ContainerController', 'action' => 'getAllContainers', 'auth' => false],
    ['path' => '/containers/{id}', 'method' => 'GET', 'controller' => 'ContainerController', 'action' => 'getContainer', 'auth' => false],
    ['path' => '/containers', 'method' => 'POST', 'controller' => 'ContainerController', 'action' => 'createContainer', 'auth' => false], //make false after test
    ['path' => '/containers/{id}', 'method' => 'PUT', 'controller' => 'ContainerController', 'action' => 'updateContainer', 'auth' => true],
    ['path' => '/containers/{id}', 'method' => 'DELETE', 'controller' => 'ContainerController', 'action' => 'deleteContainer', 'auth' => true],

    // Orders (sample, future use)
    ['path' => '/orders', 'method' => 'GET', 'controller' => 'OrderController', 'action' => 'getOrders', 'auth' => true],
    ['path' => '/orders/{id}', 'method' => 'GET', 'controller' => 'OrderController', 'action' => 'getOrderById', 'auth' => true],
];
