<?php

require_once __DIR__ . '/exceptions/AppException.php';
require_once __DIR__ . '/exceptions/AuthException.php';

require_once __DIR__ . '/exceptions/ValidationException.php';
require_once __DIR__ . '/exceptions/NotFoundException.php';
require_once __DIR__ . '/exceptions/ConflictException.php';

$container = require __DIR__ . '/init/Services.php';
$routes = require __DIR__ . '/routes/api.php';

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

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json; charset=UTF-8");


if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Parse request
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = str_replace(dirname($_SERVER['SCRIPT_NAME']), '', $uri);
$method = $_SERVER['REQUEST_METHOD'];

// remove "/api" prefix para tumugma sa routes
if (strpos($uri, '/api') === 0) {
    $uri = substr($uri, 4); // tanggalin first 4 chars = "/api"
}

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

    // CORS middleware to the runner for all requests
    $runner->add($container->get('corsMiddleware'));

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

    // Add validation for Gallon POST/PUT
    if (in_array($matched['action'], ['createGallon', 'updateGallon'])) {
        $fields = ['name', 'image_url', 'size', 'price'];
        $runner->add($container->get('validateMiddleware', ['fields' => $fields]));
    }

    $request = $runner->run($inputData);

    $userId = $request['userId'] ?? null;

    // Call controller + action
    $controllerName = $matched['controller'];
    $actionName = $matched['action'];

    if (!class_exists($controllerName)) {
        throw new NotFoundException("Controller not found: $controllerName");
    }

    // instantiate controller via container
    $controller = $container->get($controllerName);

    $params = $matched['params'] ?? [];
    $params = is_array($params) ? $params : [];

    switch ($actionName) {
        case 'createAddress':
            $result = $controller->$actionName($inputData, $userId, ...$params);
            break;

        case 'updateAddress':
            error_log("updateAddress - URI: " . $uri);
            error_log("updateAddress - Params: " . print_r($params, true));
            error_log("updateAddress - Params count: " . count($params));

            // If no parameters extracted, try manual fallback
            if (empty($params)) {
                $parts = explode('/', $uri);
                $addressId = end($parts);
                error_log("updateAddress - Manually extracted ID: " . $addressId);
                $result = $controller->updateAddress($inputData, $addressId, $userId);
            } else {
                $result = $controller->updateAddress($inputData, $params[0] ?? null, $userId);
            }
            break;
        case 'deleteAddress':
        case 'setDefaultAddress':
        case 'getUserAddresses':
            $result = $controller->$actionName($userId, ...$params);
            break;
        case 'createOrder':
            $result = $controller->$actionName($request, ...$params);
            break;
        case 'getAddress':
            if (empty($params)) {
                $parts = explode('/', $uri);
                $addressId = end($parts);
                $result = $controller->getAddress($addressId, $userId);
            } else {
                $result = $controller->getAddress($params[0] ?? null, $userId);
            }
            break;
        default:
            $result = $controller->$actionName($inputData, ...$params);
            break;
    }

    echo json_encode($result);
    exit;
} catch (Exception $e) {
    sendErrorResponse($e);
}
