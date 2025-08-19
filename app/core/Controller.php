<?php
namespace App\Core;

abstract class Controller {
    protected $routeParams = [];
    
    public function __construct($routeParams) {
        $this->routeParams = $routeParams;
    }
    
    protected function render($view, $data = []) {
        extract($data, EXTR_SKIP);
        
        $file = dirname(__DIR__) . "/views/$view.php";
        
        if (is_readable($file)) {
            require $file;
        } else {
            throw new \Exception("View $file not found");
        }
    }
    
    protected function renderWithLayout($view, $data = [], $layout = 'layouts/main') {
        $data['content'] = $this->getViewContent($view, $data);
        $this->render($layout, $data);
    }
    
    private function getViewContent($view, $data = []) {
        extract($data, EXTR_SKIP);
        
        $file = dirname(__DIR__) . "/views/$view.php";
        
        if (is_readable($file)) {
            ob_start();
            require $file;
            return ob_get_clean();
        } else {
            throw new \Exception("View $file not found");
        }
    }
    
    protected function redirect($url) {
        header('Location: ' . $url);
        exit;
    }
    
    protected function json($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
    
    protected function before() {
        // Override in child classes for pre-action logic
    }
    
    protected function after() {
        // Override in child classes for post-action logic
    }
    
    public function __call($name, $args) {
        $method = $name . 'Action';
        
        if (method_exists($this, $method)) {
            if ($this->before() !== false) {
                call_user_func_array([$this, $method], $args);
                $this->after();
            }
        } else {
            throw new \Exception("Method $method not found in controller " . get_class($this));
        }
    }
}