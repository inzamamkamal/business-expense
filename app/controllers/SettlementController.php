<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\Booking;
use App\Models\Income;
use App\Models\Expense;
use App\Models\Staff;
use App\Middlewares\AuthMiddleware;

class SettlementController extends Controller {
    private $bookingModel;
    private $incomeModel; 
    private $expenseModel;
    private $staffModel;
    
    public function __construct($params) {
        parent::__construct($params);
        
        $this->bookingModel = new Booking();
        $this->incomeModel = new Income();
        $this->expenseModel = new Expense();
        $this->staffModel = new Staff();
    }
    
    protected function before() {
        AuthMiddleware::check();
        AuthMiddleware::checkRole(['admin', 'super_admin']);
    }
    
    public function index() {
        $month = $_GET['month'] ?? date('n');
        $year = $_GET['year'] ?? date('Y');
        $dateFrom = "$year-$month-01";
        $dateTo = date('Y-m-t', strtotime($dateFrom));
        
        // Get financial summary
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
        
        // Calculate totals
        $totalBookingRevenue = array_sum(array_column($bookings, 'total_amount'));
        $totalOtherIncome = array_sum(array_column($income, 'amount'));
        $totalExpenses = array_sum(array_column($expenses, 'amount'));
        $totalRevenue = $totalBookingRevenue + $totalOtherIncome;
        $netProfit = $totalRevenue - $totalExpenses;
        
        // Get staff commissions
        $staffCommissions = $this->calculateStaffCommissions($bookings);
        
        $this->renderWithLayout('settlement/index', [
            'title' => 'Monthly Settlement',
            'selectedMonth' => $month,
            'selectedYear' => $year,
            'bookings' => $bookings,
            'income' => $income,
            'expenses' => $expenses,
            'totalBookingRevenue' => $totalBookingRevenue,
            'totalOtherIncome' => $totalOtherIncome,
            'totalExpenses' => $totalExpenses,
            'totalRevenue' => $totalRevenue,
            'netProfit' => $netProfit,
            'staffCommissions' => $staffCommissions
        ]);
    }
    
    public function generate() {
        $month = $_POST['month'] ?? date('n');
        $year = $_POST['year'] ?? date('Y');
        
        // Generate settlement logic here
        // This would typically create a PDF or detailed report
        
        setFlash('success', 'Settlement generated successfully for ' . date('F Y', mktime(0, 0, 0, $month, 1, $year)));
        $this->redirect(baseUrl('settlement?month=' . $month . '&year=' . $year));
    }
    
    private function calculateStaffCommissions($bookings) {
        $commissions = [];
        
        foreach ($bookings as $booking) {
            if (!isset($booking['staff_id']) || !$booking['staff_id']) continue;
            
            $staffId = $booking['staff_id'];
            $staffName = $booking['staff_name'] ?? 'Unknown';
            $commission = $booking['commission_amount'] ?? 0;
            
            if (!isset($commissions[$staffId])) {
                $commissions[$staffId] = [
                    'name' => $staffName,
                    'total_bookings' => 0,
                    'total_revenue' => 0,
                    'total_commission' => 0
                ];
            }
            
            $commissions[$staffId]['total_bookings']++;
            $commissions[$staffId]['total_revenue'] += $booking['total_amount'];
            $commissions[$staffId]['total_commission'] += $commission;
        }
        
        return $commissions;
    }
}