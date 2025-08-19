<?php

class AttendanceController extends Controller {
    private $attendanceModel;
    private $staffModel;
    
    public function __construct() {
        parent::__construct();
        $this->attendanceModel = new Attendance();
        $this->staffModel = new Staff();
    }
    
    /**
     * Show attendance page
     */
    public function index() {
        $date = Security::sanitizeInput($_GET['date'] ?? date('Y-m-d'));
        $staffId = (int)($_GET['staff_id'] ?? 0);
        
        $attendance = $this->attendanceModel->getDailyAttendance($date);
        $staff = $this->staffModel->getActiveStaff();
        
        $data = [
            'attendance' => $attendance,
            'staff' => $staff,
            'selected_date' => $date,
            'selected_staff' => $staffId,
            'csrf_token' => Security::generateCsrfToken()
        ];
        
        $this->view('attendance/index', $data);
    }
    
    /**
     * Mark attendance (AJAX)
     */
    public function mark() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['status' => 'error', 'message' => 'Invalid request method'], 405);
        }
        
        $required = ['staff_id', 'status'];
        $data = $this->getPostData($required);
        
        $data['staff_id'] = (int)$data['staff_id'];
        $data['date'] = Security::sanitizeInput($_POST['date'] ?? date('Y-m-d'));
        $data['check_in_time'] = Security::sanitizeInput($_POST['check_in_time'] ?? '');
        $data['check_out_time'] = Security::sanitizeInput($_POST['check_out_time'] ?? '');
        $data['notes'] = Security::sanitizeInput($_POST['notes'] ?? '');
        
        // Validate date
        if (!Security::validateDate($data['date'])) {
            $this->json(['status' => 'error', 'message' => 'Invalid date format']);
        }
        
        // Validate times if provided
        if (!empty($data['check_in_time']) && !Security::validateTime($data['check_in_time'])) {
            $this->json(['status' => 'error', 'message' => 'Invalid check-in time format']);
        }
        
        if (!empty($data['check_out_time']) && !Security::validateTime($data['check_out_time'])) {
            $this->json(['status' => 'error', 'message' => 'Invalid check-out time format']);
        }
        
        // Validate status
        $validStatuses = ['present', 'absent', 'late', 'half_day'];
        if (!in_array($data['status'], $validStatuses)) {
            $this->json(['status' => 'error', 'message' => 'Invalid attendance status']);
        }
        
        try {
            $result = $this->attendanceModel->markAttendance($data);
            if ($result) {
                $this->json(['status' => 'success', 'message' => 'Attendance marked successfully']);
            } else {
                $this->json(['status' => 'error', 'message' => 'Failed to mark attendance']);
            }
        } catch (Exception $e) {
            error_log("Attendance marking error: " . $e->getMessage());
            $this->json(['status' => 'error', 'message' => 'An error occurred while marking attendance']);
        }
    }
    
    /**
     * Get attendance records (AJAX)
     */
    public function records() {
        $startDate = Security::sanitizeInput($_POST['start_date'] ?? '');
        $endDate = Security::sanitizeInput($_POST['end_date'] ?? '');
        $staffId = (int)($_POST['staff_id'] ?? 0);
        
        $attendance = $this->attendanceModel->getAttendanceWithStaff($startDate, $endDate, $staffId ?: null);
        
        $this->json(['status' => 'success', 'attendance' => $attendance]);
    }
    
    /**
     * Get staff attendance summary (AJAX)
     */
    public function summary() {
        $staffId = (int)($_POST['staff_id'] ?? 0);
        $startDate = Security::sanitizeInput($_POST['start_date'] ?? '');
        $endDate = Security::sanitizeInput($_POST['end_date'] ?? '');
        
        if (!$staffId) {
            $this->json(['status' => 'error', 'message' => 'Staff ID required']);
        }
        
        $summary = $this->attendanceModel->getStaffAttendanceSummary($staffId, $startDate, $endDate);
        
        $this->json(['status' => 'success', 'summary' => $summary]);
    }
    
    /**
     * Get monthly attendance report
     */
    public function monthlyReport() {
        $this->requireRole(['admin', 'super_admin']);
        
        $year = (int)($_GET['year'] ?? date('Y'));
        $month = (int)($_GET['month'] ?? date('m'));
        
        $report = $this->attendanceModel->getMonthlyReport($year, $month);
        
        $data = [
            'report' => $report,
            'year' => $year,
            'month' => $month,
            'month_name' => date('F', mktime(0, 0, 0, $month, 1, $year))
        ];
        
        $this->view('attendance/monthly_report', $data);
    }
    
    /**
     * Get daily attendance for a specific date (AJAX)
     */
    public function daily() {
        $date = Security::sanitizeInput($_POST['date'] ?? date('Y-m-d'));
        
        if (!Security::validateDate($date)) {
            $this->json(['status' => 'error', 'message' => 'Invalid date format']);
        }
        
        $attendance = $this->attendanceModel->getDailyAttendance($date);
        
        $this->json(['status' => 'success', 'attendance' => $attendance]);
    }
}