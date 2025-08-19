<?php
/**
 * BTS DISC 2.0 - Entry Point
 * 
 * This is the main entry point for the application.
 * All requests are routed through this file.
 */

// Define the application root
define('APP_ROOT', dirname(__DIR__));

// Autoloader
spl_autoload_register(function ($class) {
    $file = APP_ROOT . '/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

// Load helper functions
require_once APP_ROOT . '/app/helpers/functions.php';

// Load configuration
use App\Core\Config;
use App\Core\Session;
use App\Core\Router;

try {
    // Load environment configuration
    Config::load();
    
    // Set timezone
    date_default_timezone_set(Config::get('APP_TIMEZONE', 'Asia/Kolkata'));
    
    // Start session
    Session::start();
    
    // Initialize router
    $router = new Router();
    
    // Define routes
    
    // Authentication routes
    $router->get('', ['controller' => 'Auth', 'action' => 'login']);
    $router->get('login', ['controller' => 'Auth', 'action' => 'login']);
    $router->post('login', ['controller' => 'Auth', 'action' => 'authenticate']);
    $router->get('logout', ['controller' => 'Auth', 'action' => 'logout']);
    
    // Dashboard routes
    $router->get('dashboard', ['controller' => 'Dashboard', 'action' => 'index']);
    $router->post('switch-db', ['controller' => 'Dashboard', 'action' => 'switchDatabase']);
    
    // Booking routes
    $router->get('bookings', ['controller' => 'Booking', 'action' => 'index']);
    $router->get('bookings/create', ['controller' => 'Booking', 'action' => 'create']);
    $router->post('bookings', ['controller' => 'Booking', 'action' => 'store']);
    $router->get('bookings/{id:\d+}', ['controller' => 'Booking', 'action' => 'show']);
    $router->get('bookings/{id:\d+}/edit', ['controller' => 'Booking', 'action' => 'edit']);
    $router->post('bookings/{id:\d+}', ['controller' => 'Booking', 'action' => 'update']);
    $router->post('bookings/{id:\d+}/delete', ['controller' => 'Booking', 'action' => 'delete']);
    
    // Staff routes
    $router->get('staff', ['controller' => 'Staff', 'action' => 'index']);
    $router->get('staff/create', ['controller' => 'Staff', 'action' => 'create']);
    $router->post('staff', ['controller' => 'Staff', 'action' => 'store']);
    $router->get('staff/{id:\d+}', ['controller' => 'Staff', 'action' => 'show']);
    $router->get('staff/{id:\d+}/edit', ['controller' => 'Staff', 'action' => 'edit']);
    $router->post('staff/{id:\d+}', ['controller' => 'Staff', 'action' => 'update']);
    $router->post('staff/{id:\d+}/toggle-status', ['controller' => 'Staff', 'action' => 'toggleStatus']);
    
    // Income routes
    $router->get('income', ['controller' => 'Income', 'action' => 'index']);
    $router->get('income/create', ['controller' => 'Income', 'action' => 'create']);
    $router->post('income', ['controller' => 'Income', 'action' => 'store']);
    $router->get('income/{id:\d+}/edit', ['controller' => 'Income', 'action' => 'edit']);
    $router->post('income/{id:\d+}', ['controller' => 'Income', 'action' => 'update']);
    $router->post('income/{id:\d+}/delete', ['controller' => 'Income', 'action' => 'delete']);
    
    // Expense routes
    $router->get('expenses', ['controller' => 'Expense', 'action' => 'index']);
    $router->get('expenses/create', ['controller' => 'Expense', 'action' => 'create']);
    $router->post('expenses', ['controller' => 'Expense', 'action' => 'store']);
    $router->get('expenses/{id:\d+}/edit', ['controller' => 'Expense', 'action' => 'edit']);
    $router->post('expenses/{id:\d+}', ['controller' => 'Expense', 'action' => 'update']);
    $router->post('expenses/{id:\d+}/delete', ['controller' => 'Expense', 'action' => 'delete']);
    
    // Attendance routes
    $router->get('attendance', ['controller' => 'Attendance', 'action' => 'index']);
    $router->post('attendance/mark', ['controller' => 'Attendance', 'action' => 'mark']);
    $router->get('attendance/report', ['controller' => 'Attendance', 'action' => 'report']);
    $router->get('attendance/staff/{id:\d+}', ['controller' => 'Attendance', 'action' => 'staffAttendance']);
    
    // Settlement routes
    $router->get('settlement', ['controller' => 'Settlement', 'action' => 'index']);
    $router->post('settlement/generate', ['controller' => 'Settlement', 'action' => 'generate']);
    
    // Lock dates routes (admin only)
    $router->get('lock-dates', ['controller' => 'Lock', 'action' => 'index']);
    $router->post('lock-dates/lock', ['controller' => 'Lock', 'action' => 'lock']);
    $router->post('lock-dates/unlock', ['controller' => 'Lock', 'action' => 'unlock']);
    
    // Reports routes
    $router->get('reports', ['controller' => 'Report', 'action' => 'index']);
    $router->get('reports/booking', ['controller' => 'Report', 'action' => 'booking']);
    $router->get('reports/financial', ['controller' => 'Report', 'action' => 'financial']);
    $router->get('reports/staff', ['controller' => 'Report', 'action' => 'staff']);
    
    // Get the URL path
    $url = $_GET['route'] ?? '';
    
    // Remove trailing slash
    $url = rtrim($url, '/');
    
    // Dispatch the request
    $router->dispatch($url);
    
} catch (Exception $e) {
    // Error handling
    http_response_code($e->getCode() ?: 500);
    
    if (Config::get('APP_DEBUG') === 'true') {
        echo '<h1>Error: ' . $e->getMessage() . '</h1>';
        echo '<pre>' . $e->getTraceAsString() . '</pre>';
    } else {
        // In production, show a friendly error page
        if ($e->getCode() === 404) {
            include APP_ROOT . '/app/views/errors/404.php';
        } else {
            include APP_ROOT . '/app/views/errors/500.php';
        }
    }
}