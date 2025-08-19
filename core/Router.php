<?php

class Router {
    private $routes = [];
    private $basePath = '';
    
    public function __construct($basePath = '') {
        $this->basePath = rtrim($basePath, '/');
    }
    
    /**
     * Add a GET route
     */
    public function get($path, $handler) {
        $this->addRoute('GET', $path, $handler);
    }
    
    /**
     * Add a POST route
     */
    public function post($path, $handler) {
        $this->addRoute('POST', $path, $handler);
    }
    
    /**
     * Add a route
     */
    private function addRoute($method, $path, $handler) {
        $path = $this->basePath . $path;
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler,
            'pattern' => $this->createPattern($path)
        ];
    }
    
    /**
     * Create regex pattern from route path
     */
    private function createPattern($path) {
        // Convert route parameters like {id} to regex groups
        $pattern = preg_replace('/\{([^}]+)\}/', '([^/]+)', $path);
        return '#^' . $pattern . '$#';
    }
    
    /**
     * Dispatch the current request
     */
    public function dispatch() {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        foreach ($this->routes as $route) {
            if ($route['method'] === $method && preg_match($route['pattern'], $path, $matches)) {
                array_shift($matches); // Remove full match
                return $this->callHandler($route['handler'], $matches);
            }
        }
        
        // No route found
        $this->handleNotFound();
    }
    
    /**
     * Call the route handler
     */
    private function callHandler($handler, $params = []) {
        if (is_string($handler)) {
            // Handle "Controller@method" format
            if (strpos($handler, '@') !== false) {
                list($controller, $method) = explode('@', $handler);
                $controllerClass = $controller . 'Controller';
                
                if (class_exists($controllerClass)) {
                    $instance = new $controllerClass();
                    if (method_exists($instance, $method)) {
                        return call_user_func_array([$instance, $method], $params);
                    }
                }
            }
        } elseif (is_callable($handler)) {
            return call_user_func_array($handler, $params);
        }
        
        throw new Exception("Invalid route handler");
    }
    
    /**
     * Handle 404 not found
     */
    private function handleNotFound() {
        http_response_code(404);
        echo "404 - Page Not Found";
        exit;
    }
}