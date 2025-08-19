<?php

abstract class Controller {
    protected $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->checkAuth();
    }
    
    /**
     * Check if user is authenticated
     */
    protected function checkAuth() {
        if (!Auth::isLoggedIn()) {
            $this->redirect('/');
        }
    }
    
    /**
     * Load a view
     */
    protected function view($viewName, $data = []) {
        // Extract data to variables
        extract($data);
        
        // Start output buffering
        ob_start();
        
        // Include the view file
        $viewFile = __DIR__ . "/../app/views/$viewName.php";
        if (file_exists($viewFile)) {
            include $viewFile;
        } else {
            throw new Exception("View not found: $viewName");
        }
        
        // Get the content
        $content = ob_get_clean();
        
        // Load the layout
        include __DIR__ . "/../app/views/layouts/main.php";
    }
    
    /**
     * Return JSON response
     */
    protected function json($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
    
    /**
     * Redirect to a URL
     */
    protected function redirect($url) {
        header("Location: $url");
        exit;
    }
    
    /**
     * Get POST data with validation
     */
    protected function getPostData($required = []) {
        // Validate CSRF token
        if (!Security::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->json(['status' => 'error', 'message' => 'Invalid CSRF token'], 403);
        }
        
        $data = [];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                $this->json(['status' => 'error', 'message' => "Missing required field: $field"], 400);
            }
            $data[$field] = Security::sanitizeInput($_POST[$field]);
        }
        
        return $data;
    }
    
    /**
     * Validate user role
     */
    protected function requireRole($roles) {
        if (!Auth::hasRole($roles)) {
            $this->json(['status' => 'error', 'message' => 'Access denied'], 403);
        }
    }
}