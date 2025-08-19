<?php
namespace App\Core;

class Router {
    private $routes = [];
    private $params = [];
    
    public function add($route, $params = []) {
        // Convert route to regex
        $route = preg_replace('/\//', '\\/', $route);
        $route = preg_replace('/\{([a-z]+)\}/', '(?P<\1>[a-z-]+)', $route);
        $route = preg_replace('/\{([a-z]+):([^\}]+)\}/', '(?P<\1>\2)', $route);
        $route = '/^' . $route . '$/i';
        
        $this->routes[$route] = $params;
    }
    
    public function get($route, $params) {
        $params['method'] = 'GET';
        $this->add($route, $params);
    }
    
    public function post($route, $params) {
        $params['method'] = 'POST';
        $this->add($route, $params);
    }
    
    public function match($url, $method = 'GET') {
        foreach ($this->routes as $route => $params) {
            if (preg_match($route, $url, $matches)) {
                // Check method
                if (isset($params['method']) && $params['method'] !== $method) {
                    continue;
                }
                
                foreach ($matches as $key => $match) {
                    if (is_string($key)) {
                        $params[$key] = $match;
                    }
                }
                
                $this->params = $params;
                return true;
            }
        }
        return false;
    }
    
    public function dispatch($url) {
        $url = $this->removeQueryString($url);
        $method = $_SERVER['REQUEST_METHOD'];
        
        if ($this->match($url, $method)) {
            $controller = $this->params['controller'];
            $controller = $this->convertToStudlyCaps($controller);
            $controller = "App\\Controllers\\{$controller}Controller";
            
            if (class_exists($controller)) {
                $controllerObject = new $controller($this->params);
                
                $action = $this->params['action'];
                $action = $this->convertToCamelCase($action);
                
                if (is_callable([$controllerObject, $action])) {
                    $controllerObject->$action();
                } else {
                    throw new \Exception("Method $action not found in controller $controller");
                }
            } else {
                throw new \Exception("Controller class $controller not found");
            }
        } else {
            throw new \Exception('No route matched.', 404);
        }
    }
    
    private function removeQueryString($url) {
        if ($url != '') {
            $parts = explode('?', $url, 2);
            if (strpos($parts[0], '=') === false) {
                $url = $parts[0];
            } else {
                $url = '';
            }
        }
        return $url;
    }
    
    private function convertToStudlyCaps($string) {
        return str_replace(' ', '', ucwords(str_replace('-', ' ', $string)));
    }
    
    private function convertToCamelCase($string) {
        return lcfirst($this->convertToStudlyCaps($string));
    }
}