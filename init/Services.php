<?php

require_once __DIR__ . '/../core/Container.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';

// Services
require_once __DIR__ . '/../services/EmailService.php';


// Models
require_once __DIR__ . '/../models/UserModel.php';
require_once __DIR__ . '/../models/ContainerModel.php';


// Controllers 
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/ContainerController.php';


// Middlewares 
require_once __DIR__ . '/../middleware/LoggerMiddleware.php';
require_once __DIR__ . '/../middleware/CorsMiddleware.php';
require_once __DIR__ . '/../middleware/RateLimitMiddleware.php';
require_once __DIR__ . '/../middleware/SanitizeMiddleware.php';
require_once __DIR__ . '/../middleware/ValidateMiddleware.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
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
$container->set('containerModel', fn($c, $params) => new ContainerModel($c->get('db')));

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

// Middlewares
$container->set('loggerMiddleware', fn($c, $params) => new LoggerMiddleware());
$container->set('corsMiddleware', fn($c, $params) => new CorsMiddleware());
$container->set('rateLimitMiddleware', fn($c, $params) => new RateLimitMiddleware());
$container->set('sanitizeMiddleware', fn($c, $params) => new SanitizeMiddleware());
$container->set('validateMiddleware', fn($c, $params) => new ValidateMiddleware($params['fields'] ?? []));
$container->set('authMiddleware', fn($c, $params) => new AuthMiddleware());

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

$container->set('ContainerController', fn($c, $params) => new ContainerController(
    $c->get('containerModel')
));

return $container;
