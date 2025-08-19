<?php
/**
 * Global helper functions
 */

use App\Helpers\Security;
use App\Core\Session;
use App\Core\Config;

/**
 * Escape output
 */
function e($string) {
    return Security::escape($string);
}

/**
 * Get base URL
 */
function baseUrl($path = '') {
    $baseUrl = Config::get('APP_URL', '');
    return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
}

/**
 * Get asset URL
 */
function asset($path) {
    return baseUrl('public/' . ltrim($path, '/'));
}

/**
 * Redirect to URL
 */
function redirect($url) {
    header('Location: ' . $url);
    exit;
}

/**
 * Get current URL
 */
function currentUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    return $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}

/**
 * Generate CSRF token field
 */
function csrfField() {
    $token = Session::generateCSRFToken();
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

/**
 * Get CSRF token
 */
function csrfToken() {
    return Session::generateCSRFToken();
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return Session::has('user_id');
}

/**
 * Get logged in user ID
 */
function userId() {
    return Session::get('user_id');
}

/**
 * Get logged in username
 */
function username() {
    return Session::get('username');
}

/**
 * Get user role
 */
function userRole() {
    return Session::get('role');
}

/**
 * Check if user has role
 */
function hasRole($role) {
    $userRole = userRole();
    if (is_array($role)) {
        return in_array($userRole, $role);
    }
    return $userRole === $role;
}

/**
 * Format date
 */
function formatDate($date, $format = 'd/m/Y') {
    if (!$date) return '';
    return date($format, strtotime($date));
}

/**
 * Format currency
 */
function formatCurrency($amount, $symbol = '₹') {
    return $symbol . ' ' . number_format($amount, 2);
}

/**
 * Get flash message
 */
function flash($key = null) {
    if ($key) {
        $message = Session::get('flash_' . $key);
        Session::remove('flash_' . $key);
        return $message;
    }
    
    // Get all flash messages
    $messages = [];
    foreach ($_SESSION as $k => $v) {
        if (strpos($k, 'flash_') === 0) {
            $type = str_replace('flash_', '', $k);
            $messages[$type] = $v;
            Session::remove($k);
        }
    }
    return $messages;
}

/**
 * Set flash message
 */
function setFlash($key, $message) {
    Session::set('flash_' . $key, $message);
}

/**
 * Check if date is locked
 */
function isDateLocked($pdo, $date) {
    $stmt = $pdo->prepare("SELECT * FROM locks WHERE locked_date = ?");
    $stmt->execute([$date]);
    return $stmt->fetch() !== false;
}