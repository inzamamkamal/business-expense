<?php

class SettlementController extends Controller {
    private $settlementModel;
    private $staffModel;
    
    public function __construct() {
        parent::__construct();
        $this->requireRole(['admin', 'super_admin']);
        $this->settlementModel = new Settlement();
        $this->staffModel = new Staff();
    }
    
    /**
     * Show settlement management page
     */
    public function index() {
        $startDate = Security::sanitizeInput($_GET['start_date'] ?? date('Y-m-01'));
        $endDate = Security::sanitizeInput($_GET['end_date'] ?? date('Y-m-t'));
        $staffId = (int)($_GET['staff_id'] ?? 0);
        
        $settlements = $this->settlementModel->getSettlementsWithStaff($startDate, $endDate, $staffId ?: null);
        $staff = $this->staffModel->getActiveStaff();
        
        $data = [
            'settlements' => $settlements,
            'staff' => $staff,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'staff_id' => $staffId
            ],
            'csrf_token' => Security::generateCsrfToken()
        ];
        
        $this->view('settlement/index', $data);
    }
    
    /**
     * Add new settlement record (AJAX)
     */
    public function add() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['status' => 'error', 'message' => 'Invalid request method'], 405);
        }
        
        $required = ['staff_id', 'amount', 'type', 'settlement_date'];
        $data = $this->getPostData($required);
        
        $data['staff_id'] = (int)$data['staff_id'];
        $data['description'] = Security::sanitizeInput($_POST['description'] ?? '');
        $data['payment_method'] = Security::sanitizeInput($_POST['payment_method'] ?? 'cash');
        $data['processed_by'] = Security::sanitizeInput($_POST['processed_by'] ?? Auth::username());
        
        // Validate amount
        if (!Security::validateNumeric($data['amount'], 0.01)) {
            $this->json(['status' => 'error', 'message' => 'Invalid amount']);
        }
        
        // Validate date
        if (!Security::validateDate($data['settlement_date'])) {
            $this->json(['status' => 'error', 'message' => 'Invalid date format']);
        }
        
        // Validate type
        $validTypes = ['salary', 'bonus', 'advance', 'deduction'];
        if (!in_array($data['type'], $validTypes)) {
            $this->json(['status' => 'error', 'message' => 'Invalid settlement type']);
        }
        
        // Validate staff exists
        $staff = $this->staffModel->find($data['staff_id']);
        if (!$staff) {
            $this->json(['status' => 'error', 'message' => 'Staff member not found']);
        }
        
        try {
            $settlementId = $this->settlementModel->createSettlement($data);
            if ($settlementId) {
                $this->json(['status' => 'success', 'message' => 'Settlement record added successfully', 'settlement_id' => $settlementId]);
            } else {
                $this->json(['status' => 'error', 'message' => 'Failed to add settlement record']);
            }
        } catch (Exception $e) {
            error_log("Settlement creation error: " . $e->getMessage());
            $this->json(['status' => 'error', 'message' => 'An error occurred while adding the settlement record']);
        }
    }
    
    /**
     * Update settlement record (AJAX)
     */
    public function update() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['status' => 'error', 'message' => 'Invalid request method'], 405);
        }
        
        $settlementId = (int)($_POST['settlement_id'] ?? 0);
        if (!$settlementId) {
            $this->json(['status' => 'error', 'message' => 'Invalid settlement ID']);
        }
        
        // Validate CSRF token
        if (!Security::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->json(['status' => 'error', 'message' => 'Invalid CSRF token'], 403);
        }
        
        $data = [];
        if (isset($_POST['amount'])) {
            $data['amount'] = (float)$_POST['amount'];
            if (!Security::validateNumeric($data['amount'], 0.01)) {
                $this->json(['status' => 'error', 'message' => 'Invalid amount']);
            }
        }
        if (!empty($_POST['settlement_date'])) {
            $data['settlement_date'] = Security::sanitizeInput($_POST['settlement_date']);
            if (!Security::validateDate($data['settlement_date'])) {
                $this->json(['status' => 'error', 'message' => 'Invalid date format']);
            }
        }
        if (!empty($_POST['type'])) {
            $validTypes = ['salary', 'bonus', 'advance', 'deduction'];
            if (!in_array($_POST['type'], $validTypes)) {
                $this->json(['status' => 'error', 'message' => 'Invalid settlement type']);
            }
            $data['type'] = Security::sanitizeInput($_POST['type']);
        }
        if (isset($_POST['description'])) {
            $data['description'] = Security::sanitizeInput($_POST['description']);
        }
        if (!empty($_POST['payment_method'])) {
            $data['payment_method'] = Security::sanitizeInput($_POST['payment_method']);
        }
        
        try {
            $result = $this->settlementModel->update($settlementId, $data);
            if ($result) {
                $this->json(['status' => 'success', 'message' => 'Settlement record updated successfully']);
            } else {
                $this->json(['status' => 'error', 'message' => 'Failed to update settlement record']);
            }
        } catch (Exception $e) {
            error_log("Settlement update error: " . $e->getMessage());
            $this->json(['status' => 'error', 'message' => 'An error occurred while updating the settlement record']);
        }
    }
    
    /**
     * Delete settlement record (AJAX)
     */
    public function delete() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['status' => 'error', 'message' => 'Invalid request method'], 405);
        }
        
        $settlementId = (int)($_POST['settlement_id'] ?? 0);
        if (!$settlementId) {
            $this->json(['status' => 'error', 'message' => 'Invalid settlement ID']);
        }
        
        // Validate CSRF token
        if (!Security::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->json(['status' => 'error', 'message' => 'Invalid CSRF token'], 403);
        }
        
        try {
            $result = $this->settlementModel->delete($settlementId);
            if ($result) {
                $this->json(['status' => 'success', 'message' => 'Settlement record deleted successfully']);
            } else {
                $this->json(['status' => 'error', 'message' => 'Failed to delete settlement record']);
            }
        } catch (Exception $e) {
            error_log("Settlement deletion error: " . $e->getMessage());
            $this->json(['status' => 'error', 'message' => 'An error occurred while deleting the settlement record']);
        }
    }
    
    /**
     * Get staff balance (AJAX)
     */
    public function balance() {
        $staffId = (int)($_POST['staff_id'] ?? 0);
        $startDate = Security::sanitizeInput($_POST['start_date'] ?? '');
        $endDate = Security::sanitizeInput($_POST['end_date'] ?? '');
        
        if (!$staffId) {
            $this->json(['status' => 'error', 'message' => 'Staff ID required']);
        }
        
        $balance = $this->settlementModel->getStaffBalance($staffId, $startDate ?: null, $endDate ?: null);
        
        $this->json(['status' => 'success', 'balance' => $balance]);
    }
    
    /**
     * Get monthly settlement report
     */
    public function monthlyReport() {
        $year = (int)($_GET['year'] ?? date('Y'));
        $month = (int)($_GET['month'] ?? date('m'));
        
        $report = $this->settlementModel->getMonthlyReport($year, $month);
        
        $data = [
            'report' => $report,
            'year' => $year,
            'month' => $month,
            'month_name' => date('F', mktime(0, 0, 0, $month, 1, $year))
        ];
        
        $this->view('settlement/monthly_report', $data);
    }
}