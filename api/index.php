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
