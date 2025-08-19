<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\User;

class AuthController extends Controller
{
    public function showLogin(): void
    {
        $this->view('auth/login');
    }

    public function login(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/');
        }

        // CSRF protection
        if (!\App\Core\Security::checkCsrfToken($_POST['_csrf'] ?? null)) {
            http_response_code(403);
            exit('Invalid CSRF token');
        }

        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        $userModel = new User();
        $user = $userModel->findByUsername($username);

        if ($user && hash_equals($user['password_hash'], $password)) {
            $this->initSession($user);
            $this->redirect('/dashboard');
        }

        $this->view('auth/login', ['error' => 'Invalid credentials']);
    }

    private function initSession(array $user): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Regenerate session ID to prevent fixation
        session_regenerate_id(true);

        $_SESSION['user_id']  = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role']     = $user['role'];
    }
}