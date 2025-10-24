<?php

require_once __DIR__ . '/../core/Container.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';

// Services
require_once __DIR__ . '/../services/EmailService.php';
require_once __DIR__ . '/../services/PaypalService.php';


// Models
require_once __DIR__ . '/../models/UserModel.php';
require_once __DIR__ . '/../models/GallonModel.php';
require_once __DIR__ . '/../models/OrderModel.php';
require_once __DIR__ . '/../models/AddressModel.php';
require_once __DIR__ . '/../models/CartModel.php';


// Controllers 
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/GallonController.php';
require_once __DIR__ . '/../controllers/OrderController.php';
require_once __DIR__ . '/../controllers/AddressController.php';
require_once __DIR__ . '/../controllers/ProfileController.php';
require_once __DIR__ . '/../controllers/ImageController.php';
require_once __DIR__ . '/../controllers/CartController.php';
require_once __DIR__ . '/../controllers/PaypalController.php';
require_once __DIR__ . '/../controllers/AdminOrdersController.php';
require_once __DIR__ . '/../controllers/AdminCustomerController.php';
require_once __DIR__ . '/../controllers/AdminRiderController.php';
require_once __DIR__ . '/../controllers/RiderPageController.php';


// Middlewares 
require_once __DIR__ . '/../middleware/LoggerMiddleware.php';
require_once __DIR__ . '/../middleware/CorsMiddleware.php';
require_once __DIR__ . '/../middleware/RateLimitMiddleware.php';
require_once __DIR__ . '/../middleware/SanitizeMiddleware.php';
require_once __DIR__ . '/../middleware/ValidateMiddleware.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/RoleMiddleware.php';
require_once __DIR__ . '/../middleware/MiddlewareRunner.php';


// Libraries
require_once __DIR__ . '/../libs/php-jwt-main/src/JWT.php';
require_once __DIR__ . '/../libs/php-jwt-main/src/Key.php';
require_once __DIR__ . '/../libs/PHPMailer-master/src/PHPMailer.php';
require_once __DIR__ . '/../libs/PHPMailer-master/src/SMTP.php';
require_once __DIR__ . '/../libs/PHPMailer-master/src/Exception.php';

use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;
use PHPMailer\PHPMailer\PHPMailer;

$container = new Container();

// Config
$container->set('config', fn($c, $params) => include __DIR__ . '/../config/config.php');

// Database
$container->set('db', fn($c, $params) => $conn);

// Models
$container->set('userModel', fn($c, $params) => new UserModel($c->get('db')));
$container->set('gallonModel', fn($c, $params) => new GallonModel($c->get('db')));
$container->set('orderModel', fn($c, $params) => new OrderModel($c->get('db')));
$container->set('addressModel', fn($c, $params) => new AddressModel($c->get('db')));
$container->set('cartModel', fn($c, $params) => new CartModel($c->get('db')));

//for EmailService to use
$container->set('mailer', function ($c, $params) {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'dmcarss23@gmail.com';
    $mail->Password   = 'nfww iaxw jmdp kwfv';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ];
    return $mail;
});

// Email Service
$container->set('emailService', fn($c, $params) => new EmailService($c->get('mailer')));
$container->set('paypalService', fn($c, $params) => new PaypalService());

// Middlewares
$container->set('loggerMiddleware', fn($c, $params) => new LoggerMiddleware());
$container->set('corsMiddleware', fn($c, $params) => new CorsMiddleware());
$container->set('rateLimitMiddleware', fn($c, $params) => new RateLimitMiddleware());
$container->set('sanitizeMiddleware', fn($c, $params) => new SanitizeMiddleware());
$container->set('validateMiddleware', fn($c, $params) => new ValidateMiddleware($params));

$container->set('authMiddleware', fn($c, $params) => new AuthMiddleware());


$container->set('roleMiddleware', function ($c, $params = []) {
    $role = $params['role'] ?? null;
    if (!$role) {
        throw new Exception("Role is required for RoleMiddleware");
    }
    return new RoleMiddleware($role);
});
// Middleware Runner
$container->set('middlewareRunner', function ($c, $params) {
    $runner = new MiddlewareRunner();
    $runner->add($c->get('loggerMiddleware'));
    $runner->add($c->get('corsMiddleware'));
    $runner->add($c->get('rateLimitMiddleware'));
    $runner->add($c->get('sanitizeMiddleware'));
    return $runner;
});

$container->set('AuthController', fn($c, $params) => new AuthController(
    $c->get('userModel'),
    $c->get('emailService')
));

$container->set('GallonController', fn($c, $params) => new GallonController(
    $c->get('gallonModel')
));

$container->set('OrderController', fn($c, $params) => new OrderController(
    $c->get('orderModel'),
    $c->get('addressModel'),
    $c->get('cartModel')
));
$container->set('AddressController', fn($c, $params) => new AddressController(
    $c->get('addressModel')
));
$container->set('ProfileController', fn($c, $params) => new ProfileController(
    $c->get('userModel')
));

$container->set('ImageController', fn($c, $params) => new ImageController());
$container->set('CartController', fn($c, $params) => new CartController(
    $c->get('cartModel')
));
$container->set('PaypalController', fn($c, $params) => new PaypalController(
    $c->get('orderModel'),
    $c->get('paypalService')
));
$container->set('AdminOrdersController', fn($c, $params) => new AdminOrdersController(
    $c->get('orderModel')
));
$container->set('AdminCustomerController', function ($c) {
    return new AdminCustomerController(
        $c->get('userModel'),
        $c->get('addressModel'),
        $c->get('orderModel')
    );
});
$container->set('AdminRiderController', fn($c, $params) => new AdminRiderController(
    $c->get('userModel'),
    $c->get('orderModel')
));
$container->set('RiderPageController', fn($c, $params) => new RiderPageController(
    $c->get('orderModel')
));



return $container;
