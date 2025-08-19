<?php

class Auth {
    
    /**
     * Check if user is logged in
     */
    public static function isLoggedIn() {
        return isset($_SESSION['user_id']) && isset($_SESSION['username']);
    }
    
    /**
     * Get current user ID
     */
    public static function userId() {
        return $_SESSION['user_id'] ?? null;
    }
    
    /**
     * Get current username
     */
    public static function username() {
        return $_SESSION['username'] ?? null;
    }
    
    /**
     * Get current user role
     */
    public static function role() {
        return $_SESSION['role'] ?? null;
    }
    
    /**
     * Check if user has specific role(s)
     */
    public static function hasRole($roles) {
        if (!self::isLoggedIn()) {
            return false;
        }
        
        $userRole = self::role();
        $roles = is_array($roles) ? $roles : [$roles];
        
        return in_array($userRole, $roles);
    }
    
    /**
     * Check if user is admin
     */
    public static function isAdmin() {
        return self::hasRole(['admin', 'super_admin']);
    }
    
    /**
     * Login user
     */
    public static function login($user) {
        // Regenerate session ID for security
        session_regenerate_id(true);
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['db_mode'] = 'current';
        $_SESSION['login_time'] = time();
        
        // Set secure session cookie parameters
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), session_id(), [
                'expires' => time() + (86400 * 7), // 7 days
                'path' => '/',
                'domain' => '',
                'secure' => isset($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Strict'
            ]);
        }
    }
    
    /**
     * Logout user
     */
    public static function logout() {
        // Clear all session data
        $_SESSION = [];
        
        // Delete session cookie
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        
        // Destroy session
        session_destroy();
    }
    
    /**
     * Check session timeout
     */
    public static function checkTimeout($timeout = 7200) { // 2 hours default
        if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > $timeout) {
            self::logout();
            return false;
        }
        
        // Update last activity time
        $_SESSION['login_time'] = time();
        return true;
    }
    
    /**
     * Attempt to authenticate user
     */
    public static function attempt($username, $password) {
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([trim($username)]);
        $user = $stmt->fetch();
        
        if ($user && self::verifyPassword($password, $user['password_hash'])) {
            self::login($user);
            return true;
        }
        
        return false;
    }
    
    /**
     * Verify password (supports both plain text and hashed passwords)
     */
    private static function verifyPassword($password, $hash) {
        // Check if it's a bcrypt hash
        if (password_get_info($hash)['algo'] !== null) {
            return password_verify($password, $hash);
        }
        
        // Fallback to plain text comparison (for existing system)
        return $password === $hash;
    }
}