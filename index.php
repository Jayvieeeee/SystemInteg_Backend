<?php
require_once __DIR__ . '/exceptions/AppException.php';
require_once __DIR__ . '/exceptions/AuthException.php';
require_once __DIR__ . '/exceptions/ValidationException.php';
require_once __DIR__ . '/exceptions/NotfoundException.php';
require_once __DIR__ . '/exceptions/ConflictException.php';
require_once __DIR__ . '/middleware/CorsMiddleware.php';

$container = require __DIR__ . '/init/Services.php';
$routes = require __DIR__ . '/routes/api.php';


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

// Handle CORS immediately using CorsMiddleware (static call)
CorsMiddleware::handlePreflight();


header("Content-Type: application/json; charset=UTF-8");

// Parse request
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = str_replace(dirname($_SERVER['SCRIPT_NAME']), '', $uri);
$method = $_SERVER['REQUEST_METHOD'];

if (strpos($uri, '/api') === 0) {
    $uri = substr($uri, 4); // remove 4 chars = "/api"
}

// Handle different content types
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';

if (strpos($contentType, 'multipart/form-data') !== false) {
    $inputData = $_POST;
    if (!empty($_FILES)) {
        $inputData['_files'] = $_FILES;
    }
} else {
    // For JSON, use php://input
    $rawInput = file_get_contents("php://input");
    $inputData = json_decode($rawInput, true) ?? [];
}

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

    // Add auth middleware if required
    if (!empty($matched['auth'])) {
        $runner->add($container->get('authMiddleware'));
    }

    if (!empty($matched['role'])) {
        $runner->add($container->get('roleMiddleware', [
            'role' => $matched['role']
        ]));
    }

    // Add validation middleware for specific actions
    if (in_array($matched['action'], ['register', 'login', 'updateProfile', 'changePassword', 'createGallon', 'updateGallon'])) {
        $fields = [];

        if ($matched['action'] === 'register') {
            $fields = ['first_name', 'last_name', 'email', 'username', 'password', 'contact_no'];
        } elseif ($matched['action'] === 'login') {
            $fields = ['identifier' => 'Username or email', 'password' => 'Password'];
        } elseif ($matched['action'] === 'updateProfile') {
            $fields = ['first_name', 'last_name', 'email', 'contact_no'];
        } elseif ($matched['action'] === 'changePassword') {
            $fields = [
                'old_password' => 'Old password',
                'new_password' => 'New password',
                'confirm_password' => 'Confirm password'
            ];
        } elseif ($matched['action'] === 'createGallon') {
            // All fields required including image
            $fields = ['name', 'size', 'price'];
        } elseif ($matched['action'] === 'updateGallon') {
            // All fields optional (including image)
            $fields = ['name', 'size', 'price'];
        }

        $runner->add($container->get('validateMiddleware', [
            'fields' => $fields,
            'route'  => $matched['action']
        ]));
    }
    // Run all middlewares
    $request = $runner->run($inputData);

    $userId = $request['user_id'] ?? null;

    // Call controller + action
    $controllerName = $matched['controller'];
    $actionName = $matched['action'];

    if (!class_exists($controllerName)) {
        throw new NotFoundException("Controller not found: $controllerName");
    }

    $controller = $container->get($controllerName);

    // Build arguments dynamically
    $args = [];

    // Add $request if it's POST/PUT/PATCH or DELETE with body
    if (in_array($method, ['POST', 'PUT', 'PATCH']) || ($method === 'DELETE' && !empty($inputData))) {
        $args[] = $request;
    }

    // Add $userId if authenticated
    if (!empty($matched['auth'])) {
        $args[] = $userId;
    }

    // Add route parameters (e.g., {id}, {addressId})
    $args = array_merge($args, $params);

    // Call the controller method with dynamic arguments
    $result = $controller->$actionName(...$args);

    echo json_encode($result);
    exit;
} catch (Exception $e) {
    sendErrorResponse($e);
}
