<?php

$container = require __DIR__ . '/init/Services.php';

require_once "config/config.php";

// Exceptions
require_once "exceptions/AppException.php";
require_once "exceptions/AuthException.php";
require_once "exceptions/ValidationException.php";
require_once "exceptions/NotFoundException.php";
require_once "exceptions/ConflictException.php";

// Middleware
require_once "middleware/MiddlewareRunner.php";
require_once "middleware/LoggerMiddleware.php";
require_once "middleware/CorsMiddleware.php";
require_once "middleware/RateLimitMiddleware.php";
require_once "middleware/SanitizeMiddleware.php";
require_once "middleware/ValidateMiddleware.php";
require_once "middleware/AuthMiddleware.php";

// Controllers
require_once "controllers/AuthController.php";


// Load routes
$routes = require "routes/api.php";

// Error response
function sendErrorResponse($e)
{
    $statusCode = method_exists($e, "getStatusCode") ? $e->getStatusCode() : 500;
    http_response_code($statusCode);
    header("Content-Type: application/json");
    echo json_encode([
        "error" => true,
        "message" => (APP_ENV === 'development') ? $e->getMessage() : "Something went wrong"
    ]);
    exit;
}

// Parse request
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = str_replace(dirname($_SERVER['SCRIPT_NAME']), '', $uri);
$method = $_SERVER['REQUEST_METHOD'];

$rawInput = file_get_contents("php://input");
$inputData = json_decode($rawInput, true) ?? [];

// Middleware runner
$runner = $container->get('middlewareRunner');

try {
    $matched = null;
    $params = [];

    foreach ($routes as $route) {
        // convert {id} into regex
        $pattern = "@^" . preg_replace('/\{[^}]+\}/', '([^/]+)', $route['path']) . "$@";
        if ($method === $route['method'] && preg_match($pattern, $uri, $matches)) {
            array_shift($matches); // remove full match
            $params = $matches;
            $matched = $route;
            break;
        }
    }

    if (!$matched) {
        throw new NotFoundException("Route not found: $uri");
    }

    // Route-specific middleware: Auth
    if (!empty($matched['auth'])) {
        $runner->add($container->get('authMiddleware'));
    }


    if (in_array($matched['action'], ['register', 'login'])) {
        $fields = [];
        if ($matched['action'] === 'register') {
            $fields = ['first_name', 'last_name', 'email', 'username', 'password', 'contact_no'];
        } elseif ($matched['action'] === 'login') {
            $fields = ['identifier' => 'Username or email', 'password' => 'Password'];
        }
        $runner->add($container->get('validateMiddleware', ['fields' => $fields]));
    }

    $request = $runner->run($inputData);

    // Call controller + action
    $controllerName = $matched['controller'];
    $actionName = $matched['action'];

    if (!class_exists($controllerName)) {
        throw new NotFoundException("Controller not found: $controllerName");
    }

    $controller = $container->get($controllerName);
    $result = call_user_func_array([$controller, $actionName], [$request, ...$params]);

    header("Content-Type: application/json");
    echo json_encode($result);
    exit;
} catch (Exception $e) {
    sendErrorResponse($e);
}
