<?php
// PUBLIC ENTRY POINT

use App\Core\Router;

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// Simple PSR-4 autoloader for this lightweight setup
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    if (str_starts_with($class, $prefix)) {
        $path = __DIR__ . '/../' . str_replace('App\\', 'app/', $class) . '.php';
        $path = str_replace('\\', '/', $path);
        if (file_exists($path)) {
            require $path;
        }
    }
});

// Start secure session
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? '1' : '0');
    session_start();
}

// CSRF token generation
if (empty($_SESSION['_csrf'])) {
    $_SESSION['_csrf'] = bin2hex(random_bytes(32));
}

$router = new Router();

// Define routes
$router->get('/', ['AuthController', 'showLogin']);
$router->post('/login', ['AuthController', 'login']);

// Additional routes can be added here, e.g., dashboard

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);