<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Models\User;
use App\Helpers\Validator;
use App\Middlewares\AuthMiddleware;

class AuthController extends Controller {
    private $userModel;
    
    public function __construct($params) {
        parent::__construct($params);
        $this->userModel = new User();
    }
    
    /**
     * Show login form
     */
    public function login() {
        AuthMiddleware::guest();
        
        $this->renderWithLayout('auth/login', [
            'title' => 'Login',
            'error' => flash('error')
        ]);
    }
    
    /**
     * Handle login request
     */
    public function authenticate() {
        AuthMiddleware::guest();
        
        // Validate input
        $validator = new Validator($_POST);
        if (!$validator->validate([
            'username' => 'required|min:3',
            'password' => 'required'
        ])) {
            setFlash('error', $validator->getFirstError());
            $this->redirect(baseUrl('login'));
        }
        
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        
        // Authenticate user
        $user = $this->userModel->authenticate($username, $password);
        
        if ($user) {
            // Set session data
            Session::set('user_id', $user['id']);
            Session::set('username', $user['username']);
            Session::set('role', $user['role']);
            Session::set('db_mode', 'current');
            
            // Regenerate session ID for security
            Session::regenerate();
            
            $this->redirect(baseUrl('dashboard'));
        } else {
            setFlash('error', 'Invalid username or password');
            $this->redirect(baseUrl('login'));
        }
    }
    
    /**
     * Logout user
     */
    public function logout() {
        Session::destroy();
        $this->redirect(baseUrl('login'));
    }
}