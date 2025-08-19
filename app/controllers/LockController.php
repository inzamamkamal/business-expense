<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\Lock;
use App\Helpers\Validator;
use App\Middlewares\AuthMiddleware;
use App\Middlewares\CSRFMiddleware;

class LockController extends Controller {
    private $lockModel;
    
    public function __construct($params) {
        parent::__construct($params);
        $this->lockModel = new Lock();
    }
    
    protected function before() {
        AuthMiddleware::check();
        AuthMiddleware::checkRole(['admin', 'super_admin']);
        CSRFMiddleware::validate();
    }
    
    public function index() {
        $month = $_GET['month'] ?? date('n');
        $year = $_GET['year'] ?? date('Y');
        
        $lockedDates = $this->lockModel->getLockedDatesForMonth($month, $year);
        
        // Get calendar data
        $firstDay = mktime(0, 0, 0, $month, 1, $year);
        $daysInMonth = date('t', $firstDay);
        $startDayOfWeek = date('w', $firstDay);
        
        $this->renderWithLayout('lock/index', [
            'title' => 'Lock Dates',
            'lockedDates' => $lockedDates,
            'selectedMonth' => $month,
            'selectedYear' => $year,
            'daysInMonth' => $daysInMonth,
            'startDayOfWeek' => $startDayOfWeek,
            'currentDate' => date('Y-m-d')
        ]);
    }
    
    public function lock() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(baseUrl('lock-dates'));
        }
        
        $date = $_POST['date'] ?? null;
        
        if (!$date || !Security::isValidDate($date)) {
            $this->json(['status' => 'error', 'message' => 'Invalid date provided']);
        }
        
        // Check if date is in the future
        if (strtotime($date) > strtotime(date('Y-m-d'))) {
            $this->json(['status' => 'error', 'message' => 'Cannot lock future dates']);
        }
        
        if ($this->lockModel->lockDate($date, userId())) {
            $this->json(['status' => 'success', 'message' => 'Date locked successfully']);
        } else {
            $this->json(['status' => 'error', 'message' => 'Date is already locked']);
        }
    }
    
    public function unlock() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(baseUrl('lock-dates'));
        }
        
        $date = $_POST['date'] ?? null;
        
        if (!$date || !Security::isValidDate($date)) {
            $this->json(['status' => 'error', 'message' => 'Invalid date provided']);
        }
        
        if ($this->lockModel->unlockDate($date)) {
            $this->json(['status' => 'success', 'message' => 'Date unlocked successfully']);
        } else {
            $this->json(['status' => 'error', 'message' => 'Failed to unlock date']);
        }
    }
}