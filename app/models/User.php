<?php
namespace App\Models;

use App\Core\Model;
use App\Helpers\Security;

class User extends Model {
    protected $table = 'users';
    
    /**
     * Find user by username
     */
    public function findByUsername($username) {
        $stmt = $this->query("SELECT * FROM {$this->table} WHERE username = :username LIMIT 1", [
            'username' => $username
        ]);
        return $stmt->fetch();
    }
    
    /**
     * Authenticate user
     */
    public function authenticate($username, $password) {
        $user = $this->findByUsername($username);
        
        if ($user && $password === $user['password_hash']) {
            // For now keeping the plain password check as per original code
            // TODO: Update to use proper password hashing
            return $user;
        }
        
        return false;
    }
    
    /**
     * Create new user
     */
    public function createUser($data) {
        // TODO: Hash password properly when creating users
        return $this->create($data);
    }
    
    /**
     * Update user
     */
    public function updateUser($id, $data) {
        // Remove sensitive fields that shouldn't be updated directly
        unset($data['id'], $data['created_at']);
        
        return $this->update($id, $data);
    }
    
    /**
     * Get users by role
     */
    public function getUsersByRole($role) {
        return $this->findAll(['role' => $role]);
    }
}