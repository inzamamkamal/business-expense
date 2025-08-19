<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\Attendance;
use App\Models\Staff;
use App\Models\Lock;
use App\Middlewares\AuthMiddleware;
use App\Middlewares\CSRFMiddleware;

class AttendanceController extends Controller {
    private $attendanceModel;
    private $staffModel;
    private $lockModel;
    
    public function __construct($params) {
        parent::__construct($params);
        
        $this->attendanceModel = new Attendance();
        $this->staffModel = new Staff();
        $this->lockModel = new Lock();
    }
    
    protected function before() {
        AuthMiddleware::check();
        CSRFMiddleware::validate();
    }
    
    /**
     * Show attendance sheet
     */
    public function index() {
        $date = $_GET['date'] ?? date('Y-m-d');
        
        // Get all active staff
        $staff = $this->staffModel->getActiveStaff();
        
        // Get attendance for the date
        $attendance = $this->attendanceModel->getAttendanceByDate($date);
        
        // Create attendance map for easy lookup
        $attendanceMap = [];
        foreach ($attendance as $record) {
            $attendanceMap[$record['staff_id']] = $record;
        }
        
        $this->renderWithLayout('attendance/index', [
            'title' => 'Attendance Management',
            'date' => $date,
            'staff' => $staff,
            'attendanceMap' => $attendanceMap,
            'isLocked' => $this->lockModel->isDateLocked($date)
        ]);
    }
    
    /**
     * Mark attendance
     */
    public function mark() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(baseUrl('attendance'));
        }
        
        $date = $_POST['date'] ?? date('Y-m-d');
        
        // Check if date is locked
        if ($this->lockModel->isDateLocked($date)) {
            setFlash('error', 'Cannot mark attendance for locked date');
            $this->redirect(baseUrl('attendance?date=' . $date));
        }
        
        $attendanceData = $_POST['attendance'] ?? [];
        $successCount = 0;
        
        foreach ($attendanceData as $staffId => $data) {
            try {
                $this->attendanceModel->markAttendance(
                    $staffId,
                    $date,
                    $data['status'] ?? 'absent',
                    $data['check_in'] ?? null,
                    $data['check_out'] ?? null
                );
                $successCount++;
            } catch (\Exception $e) {
                // Log error but continue with other records
            }
        }
        
        setFlash('success', "Attendance marked for $successCount staff members");
        $this->redirect(baseUrl('attendance?date=' . $date));
    }
    
    /**
     * Monthly attendance report
     */
    public function report() {
        $month = $_GET['month'] ?? date('n');
        $year = $_GET['year'] ?? date('Y');
        
        $summary = $this->attendanceModel->getMonthlySummary($month, $year);
        
        $this->renderWithLayout('attendance/report', [
            'title' => 'Monthly Attendance Report',
            'summary' => $summary,
            'selectedMonth' => $month,
            'selectedYear' => $year
        ]);
    }
    
    /**
     * Individual staff attendance
     */
    public function staffAttendance() {
        $staffId = $this->routeParams['id'] ?? 0;
        $month = $_GET['month'] ?? date('n');
        $year = $_GET['year'] ?? date('Y');
        
        $staff = $this->staffModel->findById($staffId);
        if (!$staff) {
            setFlash('error', 'Staff member not found');
            $this->redirect(baseUrl('attendance'));
        }
        
        $attendance = $this->attendanceModel->getStaffAttendance($staffId, $month, $year);
        
        $this->renderWithLayout('attendance/staff', [
            'title' => 'Staff Attendance - ' . $staff['name'],
            'staff' => $staff,
            'attendance' => $attendance,
            'selectedMonth' => $month,
            'selectedYear' => $year
        ]);
    }
}