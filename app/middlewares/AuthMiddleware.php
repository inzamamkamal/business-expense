<?php
namespace App\Middlewares;

use App\Core\Session;

class AuthMiddleware {
    /**
     * Check if user is authenticated
     */
    public static function check() {
        if (!Session::has('user_id') || !Session::has('username')) {
            redirect(baseUrl('login'));
            exit;
        }
    }
    
    /**
     * Check if user has required role
     */
    public static function checkRole($allowedRoles) {
        self::check(); // First ensure user is logged in
        
        $userRole = Session::get('role');
        
        if (!is_array($allowedRoles)) {
            $allowedRoles = [$allowedRoles];
        }
        
        if (!in_array($userRole, $allowedRoles)) {
            // Redirect to dashboard with error message
            setFlash('error', 'You do not have permission to access this page.');
            redirect(baseUrl('dashboard'));
            exit;
        }
    }
    
    /**
     * Check if user is guest (not logged in)
     */
    public static function guest() {
        if (Session::has('user_id')) {
            redirect(baseUrl('dashboard'));
            exit;
        }
    }
}