<?php
namespace App\Middlewares;

use App\Core\Session;

class CSRFMiddleware {
    /**
     * Validate CSRF token for POST requests
     */
    public static function validate() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST['csrf_token'] ?? '';
            
            if (!Session::validateCSRFToken($token)) {
                http_response_code(403);
                die('CSRF token validation failed');
            }
        }
    }
}