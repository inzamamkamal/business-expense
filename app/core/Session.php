<?php
namespace App\Core;

class Session {
    public static function start() {
        if (session_status() === PHP_SESSION_NONE) {
            // Set secure session parameters
            ini_set('session.use_only_cookies', 1);
            ini_set('session.use_strict_mode', 1);
            
            session_set_cookie_params([
                'lifetime' => Config::get('SESSION_LIFETIME', 120) * 60,
                'path' => '/',
                'domain' => '',
                'secure' => Config::get('SESSION_SECURE', true),
                'httponly' => Config::get('SESSION_HTTPONLY', true),
                'samesite' => Config::get('SESSION_SAMESITE', 'Lax')
            ]);
            
            session_start();
            
            // Regenerate session ID periodically
            if (!isset($_SESSION['last_regeneration'])) {
                self::regenerate();
            } elseif (time() - $_SESSION['last_regeneration'] > 300) {
                self::regenerate();
            }
        }
    }
    
    public static function regenerate() {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
    
    public static function set($key, $value) {
        $_SESSION[$key] = $value;
    }
    
    public static function get($key, $default = null) {
        return $_SESSION[$key] ?? $default;
    }
    
    public static function has($key) {
        return isset($_SESSION[$key]);
    }
    
    public static function remove($key) {
        unset($_SESSION[$key]);
    }
    
    public static function destroy() {
        session_destroy();
        $_SESSION = [];
    }
    
    public static function generateCSRFToken() {
        if (!self::has('csrf_token')) {
            self::set('csrf_token', bin2hex(random_bytes(32)));
        }
        return self::get('csrf_token');
    }
    
    public static function validateCSRFToken($token) {
        return hash_equals(self::get('csrf_token', ''), $token);
    }
}