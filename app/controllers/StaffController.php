<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\Staff;
use App\Models\Attendance;
use App\Helpers\Validator;
use App\Helpers\Security;
use App\Middlewares\AuthMiddleware;
use App\Middlewares\CSRFMiddleware;

class StaffController extends Controller {
    private $staffModel;
    private $attendanceModel;
    
    public function __construct($params) {
        parent::__construct($params);
        
        $this->staffModel = new Staff();
        $this->attendanceModel = new Attendance();
    }
    
    protected function before() {
        AuthMiddleware::check();
        CSRFMiddleware::validate();
    }
    
    /**
     * List staff members
     */
    public function index() {
        $month = $_GET['month'] ?? date('n');
        $year = $_GET['year'] ?? date('Y');
        
        $staff = $this->staffModel->getStaffWithStats($month, $year);
        
        $this->renderWithLayout('staff/index', [
            'title' => 'Staff Management',
            'staff' => $staff,
            'selectedMonth' => $month,
            'selectedYear' => $year
        ]);
    }
    
    /**
     * Show create staff form
     */
    public function create() {
        AuthMiddleware::checkRole(['admin', 'super_admin']);
        
        $this->renderWithLayout('staff/create', [
            'title' => 'Add New Staff'
        ]);
    }
    
    /**
     * Store new staff member
     */
    public function store() {
        AuthMiddleware::checkRole(['admin', 'super_admin']);
        
        // Validate input
        $validator = new Validator($_POST);
        $valid = $validator->validate([
            'name' => 'required|min:3|max:100',
            'email' => 'email',
            'phone' => 'required|numeric',
            'department' => 'required',
            'position' => 'required',
            'join_date' => 'required|date',
            'salary' => 'numeric|min:0',
            'commission_rate' => 'numeric|min:0|max:100'
        ]);
        
        if (!$valid) {
            setFlash('error', $validator->getFirstError());
            $this->redirect(baseUrl('staff/create'));
        }
        
        // Prepare data
        $data = [
            'name' => Security::sanitize($_POST['name']),
            'email' => Security::sanitize($_POST['email'] ?? ''),
            'phone' => Security::sanitize($_POST['phone']),
            'department' => $_POST['department'],
            'position' => Security::sanitize($_POST['position']),
            'join_date' => $_POST['join_date'],
            'salary' => $_POST['salary'] ?? 0,
            'commission_rate' => $_POST['commission_rate'] ?? 0,
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        try {
            $staffId = $this->staffModel->create($data);
            setFlash('success', 'Staff member added successfully');
            $this->redirect(baseUrl('staff/' . $staffId));
        } catch (\Exception $e) {
            setFlash('error', 'Failed to add staff member: ' . $e->getMessage());
            $this->redirect(baseUrl('staff/create'));
        }
    }
    
    /**
     * Show staff details
     */
    public function show() {
        $id = $this->routeParams['id'] ?? 0;
        $staff = $this->staffModel->findById($id);
        
        if (!$staff) {
            setFlash('error', 'Staff member not found');
            $this->redirect(baseUrl('staff'));
        }
        
        // Get performance data
        $dateFrom = date('Y-m-01');
        $dateTo = date('Y-m-d');
        $performance = $this->staffModel->getPerformanceReport($id, $dateFrom, $dateTo);
        
        // Get attendance summary
        $attendance = $this->attendanceModel->getStaffAttendance($id);
        
        $this->renderWithLayout('staff/show', [
            'title' => 'Staff Details',
            'staff' => $staff,
            'performance' => $performance,
            'attendance' => $attendance
        ]);
    }
    
    /**
     * Show edit form
     */
    public function edit() {
        AuthMiddleware::checkRole(['admin', 'super_admin']);
        
        $id = $this->routeParams['id'] ?? 0;
        $staff = $this->staffModel->findById($id);
        
        if (!$staff) {
            setFlash('error', 'Staff member not found');
            $this->redirect(baseUrl('staff'));
        }
        
        $this->renderWithLayout('staff/edit', [
            'title' => 'Edit Staff',
            'staff' => $staff
        ]);
    }
    
    /**
     * Update staff member
     */
    public function update() {
        AuthMiddleware::checkRole(['admin', 'super_admin']);
        
        $id = $this->routeParams['id'] ?? 0;
        
        // Validate and update similar to store method
        // ... validation code ...
        
        try {
            $this->staffModel->update($id, $data);
            setFlash('success', 'Staff member updated successfully');
            $this->redirect(baseUrl('staff/' . $id));
        } catch (\Exception $e) {
            setFlash('error', 'Failed to update staff member: ' . $e->getMessage());
            $this->redirect(baseUrl('staff/' . $id . '/edit'));
        }
    }
    
    /**
     * Toggle staff status
     */
    public function toggleStatus() {
        AuthMiddleware::checkRole(['admin', 'super_admin']);
        
        $id = $this->routeParams['id'] ?? 0;
        $staff = $this->staffModel->findById($id);
        
        if (!$staff) {
            $this->json(['status' => 'error', 'message' => 'Staff member not found'], 404);
        }
        
        $newStatus = $staff['status'] === 'active' ? 'inactive' : 'active';
        
        try {
            $this->staffModel->update($id, ['status' => $newStatus]);
            $this->json(['status' => 'success', 'message' => 'Status updated successfully']);
        } catch (\Exception $e) {
            $this->json(['status' => 'error', 'message' => 'Failed to update status']);
        }
    }
}