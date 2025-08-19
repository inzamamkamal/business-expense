<?php

class ExpenseController extends Controller {
    private $expenseModel;
    
    public function __construct() {
        parent::__construct();
        $this->expenseModel = new Expense();
    }
    
    /**
     * Show expense management page
     */
    public function index() {
        $startDate = Security::sanitizeInput($_GET['start_date'] ?? date('Y-m-01'));
        $endDate = Security::sanitizeInput($_GET['end_date'] ?? date('Y-m-t'));
        $category = Security::sanitizeInput($_GET['category'] ?? '');
        
        $records = $this->expenseModel->getExpenseRecords($startDate, $endDate, $category ?: null);
        $summary = $this->expenseModel->getExpenseSummary($startDate, $endDate);
        $categories = $this->expenseModel->getCategories();
        
        $data = [
            'records' => $records,
            'summary' => $summary,
            'categories' => $categories,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'category' => $category
            ],
            'csrf_token' => Security::generateCsrfToken()
        ];
        
        $this->view('expense/index', $data);
    }
    
    /**
     * Add new expense record (AJAX)
     */
    public function add() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['status' => 'error', 'message' => 'Invalid request method'], 405);
        }
        
        $required = ['category', 'amount', 'date'];
        $data = $this->getPostData($required);
        
        // Optional fields
        $data['description'] = Security::sanitizeInput($_POST['description'] ?? '');
        $data['vendor'] = Security::sanitizeInput($_POST['vendor'] ?? '');
        $data['payment_method'] = Security::sanitizeInput($_POST['payment_method'] ?? 'cash');
        $data['approved_by'] = Security::sanitizeInput($_POST['approved_by'] ?? Auth::username());
        $data['receipt_number'] = Security::sanitizeInput($_POST['receipt_number'] ?? '');
        
        // Validate amount
        if (!Security::validateNumeric($data['amount'], 0.01)) {
            $this->json(['status' => 'error', 'message' => 'Invalid amount']);
        }
        
        // Validate date
        if (!Security::validateDate($data['date'])) {
            $this->json(['status' => 'error', 'message' => 'Invalid date format']);
        }
        
        try {
            $expenseId = $this->expenseModel->createExpense($data);
            if ($expenseId) {
                $this->json(['status' => 'success', 'message' => 'Expense record added successfully', 'expense_id' => $expenseId]);
            } else {
                $this->json(['status' => 'error', 'message' => 'Failed to add expense record']);
            }
        } catch (Exception $e) {
            error_log("Expense creation error: " . $e->getMessage());
            $this->json(['status' => 'error', 'message' => 'An error occurred while adding the expense record']);
        }
    }
    
    /**
     * Update expense record (AJAX)
     */
    public function update() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['status' => 'error', 'message' => 'Invalid request method'], 405);
        }
        
        $expenseId = (int)($_POST['expense_id'] ?? 0);
        if (!$expenseId) {
            $this->json(['status' => 'error', 'message' => 'Invalid expense ID']);
        }
        
        // Validate CSRF token
        if (!Security::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->json(['status' => 'error', 'message' => 'Invalid CSRF token'], 403);
        }
        
        $data = [];
        if (!empty($_POST['category'])) {
            $data['category'] = Security::sanitizeInput($_POST['category']);
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
        if (isset($_POST['vendor'])) {
            $data['vendor'] = Security::sanitizeInput($_POST['vendor']);
        }
        if (!empty($_POST['payment_method'])) {
            $data['payment_method'] = Security::sanitizeInput($_POST['payment_method']);
        }
        if (isset($_POST['receipt_number'])) {
            $data['receipt_number'] = Security::sanitizeInput($_POST['receipt_number']);
        }
        
        try {
            $result = $this->expenseModel->update($expenseId, $data);
            if ($result) {
                $this->json(['status' => 'success', 'message' => 'Expense record updated successfully']);
            } else {
                $this->json(['status' => 'error', 'message' => 'Failed to update expense record']);
            }
        } catch (Exception $e) {
            error_log("Expense update error: " . $e->getMessage());
            $this->json(['status' => 'error', 'message' => 'An error occurred while updating the expense record']);
        }
    }
    
    /**
     * Delete expense record (AJAX)
     */
    public function delete() {
        // Only admins can delete expenses
        $this->requireRole(['admin', 'super_admin']);
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['status' => 'error', 'message' => 'Invalid request method'], 405);
        }
        
        $expenseId = (int)($_POST['expense_id'] ?? 0);
        if (!$expenseId) {
            $this->json(['status' => 'error', 'message' => 'Invalid expense ID']);
        }
        
        // Validate CSRF token
        if (!Security::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->json(['status' => 'error', 'message' => 'Invalid CSRF token'], 403);
        }
        
        try {
            $result = $this->expenseModel->delete($expenseId);
            if ($result) {
                $this->json(['status' => 'success', 'message' => 'Expense record deleted successfully']);
            } else {
                $this->json(['status' => 'error', 'message' => 'Failed to delete expense record']);
            }
        } catch (Exception $e) {
            error_log("Expense deletion error: " . $e->getMessage());
            $this->json(['status' => 'error', 'message' => 'An error occurred while deleting the expense record']);
        }
    }
    
    /**
     * Get expense summary (AJAX)
     */
    public function summary() {
        $startDate = Security::sanitizeInput($_POST['start_date'] ?? '');
        $endDate = Security::sanitizeInput($_POST['end_date'] ?? '');
        
        $summary = $this->expenseModel->getExpenseSummary($startDate ?: null, $endDate ?: null);
        
        $this->json(['status' => 'success', 'summary' => $summary]);
    }
    
    /**
     * Get monthly expense report
     */
    public function monthlyReport() {
        $this->requireRole(['admin', 'super_admin']);
        
        $year = (int)($_GET['year'] ?? date('Y'));
        $month = (int)($_GET['month'] ?? date('m'));
        
        $report = $this->expenseModel->getMonthlyReport($year, $month);
        
        $data = [
            'report' => $report,
            'year' => $year,
            'month' => $month,
            'month_name' => date('F', mktime(0, 0, 0, $month, 1, $year))
        ];
        
        $this->view('expense/monthly_report', $data);
    }
}