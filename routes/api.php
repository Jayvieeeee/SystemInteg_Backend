<?php
return [
    // Auth
    ['path' => '/register',          'method' => 'POST', 'controller' => 'AuthController', 'action' => 'register', 'auth' => false],
    ['path' => '/login',             'method' => 'POST', 'controller' => 'AuthController', 'action' => 'login', 'auth' => false],
    ['path' => '/forgot-password',   'method' => 'POST', 'controller' => 'AuthController', 'action' => 'forgotPassword', 'auth' => false],
    ['path' => '/verify-reset-code', 'method' => 'POST', 'controller' => 'AuthController', 'action' => 'verifyResetCode', 'auth' => false],
    ['path' => '/reset-password',    'method' => 'POST', 'controller' => 'AuthController', 'action' => 'resetPassword', 'auth' => false],
    ['path' => '/resend-reset-code', 'method' => 'POST', 'controller' => 'AuthController', 'action' => 'resendResetCode', 'auth' => true],
    ['path' => '/logout', 'method' => 'POST', 'controller' => 'AuthController', 'action' => 'logout', 'auth' => true],

    // Gallons
    ['path' => '/gallons',           'method' => 'GET',    'controller' => 'GallonController',  'action' => 'getAllGallons',     'auth' => true],
    ['path' => '/gallons/{id}',      'method' => 'GET',    'controller' => 'GallonController',  'action' => 'getGallon',         'auth' => true],
    ['path' => '/gallons',           'method' => 'POST',   'controller' => 'GallonController',  'action' => 'createGallon',      'auth' => true], // make true later
    ['path' => '/gallons/{id}',      'method' => 'PUT',    'controller' => 'GallonController',  'action' => 'updateGallon',      'auth' => true],
    ['path' => '/gallons/{id}',      'method' => 'DELETE', 'controller' => 'GallonController',  'action' => 'deleteGallon',      'auth' => true],
    ['path' => '/images/{filename}', 'method' => 'GET', 'controller' => 'ImageController', 'action' => 'serveImage', 'auth' => true],

    // Orders
    ['path' => '/orders',            'method' => 'POST',   'controller' => 'OrderController',   'action' => 'createOrder',       'auth' => true],
    ['path' => '/orders/{id}',       'method' => 'GET',    'controller' => 'OrderController',   'action' => 'getOrder',          'auth' => true],
    ['path' => '/orders',            'method' => 'GET',    'controller' => 'OrderController',   'action' => 'getUserOrders',     'auth' => true],

    // Addresses
    ['path' => '/addresses',         'method' => 'GET',    'controller' => 'AddressController', 'action' => 'getUserAddresses',  'auth' => true],
    ['path' => '/addresses',         'method' => 'POST',   'controller' => 'AddressController', 'action' => 'createAddress',     'auth' => true],
    ['path' => '/addresses/{id}',    'method' => 'GET',    'controller' => 'AddressController', 'action' => 'getAddress',        'auth' => true],
    ['path' => '/addresses/{id}',    'method' => 'PUT',    'controller' => 'AddressController', 'action' => 'updateAddress',     'auth' => true],
    ['path' => '/addresses/{id}',    'method' => 'DELETE', 'controller' => 'AddressController', 'action' => 'deleteAddress',     'auth' => true],
    ['path' => '/addresses/{id}/default', 'method' => 'POST', 'controller' => 'AddressController', 'action' => 'setDefaultAddress', 'auth' => true],

    ['path' => '/profile',        'method' => 'GET',  'controller' => 'ProfileController', 'action' => 'profile', 'auth' => true],
    ['path' => '/profile', 'method' => 'PUT', 'controller' => 'ProfileController', 'action' => 'updateProfile', 'auth' => true],


    ['path' => '/cartItems', 'method' => 'GET', 'controller' => 'CartController', 'action' => 'getUserCart', 'auth' => true],
    ['path' => '/cartItems', 'method' => 'POST', 'controller' => 'CartController', 'action' => 'addToCart', 'auth' => true],
    ['path' => '/cartItems', 'method' => 'DELETE', 'controller' => 'CartController', 'action' => 'removeFromCart', 'auth' => true],
    ['path' => '/cartItems/decrease', 'method' => 'PUT', 'controller' => 'CartController', 'action' => 'decreaseQuantity', 'auth' => true],
    ['path' => '/cartItems/increase', 'method' => 'PUT', 'controller' => 'CartController', 'action' => 'increaseQuantity', 'auth' => true],

    // Dashboard stats
    ['path' => '/orders/stats', 'method' => 'GET', 'controller' => 'OrderController', 'action' => 'getOrderStats', 'auth' => true],
    ['path' => '/orders/latest', 'method' => 'GET', 'controller' => 'OrderController', 'action' => 'getLatestOrders', 'auth' => true],


];
