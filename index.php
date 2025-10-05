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

if (strpos($uri, '/api') === 0) {
    $uri = substr($uri, 4); // remove 4 chars = "/api"
}

$rawInput = file_get_contents("php://input");
$inputData = json_decode($rawInput, true) ?? [];

$runner = $container->get('middlewareRunner');

try {
    $matched = null;
    $params = [];

    foreach ($routes as $route) {

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

    $runner->add($container->get('corsMiddleware'));

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

        $runner->add($container->get('validateMiddleware', [
            'fields' => $fields,
            'route'  => $matched['action']
        ]));
    }

    if (in_array($matched['action'], ['createGallon', 'updateGallon'])) {
        $fields = ['name', 'image_url', 'size', 'price'];
        $runner->add($container->get('validateMiddleware', ['fields' => $fields]));
    }

    $request = $runner->run($inputData);

    $userId = $request['user_id'] ?? null;

    // Call controller + action
    $controllerName = $matched['controller'];
    $actionName = $matched['action'];

    if (!class_exists($controllerName)) {
        throw new NotFoundException("Controller not found: $controllerName");
    }

    $controller = $container->get($controllerName);
    $params = $matched['params'] ?? [];
    $params = is_array($params) ? $params : [];

    switch ($actionName) {
        case 'createAddress':
            $result = $controller->$actionName($request, $userId, ...$params);
            break;

        case 'updateAddress':
            if (empty($params)) {
                $parts = explode('/', $uri);
                $addressId = end($parts);
                $result = $controller->updateAddress($request, $addressId, $userId);
            } else {
                $result = $controller->updateAddress($request, $params[0] ?? null, $userId);
            }
            break;

        case 'deleteAddress':
        case 'setDefaultAddress':
        case 'getUserAddresses':
            $result = $controller->$actionName($userId, ...$params);
            break;

        case 'createOrder':
            $result = $controller->$actionName($request, $userId);
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
            // Pass the enriched request instead of just inputData
            $result = $controller->$actionName($request, ...$params);
            break;
    }

    echo json_encode($result);
    exit;
} catch (Exception $e) {
    sendErrorResponse($e);
}
