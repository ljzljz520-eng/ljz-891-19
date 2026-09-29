<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET,POST,PUT,DELETE,OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if (file_exists('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    // Fallback if composer not run (should not happen in Docker)
    include_once './Config/Database.php';
    include_once './Controllers/AuthController.php';
    include_once './Controllers/LicenseController.php';
}

use Config\Database;
use Controllers\AuthController;
use Controllers\LicenseController;

$database = new Database();
$db = $database->getConnection();

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uriParts = explode('/', $uri);

// Simple Router
// /api/auth/login
// /api/license/query?qq=123
// /api/license/create (POST)
// /api/license/send-code (POST)

if ($uri === '/api/auth/login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $auth = new AuthController($db);
    $auth->login();
} 
elseif ($uri === '/api/license/query' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $license = new LicenseController($db);
    $license->query();
}
elseif ($uri === '/api/license/create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $license = new LicenseController($db);
    $license->create();
}
elseif ($uri === '/api/license/update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $license = new LicenseController($db);
    $license->update();
}
elseif ($uri === '/api/license/send-code' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $license = new LicenseController($db);
    $license->sendVerificationCode();
}
elseif ($uri === '/api/license/list' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $license = new LicenseController($db);
    $license->listAll(); // Admin only, simplified auth for now
}
elseif ($uri === '/api/license/delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $license = new LicenseController($db);
    $license->delete();
}
elseif ($uri === '/api/license/import-preview' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $license = new LicenseController($db);
    $license->importPreview();
}
elseif ($uri === '/api/license/import' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $license = new LicenseController($db);
    $license->importConfirm();
}
// Admin Management Routes
elseif ($uri === '/api/auth/list' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $auth = new AuthController($db);
    $auth->list();
}
elseif ($uri === '/api/auth/create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $auth = new AuthController($db);
    $auth->create();
}
elseif ($uri === '/api/auth/delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $auth = new AuthController($db);
    $auth->delete();
}
else {
    http_response_code(404);
    echo json_encode(["message" => "Endpoint Not Found", "uri" => $uri]);
}
