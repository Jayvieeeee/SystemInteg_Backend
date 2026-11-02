<?php
// Vercel entry point: ipapasa lang sa existing index.php mo

// --- CORS: dapat nasa pinakataas, bago mag-load ng kahit ano (DB, services) ---
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}
// --- hanggang dito ---

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
if (
    is_string($requestPath)
    && preg_match('~^/(?:api/)?images/([A-Za-z0-9_-][A-Za-z0-9._-]*\.(?:jpe?g|png|gif|webp))$~i', $requestPath, $matches)
) {
    $imagePath = __DIR__ . '/../images/' . $matches[1];
    $extension = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
    $contentTypes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp'
    ];

    if (!is_file($imagePath) || !isset($contentTypes[$extension])) {
        http_response_code(404);
        exit;
    }

    header('Content-Type: ' . $contentTypes[$extension], true);
    header('Content-Length: ' . filesize($imagePath), true);
    header('Cache-Control: public, max-age=86400', true);
    readfile($imagePath);
    exit;
}

// Para tama yung pag-compute ng router sa "/api" prefix
$_SERVER['SCRIPT_NAME'] = '/api/index.php';

// Minsan wala ang getallheaders() sa Vercel PHP; ginagamit ito ng AuthController at ProfileController
if (!function_exists('getallheaders')) {
    function getallheaders()
    {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (strpos($name, 'HTTP_') === 0) {
                $key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                $headers[$key] = $value;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['Content-Type'] = $_SERVER['CONTENT_TYPE'];
        }
        return $headers;
    }
}

require __DIR__ . '/../index.php';
