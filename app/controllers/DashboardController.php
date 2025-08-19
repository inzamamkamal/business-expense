<?php

class DashboardController extends Controller {
    
    /**
     * Show dashboard
     */
    public function index() {
        $data = [
            'user' => [
                'username' => Auth::username(),
                'role' => Auth::role()
            ],
            'stats' => $this->getDashboardStats()
        ];
        
        $this->view('dashboard/index', $data);
    }
    
    /**
     * Get dashboard statistics
     */
    private function getDashboardStats() {
        $stats = [];
        
        try {
            // Booking stats
            $bookingModel = new Booking();
            $bookingStats = $bookingModel->getStatistics(date('Y-m-01'), date('Y-m-t'));
            $stats['bookings'] = $bookingStats;
            
            // Staff stats (admin only)
            if (Auth::isAdmin()) {
                $staffModel = new Staff();
                $staffStats = $staffModel->getStatistics();
                $stats['staff'] = $staffStats;
                
                // Income stats
                $incomeModel = new Income();
                $incomeStats = $incomeModel->getIncomeSummary(date('Y-m-01'), date('Y-m-t'));
                $stats['income'] = $incomeStats;
                
                // Expense stats
                $expenseModel = new Expense();
                $expenseStats = $expenseModel->getExpenseSummary(date('Y-m-01'), date('Y-m-t'));
                $stats['expenses'] = $expenseStats;
            }
            
            // Attendance stats
            $attendanceModel = new Attendance();
            $todayAttendance = $attendanceModel->getDailyAttendance(date('Y-m-d'));
            $stats['attendance'] = [
                'today' => $todayAttendance,
                'total_staff' => count($todayAttendance),
                'present_today' => count(array_filter($todayAttendance, function($a) { return $a['status'] === 'present'; }))
            ];
            
        } catch (Exception $e) {
            // Log error but don't break dashboard
            error_log("Dashboard stats error: " . $e->getMessage());
            $stats = ['error' => 'Unable to load statistics'];
        }
        
        return $stats;
    }
    
    /**
     * Get quick stats for AJAX requests
     */
    public function quickStats() {
        $this->json($this->getDashboardStats());
    }
}