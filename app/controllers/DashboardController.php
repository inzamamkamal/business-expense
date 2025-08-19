<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\Booking;
use App\Models\Income;
use App\Models\Expense;
use App\Models\Staff;
use App\Models\Attendance;
use App\Middlewares\AuthMiddleware;

class DashboardController extends Controller {
    private $bookingModel;
    private $incomeModel;
    private $expenseModel;
    private $staffModel;
    private $attendanceModel;
    
    public function __construct($params) {
        parent::__construct($params);
        
        // Initialize models
        $this->bookingModel = new Booking();
        $this->incomeModel = new Income();
        $this->expenseModel = new Expense();
        $this->staffModel = new Staff();
        $this->attendanceModel = new Attendance();
    }
    
    protected function before() {
        AuthMiddleware::check();
    }
    
    /**
     * Dashboard index
     */
    public function index() {
        $today = date('Y-m-d');
        $currentMonth = date('n');
        $currentYear = date('Y');
        
        // Get today's statistics
        $bookingSummary = $this->bookingModel->getDailySummary($today);
        $incomeSummary = $this->incomeModel->getDailySummary($today);
        $expenseSummary = $this->expenseModel->getDailySummary($today);
        
        // Get monthly statistics
        $monthlyBookings = $this->bookingModel->count([
            'MONTH(date)' => $currentMonth,
            'YEAR(date)' => $currentYear
        ]);
        
        // Get recent bookings
        $recentBookings = $this->bookingModel->getFilteredBookings([
            'date_from' => date('Y-m-d', strtotime('-7 days')),
            'date_to' => $today
        ]);
        
        // Get staff statistics
        $activeStaff = $this->staffModel->count(['status' => 'active']);
        $todayAttendance = $this->attendanceModel->count([
            'date' => $today,
            'status' => 'present'
        ]);
        
        // Calculate net profit
        $totalIncome = $bookingSummary['total_revenue'] + $incomeSummary['total_income'];
        $totalExpense = $expenseSummary['total_expense'];
        $netProfit = $totalIncome - $totalExpense;
        
        $this->renderWithLayout('dashboard/index', [
            'title' => 'Dashboard',
            'todayDate' => $today,
            'bookingSummary' => $bookingSummary,
            'incomeSummary' => $incomeSummary,
            'expenseSummary' => $expenseSummary,
            'monthlyBookings' => $monthlyBookings,
            'recentBookings' => array_slice($recentBookings, 0, 5),
            'activeStaff' => $activeStaff,
            'todayAttendance' => $todayAttendance,
            'netProfit' => $netProfit
        ]);
    }
    
    /**
     * Switch database mode
     */
    public function switchDatabase() {
        AuthMiddleware::checkRole(['admin', 'super_admin']);
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $mode = $_POST['db_mode'] ?? 'current';
            Session::set('db_mode', $mode);
            
            setFlash('success', 'Database mode switched successfully');
        }
        
        $this->redirect(baseUrl('dashboard'));
    }
}