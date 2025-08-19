<?php

class AuthController extends Controller {
    private $userModel;
    
    public function __construct() {
        $this->userModel = new User();
        // Don't check auth for login/logout actions
        if (!in_array($_SERVER['REQUEST_METHOD'] . ':' . ($_GET['action'] ?? 'login'), ['GET:login', 'POST:login', 'GET:logout'])) {
            parent::__construct();
        } else {
            $this->db = Database::getInstance()->getConnection();
        }
    }
    
    /**
     * Show login form
     */
    public function login() {
        // Redirect if already logged in
        if (Auth::isLoggedIn()) {
            $this->redirect('/dashboard');
        }
        
        $this->view('auth/login');
    }
    
    /**
     * Process login
     */
    public function processLogin() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/');
        }
        
        $username = Security::sanitizeInput($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        // Rate limiting
        if (!Security::rateLimit('login_' . $username, 5, 300)) {
            $this->view('auth/login', ['error' => 'Too many login attempts. Please try again later.']);
            return;
        }
        
        if (empty($username) || empty($password)) {
            $this->view('auth/login', ['error' => 'Please enter both username and password.']);
            return;
        }
        
        if (Auth::attempt($username, $password)) {
            // Reset rate limiting on successful login
            unset($_SESSION["rate_limit_login_$username"]);
            $this->redirect('/dashboard');
        } else {
            $this->view('auth/login', ['error' => 'Invalid credentials']);
        }
    }
    
    /**
     * Logout user
     */
    public function logout() {
        Auth::logout();
        $this->redirect('/');
    }
    
    /**
     * Check session and redirect if not authenticated
     */
    protected function checkAuth() {
        // Override parent method to allow login actions
        return true;
    }
}