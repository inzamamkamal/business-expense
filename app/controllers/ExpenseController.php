<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\Expense;
use App\Models\Lock;
use App\Helpers\Validator;
use App\Helpers\Security;
use App\Middlewares\AuthMiddleware;
use App\Middlewares\CSRFMiddleware;

class ExpenseController extends Controller {
    private $expenseModel;
    private $lockModel;
    
    public function __construct($params) {
        parent::__construct($params);
        
        $this->expenseModel = new Expense();
        $this->lockModel = new Lock();
    }
    
    protected function before() {
        AuthMiddleware::check();
        CSRFMiddleware::validate();
    }
    
    /**
     * List expense records
     */
    public function index() {
        $filters = [
            'date_from' => $_GET['date_from'] ?? date('Y-m-01'),
            'date_to' => $_GET['date_to'] ?? date('Y-m-d'),
            'category' => $_GET['category'] ?? '',
            'payment_method' => $_GET['payment_method'] ?? '',
            'search' => $_GET['search'] ?? ''
        ];
        
        $expenses = $this->expenseModel->getFilteredExpenses($filters);
        $categorySummary = $this->expenseModel->getExpenseByCategory($filters['date_from'], $filters['date_to']);
        $paymentSummary = $this->expenseModel->getExpenseByPaymentMethod($filters['date_from'], $filters['date_to']);
        
        $this->renderWithLayout('expense/index', [
            'title' => 'Expense Management',
            'expenses' => $expenses,
            'categorySummary' => $categorySummary,
            'paymentSummary' => $paymentSummary,
            'filters' => $filters
        ]);
    }
    
    /**
     * Show create expense form
     */
    public function create() {
        $this->renderWithLayout('expense/create', [
            'title' => 'Add Expense'
        ]);
    }
    
    /**
     * Store new expense record
     */
    public function store() {
        // Validate input
        $validator = new Validator($_POST);
        $valid = $validator->validate([
            'date' => 'required|date',
            'category' => 'required',
            'description' => 'required|min:3',
            'amount' => 'required|numeric|min:0',
            'payment_method' => 'required|in:cash,card,upi,bank_transfer',
            'vendor' => 'min:3|max:100'
        ]);
        
        if (!$valid) {
            setFlash('error', $validator->getFirstError());
            $this->redirect(baseUrl('expenses/create'));
        }
        
        // Check if date is locked
        if ($this->lockModel->isDateLocked($_POST['date'])) {
            setFlash('error', 'Cannot add expense for locked date');
            $this->redirect(baseUrl('expenses/create'));
        }
        
        // Prepare data
        $data = [
            'date' => $_POST['date'],
            'category' => $_POST['category'],
            'description' => Security::sanitize($_POST['description']),
            'amount' => $_POST['amount'],
            'payment_method' => $_POST['payment_method'],
            'vendor' => Security::sanitize($_POST['vendor'] ?? ''),
            'invoice_no' => Security::sanitize($_POST['invoice_no'] ?? ''),
            'notes' => Security::sanitize($_POST['notes'] ?? ''),
            'created_by' => userId(),
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        try {
            $expenseId = $this->expenseModel->create($data);
            setFlash('success', 'Expense record added successfully');
            $this->redirect(baseUrl('expenses'));
        } catch (\Exception $e) {
            setFlash('error', 'Failed to add expense: ' . $e->getMessage());
            $this->redirect(baseUrl('expenses/create'));
        }
    }
    
    /**
     * Show edit form
     */
    public function edit() {
        $id = $this->routeParams['id'] ?? 0;
        $expense = $this->expenseModel->findById($id);
        
        if (!$expense) {
            setFlash('error', 'Expense record not found');
            $this->redirect(baseUrl('expenses'));
        }
        
        // Check if date is locked
        if ($this->lockModel->isDateLocked($expense['date'])) {
            setFlash('error', 'Cannot edit expense for locked date');
            $this->redirect(baseUrl('expenses'));
        }
        
        $this->renderWithLayout('expense/edit', [
            'title' => 'Edit Expense',
            'expense' => $expense
        ]);
    }
    
    /**
     * Update expense record
     */
    public function update() {
        $id = $this->routeParams['id'] ?? 0;
        
        // Similar validation as store method
        // ... validation code ...
        
        try {
            $this->expenseModel->update($id, $data);
            setFlash('success', 'Expense record updated successfully');
            $this->redirect(baseUrl('expenses'));
        } catch (\Exception $e) {
            setFlash('error', 'Failed to update expense: ' . $e->getMessage());
            $this->redirect(baseUrl('expenses/' . $id . '/edit'));
        }
    }
    
    /**
     * Delete expense record
     */
    public function delete() {
        AuthMiddleware::checkRole(['admin', 'super_admin']);
        
        $id = $this->routeParams['id'] ?? 0;
        $expense = $this->expenseModel->findById($id);
        
        if (!$expense) {
            $this->json(['status' => 'error', 'message' => 'Expense record not found'], 404);
        }
        
        // Check if date is locked
        if ($this->lockModel->isDateLocked($expense['date'])) {
            $this->json(['status' => 'error', 'message' => 'Cannot delete expense for locked date']);
        }
        
        try {
            $this->expenseModel->delete($id);
            $this->json(['status' => 'success', 'message' => 'Expense record deleted successfully']);
        } catch (\Exception $e) {
            $this->json(['status' => 'error', 'message' => 'Failed to delete expense']);
        }
    }
}