<?php

class App {
    private $router;
    
    public function __construct() {
        $this->initializeSession();
        $this->loadEnvironment();
        $this->setupAutoloader();
        $this->router = new Router();
        $this->setupRoutes();
    }
    
    /**
     * Initialize secure session
     */
    private function initializeSession() {
        if (session_status() === PHP_SESSION_NONE) {
            // Set secure session parameters
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? 1 : 0);
            ini_set('session.cookie_samesite', 'Strict');
            ini_set('session.use_strict_mode', 1);
            
            session_start();
            
            // Check session timeout
            if (!Auth::checkTimeout()) {
                header('Location: /');
                exit;
            }
        }
        
        // Set timezone
        date_default_timezone_set('Asia/Kolkata');
    }
    
    /**
     * Load environment variables
     */
    private function loadEnvironment() {
        $envFile = __DIR__ . '/../.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                    list($key, $value) = explode('=', $line, 2);
                    $_ENV[trim($key)] = trim($value);
                }
            }
        }
    }
    
    /**
     * Setup autoloader for classes
     */
    private function setupAutoloader() {
        spl_autoload_register(function ($class) {
            $directories = [
                __DIR__ . '/',
                __DIR__ . '/../app/models/',
                __DIR__ . '/../app/controllers/'
            ];
            
            foreach ($directories as $directory) {
                $file = $directory . $class . '.php';
                if (file_exists($file)) {
                    require_once $file;
                    return;
                }
            }
        });
    }
    
    /**
     * Setup application routes
     */
    private function setupRoutes() {
        // Authentication routes
        $this->router->get('/', 'Auth@login');
        $this->router->post('/', 'Auth@processLogin');
        $this->router->get('/logout', 'Auth@logout');
        
        // Dashboard
        $this->router->get('/dashboard', 'Dashboard@index');
        $this->router->post('/dashboard/stats', 'Dashboard@quickStats');
        
        // Booking routes
        $this->router->get('/bookings', 'Booking@index');
        $this->router->post('/bookings/create', 'Booking@create');
        $this->router->post('/bookings/list', 'Booking@list');
        $this->router->post('/bookings/details', 'Booking@details');
        $this->router->post('/bookings/complete', 'Booking@complete');
        $this->router->post('/bookings/delete', 'Booking@delete');
        
        // Staff routes
        $this->router->get('/staff', 'Staff@index');
        $this->router->post('/staff/create', 'Staff@create');
        $this->router->post('/staff/update', 'Staff@update');
        $this->router->post('/staff/delete', 'Staff@delete');
        $this->router->post('/staff/list', 'Staff@list');
        $this->router->post('/staff/details', 'Staff@details');
        
        // Attendance routes
        $this->router->get('/attendance', 'Attendance@index');
        $this->router->post('/attendance/mark', 'Attendance@mark');
        $this->router->post('/attendance/records', 'Attendance@records');
        $this->router->post('/attendance/summary', 'Attendance@summary');
        $this->router->post('/attendance/daily', 'Attendance@daily');
        $this->router->get('/attendance/monthly-report', 'Attendance@monthlyReport');
        
        // Income routes
        $this->router->get('/income', 'Income@index');
        $this->router->post('/income/add', 'Income@add');
        $this->router->post('/income/update', 'Income@update');
        $this->router->post('/income/delete', 'Income@delete');
        $this->router->post('/income/summary', 'Income@summary');
        $this->router->get('/income/monthly-report', 'Income@monthlyReport');
        
        // Expense routes
        $this->router->get('/expenses', 'Expense@index');
        $this->router->post('/expenses/add', 'Expense@add');
        $this->router->post('/expenses/update', 'Expense@update');
        $this->router->post('/expenses/delete', 'Expense@delete');
        $this->router->post('/expenses/summary', 'Expense@summary');
        $this->router->get('/expenses/monthly-report', 'Expense@monthlyReport');
        
        // Settlement routes
        $this->router->get('/settlements', 'Settlement@index');
        $this->router->post('/settlements/add', 'Settlement@add');
        $this->router->post('/settlements/update', 'Settlement@update');
        $this->router->post('/settlements/delete', 'Settlement@delete');
        $this->router->post('/settlements/balance', 'Settlement@balance');
        $this->router->get('/settlements/monthly-report', 'Settlement@monthlyReport');
    }
    
    /**
     * Run the application
     */
    public function run() {
        try {
            $this->router->dispatch();
        } catch (Exception $e) {
            // Log error
            error_log("Application error: " . $e->getMessage());
            
            // Show error page
            http_response_code(500);
            if ($_ENV['DEBUG'] ?? false) {
                echo "<h1>Application Error</h1>";
                echo "<p>" . $e->getMessage() . "</p>";
                echo "<pre>" . $e->getTraceAsString() . "</pre>";
            } else {
                echo "<h1>Something went wrong</h1>";
                echo "<p>Please try again later.</p>";
            }
        }
    }
}