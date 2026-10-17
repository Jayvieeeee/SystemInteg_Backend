<?php
return [
    // Auth
    ['path' => '/register',          'method' => 'POST', 'controller' => 'AuthController', 'action' => 'register', 'auth' => false],
    ['path' => '/login',             'method' => 'POST', 'controller' => 'AuthController', 'action' => 'login', 'auth' => false],
    ['path' => '/verify-email',      'method' => 'GET',  'controller' => 'AuthController', 'action' => 'verifyEmail', 'auth' => false],
    ['path' => '/forgot-password',   'method' => 'POST', 'controller' => 'AuthController', 'action' => 'forgotPassword', 'auth' => false],
    ['path' => '/send-verification-email', 'method' => 'POST', 'controller' => 'AuthController', 'action' => 'sendVerificationEmailRequest', 'auth' => true],
    ['path' => '/verify-reset-code', 'method' => 'POST', 'controller' => 'AuthController', 'action' => 'verifyResetCode', 'auth' => false],
    ['path' => '/reset-password',    'method' => 'POST', 'controller' => 'AuthController', 'action' => 'resetPassword', 'auth' => false],
    ['path' => '/resend-reset-code', 'method' => 'POST', 'controller' => 'AuthController', 'action' => 'resendResetCode', 'auth' => true],
    ['path' => '/logout', 'method' => 'POST', 'controller' => 'AuthController', 'action' => 'logout', 'auth' => true],

    // Gallons
    ['path' => '/gallons',           'method' => 'GET',    'controller' => 'GallonController',  'action' => 'getAllGallons',     'auth' => true],
    ['path' => '/gallons/{id}',      'method' => 'GET',    'controller' => 'GallonController',  'action' => 'getGallon',         'auth' => true],
    ['path' => '/gallons',           'method' => 'POST',   'controller' => 'GallonController',  'action' => 'createGallon',      'auth' => true, 'role' => 'admin'],
    ['path' => '/gallons/{id}',      'method' => 'POST',    'controller' => 'GallonController',  'action' => 'updateGallon',      'auth' => true, 'role' => 'admin'],
    ['path' => '/gallons/{id}',      'method' => 'DELETE', 'controller' => 'GallonController',  'action' => 'deleteGallon',      'auth' => true],
    ['path' => '/images/{filename}', 'method' => 'GET', 'controller' => 'ImageController', 'action' => 'serveImage', 'auth'  => false],

    // Orders - IMPORTANT: specific routes BEFORE generic routes with {id}
    ['path' => '/orders/stats',      'method' => 'GET',    'controller' => 'OrderController',   'action' => 'getOrderStats',     'auth' => true],
    ['path' => '/orders/latest',     'method' => 'GET',    'controller' => 'OrderController',   'action' => 'getLatestOrderByCustomer',   'auth' => true],
    ['path' => '/orders',            'method' => 'POST',   'controller' => 'OrderController',   'action' => 'createOrder',       'auth' => true],
    ['path' => '/orders',            'method' => 'GET',    'controller' => 'OrderController',   'action' => 'getUserOrders',     'auth' => true],
    ['path' => '/orders/{id}',       'method' => 'GET',    'controller' => 'OrderController',   'action' => 'getOrder',          'auth' => true],
    ['path' => '/orders/{id}/cancel', 'method' => 'POST', 'controller' => 'OrderController', 'action' => 'cancelOrderCustomer', 'auth' => true],

    // Addresses
    ['path' => '/addresses',         'method' => 'GET',    'controller' => 'AddressController', 'action' => 'getUserAddresses',  'auth' => true],
    ['path' => '/addresses',         'method' => 'POST',   'controller' => 'AddressController', 'action' => 'createAddress',     'auth' => true],
    ['path' => '/addresses/{id}/default', 'method' => 'POST', 'controller' => 'AddressController', 'action' => 'setDefaultAddress', 'auth' => true],
    ['path' => '/addresses/{id}',    'method' => 'GET',    'controller' => 'AddressController', 'action' => 'getAddress',        'auth' => true],
    ['path' => '/addresses/{id}',    'method' => 'PUT',    'controller' => 'AddressController', 'action' => 'updateAddress',     'auth' => true],
    ['path' => '/addresses/{id}',    'method' => 'DELETE', 'controller' => 'AddressController', 'action' => 'deleteAddress',     'auth' => true],

    // Profile
    ['path' => '/profile',        'method' => 'GET',  'controller' => 'ProfileController', 'action' => 'profile', 'auth' => true],
    ['path' => '/update-profile', 'method' => 'PUT', 'controller' => 'ProfileController', 'action' => 'updateProfile', 'auth' => true],
    ['path' => '/change-password', 'method' => 'PUT', 'controller' => 'ProfileController', 'action' => 'changePassword', 'auth' => true],

    // Cart
    ['path' => '/cartItems/decrease', 'method' => 'PUT',    'controller' => 'CartController', 'action' => 'decreaseQuantity', 'auth' => true],
    ['path' => '/cartItems/increase', 'method' => 'PUT',    'controller' => 'CartController', 'action' => 'increaseQuantity', 'auth' => true],
    ['path' => '/cartItems',          'method' => 'GET',    'controller' => 'CartController', 'action' => 'getUserCart',      'auth' => true],
    ['path' => '/cartItems',          'method' => 'POST',   'controller' => 'CartController', 'action' => 'addToCart',        'auth' => true],
    ['path' => '/cartItems',          'method' => 'DELETE', 'controller' => 'CartController', 'action' => 'removeFromCart',   'auth' => true],

    // PayPal Payment
    ['path' => '/orders/paypal/create',  'method' => 'POST', 'controller' => 'PaypalController', 'action' => 'create',  'auth' => false],
    ['path' => '/orders/paypal/confirm', 'method' => 'GET',  'controller' => 'PaypalController', 'action' => 'confirm', 'auth' => false],
    ['path' => '/orders/paypal/cancel',  'method' => 'GET',  'controller' => 'PaypalController', 'action' => 'cancel',  'auth' => false],

    // Admin Orders Page
    ['path' => '/admin/orders/{id}/update-stats', 'method' => 'PUT', 'controller' => 'AdminOrdersController', 'action' => 'updateStatus', 'auth' => true, 'role' => 'admin'],
    ['path' => '/admin/orders',                    'method' => 'GET', 'controller' => 'AdminOrdersController', 'action' => 'index',        'auth' => true, 'role' => 'admin'],


    // Admin Customers Page
    ['path' => '/admin/customers', 'method' => 'GET', 'controller' => 'AdminCustomerController', 'action' => 'index', 'auth' => true, 'role' => 'admin'],
    ['path' => '/admin/customers/{id}', 'method' => 'GET', 'controller' => 'AdminCustomerController', 'action' => 'show', 'auth' => true, 'role' => 'admin'],


    ['path' => '/dashboard/stats', 'method' => 'GET', 'controller' => 'AdminDashboardController', 'action' => 'getDashboardStats', 'auth' => true],

    // Admin RidersPage
    ['path' => '/admin/riders', 'method' => 'GET', 'controller' => 'AdminRiderController', 'action' => 'index', 'auth' => true, 'role' => 'admin'],
    ['path' => '/admin/riders/{id}', 'method' => 'GET', 'controller' => 'AdminRiderController', 'action' => 'show', 'auth' => true, 'role' => 'admin'],
    ['path' => '/admin/riders/{id}/update-status', 'method' => 'PUT', 'controller' => 'AdminRiderController', 'action' => 'updateRiderStatus', 'auth' => true, 'role' => 'admin'],
    ['path' => '/admin/add-rider', 'method' => 'POST', 'controller' => 'AdminRiderController', 'action' => 'addRider', 'auth' => true, 'role' => 'admin'],

    //Rider Page
    ['path' => '/rider/orders', 'method' => 'GET', 'controller' => 'RiderPageController', 'action' => 'getOrders', 'auth' => true, 'role' => 'rider'],
    ['path' => '/rider/orders/{id}/update-status', 'method' => 'PUT', 'controller' => 'RiderPageController', 'action' => 'updateStatus', 'auth' => true, 'role' => 'rider'],
    ['path' => '/rider/delivery-history', 'method' => 'GET', 'controller' => 'RiderPageController', 'action' => 'getDeliveryHistory', 'auth' => true, 'role' => 'rider'],

        // Xendit Payment
    ['path' => '/payments/xendit/create',          'method' => 'POST', 'controller' => 'XenditController', 'action' => 'create',  'auth' => true],
    ['path' => '/payments/xendit/webhook',         'method' => 'POST', 'controller' => 'XenditController', 'action' => 'webhook', 'auth' => false],
    ['path' => '/payments/xendit/status/{orderId}', 'method' => 'GET',  'controller' => 'XenditController', 'action' => 'status',  'auth' => true],

];
