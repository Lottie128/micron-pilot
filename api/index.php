<?php
require_once 'config.php';

// Simple router
$request_uri = $_SERVER['REQUEST_URI'];
$script_name = dirname($_SERVER['SCRIPT_NAME']);
$path = str_replace($script_name, '', $request_uri);
$path = parse_url($path, PHP_URL_PATH);
$path = trim($path, '/');
$path = explode('/', $path);

$endpoint = $path[1] ?? 'dashboard';

switch ($endpoint) {
    case 'parts':
        require_once 'parts.php';
        break;
    case 'inventory':
        require_once 'inventory.php';
        break;
    case 'dashboard':
        require_once 'dashboard.php';
        break;
    default:
        sendError('Endpoint not found', 404);
}
?>
