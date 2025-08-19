<?php

class IncomeController extends Controller {
    private $incomeModel;
    
    public function __construct() {
        parent::__construct();
        $this->requireRole(['admin', 'super_admin']);
        $this->incomeModel = new Income();
    }
    
    /**
     * Show income management page
     */
    public function index() {
        $startDate = Security::sanitizeInput($_GET['start_date'] ?? date('Y-m-01'));
        $endDate = Security::sanitizeInput($_GET['end_date'] ?? date('Y-m-t'));
        $category = Security::sanitizeInput($_GET['category'] ?? '');
        
        $records = $this->incomeModel->getIncomeRecords($startDate, $endDate, $category ?: null);
        $summary = $this->incomeModel->getIncomeSummary($startDate, $endDate);
        
        $data = [
            'records' => $records,
            'summary' => $summary,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'category' => $category
            ],
            'csrf_token' => Security::generateCsrfToken()
        ];
        
        $this->view('income/index', $data);
    }
    
    /**
     * Add new income record (AJAX)
     */
    public function add() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['status' => 'error', 'message' => 'Invalid request method'], 405);
        }
        
        $required = ['source', 'amount', 'date'];
        $data = $this->getPostData($required);
        
        // Optional fields
        $data['description'] = Security::sanitizeInput($_POST['description'] ?? '');
        $data['category'] = Security::sanitizeInput($_POST['category'] ?? 'general');
        $data['payment_method'] = Security::sanitizeInput($_POST['payment_method'] ?? 'cash');
        $data['received_by'] = Security::sanitizeInput($_POST['received_by'] ?? Auth::username());
        
        // Validate amount
        if (!Security::validateNumeric($data['amount'], 0.01)) {
            $this->json(['status' => 'error', 'message' => 'Invalid amount']);
        }
        
        // Validate date
        if (!Security::validateDate($data['date'])) {
            $this->json(['status' => 'error', 'message' => 'Invalid date format']);
        }
        
        try {
            $incomeId = $this->incomeModel->createIncome($data);
            if ($incomeId) {
                $this->json(['status' => 'success', 'message' => 'Income record added successfully', 'income_id' => $incomeId]);
            } else {
                $this->json(['status' => 'error', 'message' => 'Failed to add income record']);
            }
        } catch (Exception $e) {
            error_log("Income creation error: " . $e->getMessage());
            $this->json(['status' => 'error', 'message' => 'An error occurred while adding the income record']);
        }
    }
    
    /**
     * Update income record (AJAX)
     */
    public function update() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['status' => 'error', 'message' => 'Invalid request method'], 405);
        }
        
        $incomeId = (int)($_POST['income_id'] ?? 0);
        if (!$incomeId) {
            $this->json(['status' => 'error', 'message' => 'Invalid income ID']);
        }
        
        // Validate CSRF token
        if (!Security::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->json(['status' => 'error', 'message' => 'Invalid CSRF token'], 403);
        }
        
        $data = [];
        if (!empty($_POST['source'])) {
            $data['source'] = Security::sanitizeInput($_POST['source']);
        }
        if (isset($_POST['amount'])) {
            $data['amount'] = (float)$_POST['amount'];
            if (!Security::validateNumeric($data['amount'], 0.01)) {
                $this->json(['status' => 'error', 'message' => 'Invalid amount']);
            }
        }
        if (!empty($_POST['date'])) {
            $data['date'] = Security::sanitizeInput($_POST['date']);
            if (!Security::validateDate($data['date'])) {
                $this->json(['status' => 'error', 'message' => 'Invalid date format']);
            }
        }
        if (isset($_POST['description'])) {
            $data['description'] = Security::sanitizeInput($_POST['description']);
        }
        if (!empty($_POST['category'])) {
            $data['category'] = Security::sanitizeInput($_POST['category']);
        }
        if (!empty($_POST['payment_method'])) {
            $data['payment_method'] = Security::sanitizeInput($_POST['payment_method']);
        }
        
        try {
            $result = $this->incomeModel->update($incomeId, $data);
            if ($result) {
                $this->json(['status' => 'success', 'message' => 'Income record updated successfully']);
            } else {
                $this->json(['status' => 'error', 'message' => 'Failed to update income record']);
            }
        } catch (Exception $e) {
            error_log("Income update error: " . $e->getMessage());
            $this->json(['status' => 'error', 'message' => 'An error occurred while updating the income record']);
        }
    }
    
    /**
     * Delete income record (AJAX)
     */
    public function delete() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['status' => 'error', 'message' => 'Invalid request method'], 405);
        }
        
        $incomeId = (int)($_POST['income_id'] ?? 0);
        if (!$incomeId) {
            $this->json(['status' => 'error', 'message' => 'Invalid income ID']);
        }
        
        // Validate CSRF token
        if (!Security::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->json(['status' => 'error', 'message' => 'Invalid CSRF token'], 403);
        }
        
        try {
            $result = $this->incomeModel->delete($incomeId);
            if ($result) {
                $this->json(['status' => 'success', 'message' => 'Income record deleted successfully']);
            } else {
                $this->json(['status' => 'error', 'message' => 'Failed to delete income record']);
            }
        } catch (Exception $e) {
            error_log("Income deletion error: " . $e->getMessage());
            $this->json(['status' => 'error', 'message' => 'An error occurred while deleting the income record']);
        }
    }
    
    /**
     * Get income summary (AJAX)
     */
    public function summary() {
        $startDate = Security::sanitizeInput($_POST['start_date'] ?? '');
        $endDate = Security::sanitizeInput($_POST['end_date'] ?? '');
        
        $summary = $this->incomeModel->getIncomeSummary($startDate ?: null, $endDate ?: null);
        
        $this->json(['status' => 'success', 'summary' => $summary]);
    }
    
    /**
     * Get monthly income report
     */
    public function monthlyReport() {
        $year = (int)($_GET['year'] ?? date('Y'));
        $month = (int)($_GET['month'] ?? date('m'));
        
        $report = $this->incomeModel->getMonthlyReport($year, $month);
        
        $data = [
            'report' => $report,
            'year' => $year,
            'month' => $month,
            'month_name' => date('F', mktime(0, 0, 0, $month, 1, $year))
        ];
        
        $this->view('income/monthly_report', $data);
    }
}