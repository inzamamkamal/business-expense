<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\Booking;
use App\Models\Income;
use App\Models\Expense;
use App\Models\Staff;
use App\Models\Attendance;
use App\Middlewares\AuthMiddleware;

class ReportController extends Controller {
    private $bookingModel;
    private $incomeModel;
    private $expenseModel;
    private $staffModel;
    private $attendanceModel;
    
    public function __construct($params) {
        parent::__construct($params);
        
        $this->bookingModel = new Booking();
        $this->incomeModel = new Income();
        $this->expenseModel = new Expense();
        $this->staffModel = new Staff();
        $this->attendanceModel = new Attendance();
    }
    
    protected function before() {
        AuthMiddleware::check();
    }
    
    public function index() {
        $this->renderWithLayout('reports/index', [
            'title' => 'Reports Dashboard'
        ]);
    }
    
    public function booking() {
        $dateFrom = $_GET['date_from'] ?? date('Y-m-01');
        $dateTo = $_GET['date_to'] ?? date('Y-m-d');
        
        $bookings = $this->bookingModel->getFilteredBookings([
            'date_from' => $dateFrom,
            'date_to' => $dateTo
        ]);
        
        // Calculate statistics
        $stats = [
            'total_bookings' => count($bookings),
            'total_revenue' => array_sum(array_column($bookings, 'total_amount')),
            'confirmed_bookings' => count(array_filter($bookings, fn($b) => $b['status'] === 'confirmed')),
            'cancelled_bookings' => count(array_filter($bookings, fn($b) => $b['status'] === 'cancelled')),
            'paid_amount' => array_sum(array_map(fn($b) => $b['payment_status'] === 'paid' ? $b['total_amount'] : 0, $bookings)),
            'pending_amount' => array_sum(array_map(fn($b) => $b['payment_status'] === 'pending' ? $b['total_amount'] : 0, $bookings))
        ];
        
        // Room occupancy analysis
        $roomOccupancy = [];
        foreach ($bookings as $booking) {
            $room = $booking['room_number'];
            if (!isset($roomOccupancy[$room])) {
                $roomOccupancy[$room] = ['count' => 0, 'revenue' => 0];
            }
            $roomOccupancy[$room]['count']++;
            $roomOccupancy[$room]['revenue'] += $booking['total_amount'];
        }
        
        $this->renderWithLayout('reports/booking', [
            'title' => 'Booking Reports',
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'bookings' => $bookings,
            'stats' => $stats,
            'roomOccupancy' => $roomOccupancy
        ]);
    }
    
    public function financial() {
        $dateFrom = $_GET['date_from'] ?? date('Y-m-01');
        $dateTo = $_GET['date_to'] ?? date('Y-m-d');
        
        // Get all financial data
        $bookings = $this->bookingModel->getFilteredBookings([
            'date_from' => $dateFrom,
            'date_to' => $dateTo
        ]);
        
        $income = $this->incomeModel->getFilteredIncome([
            'date_from' => $dateFrom,
            'date_to' => $dateTo
        ]);
        
        $expenses = $this->expenseModel->getFilteredExpenses([
            'date_from' => $dateFrom,
            'date_to' => $dateTo
        ]);
        
        // Calculate summaries
        $bookingRevenue = array_sum(array_column($bookings, 'total_amount'));
        $otherIncome = array_sum(array_column($income, 'amount'));
        $totalIncome = $bookingRevenue + $otherIncome;
        $totalExpenses = array_sum(array_column($expenses, 'amount'));
        $netProfit = $totalIncome - $totalExpenses;
        
        // Category breakdowns
        $incomeByCategory = $this->incomeModel->getIncomeByCategory($dateFrom, $dateTo);
        $expenseByCategory = $this->expenseModel->getExpenseByCategory($dateFrom, $dateTo);
        
        // Daily trend data
        $dailyTrend = $this->calculateDailyTrend($dateFrom, $dateTo, $bookings, $income, $expenses);
        
        $this->renderWithLayout('reports/financial', [
            'title' => 'Financial Reports',
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'bookingRevenue' => $bookingRevenue,
            'otherIncome' => $otherIncome,
            'totalIncome' => $totalIncome,
            'totalExpenses' => $totalExpenses,
            'netProfit' => $netProfit,
            'incomeByCategory' => $incomeByCategory,
            'expenseByCategory' => $expenseByCategory,
            'dailyTrend' => $dailyTrend
        ]);
    }
    
    public function staff() {
        $month = $_GET['month'] ?? date('n');
        $year = $_GET['year'] ?? date('Y');
        
        // Get staff performance data
        $staffData = $this->staffModel->getStaffWithStats($month, $year);
        $attendanceSummary = $this->attendanceModel->getMonthlySummary($month, $year);
        
        // Create attendance map
        $attendanceMap = [];
        foreach ($attendanceSummary as $record) {
            $attendanceMap[$record['id']] = $record;
        }
        
        // Merge data
        foreach ($staffData as &$staff) {
            if (isset($attendanceMap[$staff['id']])) {
                $staff['attendance'] = $attendanceMap[$staff['id']];
            }
        }
        
        $this->renderWithLayout('reports/staff', [
            'title' => 'Staff Reports',
            'selectedMonth' => $month,
            'selectedYear' => $year,
            'staffData' => $staffData
        ]);
    }
    
    private function calculateDailyTrend($dateFrom, $dateTo, $bookings, $income, $expenses) {
        $trend = [];
        $current = strtotime($dateFrom);
        $end = strtotime($dateTo);
        
        while ($current <= $end) {
            $date = date('Y-m-d', $current);
            
            $dayBookings = array_filter($bookings, fn($b) => $b['date'] === $date);
            $dayIncome = array_filter($income, fn($i) => $i['date'] === $date);
            $dayExpenses = array_filter($expenses, fn($e) => $e['date'] === $date);
            
            $trend[$date] = [
                'booking_revenue' => array_sum(array_column($dayBookings, 'total_amount')),
                'other_income' => array_sum(array_column($dayIncome, 'amount')),
                'expenses' => array_sum(array_column($dayExpenses, 'amount'))
            ];
            
            $current = strtotime('+1 day', $current);
        }
        
        return $trend;
    }
}