<?php

class StaffController extends Controller {
    private $staffModel;
    private $attendanceModel;
    
    public function __construct() {
        parent::__construct();
        $this->requireRole(['admin', 'super_admin']);
        $this->staffModel = new Staff();
        $this->attendanceModel = new Attendance();
    }
    
    /**
     * Show staff management page
     */
    public function index() {
        $staff = $this->staffModel->getActiveStaff();
        $stats = $this->staffModel->getStatistics();
        
        $data = [
            'staff' => $staff,
            'stats' => $stats,
            'csrf_token' => Security::generateCsrfToken()
        ];
        
        $this->view('staff/index', $data);
    }
    
    /**
     * Create new staff member (AJAX)
     */
    public function create() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['status' => 'error', 'message' => 'Invalid request method'], 405);
        }
        
        $required = ['name', 'contact_number', 'position'];
        $data = $this->getPostData($required);
        
        // Optional fields
        $data['salary'] = (float)($_POST['salary'] ?? 0);
        $data['hire_date'] = Security::sanitizeInput($_POST['hire_date'] ?? date('Y-m-d'));
        
        // Validate hire date
        if (!Security::validateDate($data['hire_date'])) {
            $this->json(['status' => 'error', 'message' => 'Invalid hire date format']);
        }
        
        // Validate salary
        if (!Security::validateNumeric($data['salary'], 0)) {
            $this->json(['status' => 'error', 'message' => 'Invalid salary amount']);
        }
        
        try {
            $staffId = $this->staffModel->createStaff($data);
            if ($staffId) {
                $this->json(['status' => 'success', 'message' => 'Staff member added successfully', 'staff_id' => $staffId]);
            } else {
                $this->json(['status' => 'error', 'message' => 'Failed to add staff member']);
            }
        } catch (Exception $e) {
            error_log("Staff creation error: " . $e->getMessage());
            $this->json(['status' => 'error', 'message' => 'An error occurred while adding the staff member']);
        }
    }
    
    /**
     * Update staff member (AJAX)
     */
    public function update() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['status' => 'error', 'message' => 'Invalid request method'], 405);
        }
        
        $staffId = (int)($_POST['staff_id'] ?? 0);
        if (!$staffId) {
            $this->json(['status' => 'error', 'message' => 'Invalid staff ID']);
        }
        
        // Validate CSRF token
        if (!Security::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->json(['status' => 'error', 'message' => 'Invalid CSRF token'], 403);
        }
        
        $data = [];
        if (!empty($_POST['name'])) {
            $data['name'] = Security::sanitizeInput($_POST['name']);
        }
        if (!empty($_POST['contact_number'])) {
            $data['contact_number'] = Security::sanitizeInput($_POST['contact_number']);
        }
        if (!empty($_POST['position'])) {
            $data['position'] = Security::sanitizeInput($_POST['position']);
        }
        if (isset($_POST['salary'])) {
            $data['salary'] = (float)$_POST['salary'];
            if (!Security::validateNumeric($data['salary'], 0)) {
                $this->json(['status' => 'error', 'message' => 'Invalid salary amount']);
            }
        }
        if (!empty($_POST['status'])) {
            $data['status'] = Security::sanitizeInput($_POST['status']);
        }
        
        try {
            $result = $this->staffModel->updateStaff($staffId, $data);
            if ($result) {
                $this->json(['status' => 'success', 'message' => 'Staff member updated successfully']);
            } else {
                $this->json(['status' => 'error', 'message' => 'Failed to update staff member']);
            }
        } catch (Exception $e) {
            error_log("Staff update error: " . $e->getMessage());
            $this->json(['status' => 'error', 'message' => 'An error occurred while updating the staff member']);
        }
    }
    
    /**
     * Delete staff member (AJAX)
     */
    public function delete() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['status' => 'error', 'message' => 'Invalid request method'], 405);
        }
        
        $staffId = (int)($_POST['staff_id'] ?? 0);
        if (!$staffId) {
            $this->json(['status' => 'error', 'message' => 'Invalid staff ID']);
        }
        
        // Validate CSRF token
        if (!Security::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->json(['status' => 'error', 'message' => 'Invalid CSRF token'], 403);
        }
        
        try {
            // Soft delete by setting status to inactive
            $result = $this->staffModel->updateStaff($staffId, ['status' => 'inactive']);
            if ($result) {
                $this->json(['status' => 'success', 'message' => 'Staff member deactivated successfully']);
            } else {
                $this->json(['status' => 'error', 'message' => 'Failed to deactivate staff member']);
            }
        } catch (Exception $e) {
            error_log("Staff deletion error: " . $e->getMessage());
            $this->json(['status' => 'error', 'message' => 'An error occurred while deactivating the staff member']);
        }
    }
    
    /**
     * Get staff list (AJAX)
     */
    public function list() {
        $staff = $this->staffModel->getActiveStaff();
        $this->json(['status' => 'success', 'staff' => $staff]);
    }
    
    /**
     * Get staff details (AJAX)
     */
    public function details() {
        $staffId = (int)($_POST['staff_id'] ?? 0);
        if (!$staffId) {
            $this->json(['status' => 'error', 'message' => 'Invalid staff ID']);
        }
        
        $staff = $this->staffModel->find($staffId);
        if (!$staff) {
            $this->json(['status' => 'error', 'message' => 'Staff member not found']);
        }
        
        $this->json(['status' => 'success', 'staff' => $staff]);
    }
}