<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\Income;
use App\Models\Lock;
use App\Helpers\Validator;
use App\Helpers\Security;
use App\Middlewares\AuthMiddleware;
use App\Middlewares\CSRFMiddleware;

class IncomeController extends Controller {
    private $incomeModel;
    private $lockModel;
    
    public function __construct($params) {
        parent::__construct($params);
        
        $this->incomeModel = new Income();
        $this->lockModel = new Lock();
    }
    
    protected function before() {
        AuthMiddleware::check();
        CSRFMiddleware::validate();
    }
    
    /**
     * List income records
     */
    public function index() {
        $filters = [
            'date_from' => $_GET['date_from'] ?? date('Y-m-01'),
            'date_to' => $_GET['date_to'] ?? date('Y-m-d'),
            'category' => $_GET['category'] ?? '',
            'search' => $_GET['search'] ?? ''
        ];
        
        $incomeRecords = $this->incomeModel->getFilteredIncome($filters);
        $categorySummary = $this->incomeModel->getIncomeByCategory($filters['date_from'], $filters['date_to']);
        
        $this->renderWithLayout('income/index', [
            'title' => 'Income Management',
            'incomeRecords' => $incomeRecords,
            'categorySummary' => $categorySummary,
            'filters' => $filters
        ]);
    }
    
    /**
     * Show create income form
     */
    public function create() {
        $this->renderWithLayout('income/create', [
            'title' => 'Add Income'
        ]);
    }
    
    /**
     * Store new income record
     */
    public function store() {
        // Validate input
        $validator = new Validator($_POST);
        $valid = $validator->validate([
            'date' => 'required|date',
            'category' => 'required',
            'description' => 'required|min:3',
            'amount' => 'required|numeric|min:0',
            'payment_method' => 'required|in:cash,card,upi,bank_transfer'
        ]);
        
        if (!$valid) {
            setFlash('error', $validator->getFirstError());
            $this->redirect(baseUrl('income/create'));
        }
        
        // Check if date is locked
        if ($this->lockModel->isDateLocked($_POST['date'])) {
            setFlash('error', 'Cannot add income for locked date');
            $this->redirect(baseUrl('income/create'));
        }
        
        // Prepare data
        $data = [
            'date' => $_POST['date'],
            'category' => $_POST['category'],
            'description' => Security::sanitize($_POST['description']),
            'amount' => $_POST['amount'],
            'payment_method' => $_POST['payment_method'],
            'reference_no' => Security::sanitize($_POST['reference_no'] ?? ''),
            'notes' => Security::sanitize($_POST['notes'] ?? ''),
            'created_by' => userId(),
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        try {
            $incomeId = $this->incomeModel->create($data);
            setFlash('success', 'Income record added successfully');
            $this->redirect(baseUrl('income'));
        } catch (\Exception $e) {
            setFlash('error', 'Failed to add income: ' . $e->getMessage());
            $this->redirect(baseUrl('income/create'));
        }
    }
    
    /**
     * Show edit form
     */
    public function edit() {
        $id = $this->routeParams['id'] ?? 0;
        $income = $this->incomeModel->findById($id);
        
        if (!$income) {
            setFlash('error', 'Income record not found');
            $this->redirect(baseUrl('income'));
        }
        
        // Check if date is locked
        if ($this->lockModel->isDateLocked($income['date'])) {
            setFlash('error', 'Cannot edit income for locked date');
            $this->redirect(baseUrl('income'));
        }
        
        $this->renderWithLayout('income/edit', [
            'title' => 'Edit Income',
            'income' => $income
        ]);
    }
    
    /**
     * Update income record
     */
    public function update() {
        $id = $this->routeParams['id'] ?? 0;
        
        // Similar validation as store method
        // ... validation code ...
        
        try {
            $this->incomeModel->update($id, $data);
            setFlash('success', 'Income record updated successfully');
            $this->redirect(baseUrl('income'));
        } catch (\Exception $e) {
            setFlash('error', 'Failed to update income: ' . $e->getMessage());
            $this->redirect(baseUrl('income/' . $id . '/edit'));
        }
    }
    
    /**
     * Delete income record
     */
    public function delete() {
        AuthMiddleware::checkRole(['admin', 'super_admin']);
        
        $id = $this->routeParams['id'] ?? 0;
        $income = $this->incomeModel->findById($id);
        
        if (!$income) {
            $this->json(['status' => 'error', 'message' => 'Income record not found'], 404);
        }
        
        // Check if date is locked
        if ($this->lockModel->isDateLocked($income['date'])) {
            $this->json(['status' => 'error', 'message' => 'Cannot delete income for locked date']);
        }
        
        try {
            $this->incomeModel->delete($id);
            $this->json(['status' => 'success', 'message' => 'Income record deleted successfully']);
        } catch (\Exception $e) {
            $this->json(['status' => 'error', 'message' => 'Failed to delete income']);
        }
    }
    
    /**
     * Export income report
     */
    public function export() {
        $filters = [
            'date_from' => $_GET['date_from'] ?? date('Y-m-01'),
            'date_to' => $_GET['date_to'] ?? date('Y-m-d')
        ];
        
        $incomeRecords = $this->incomeModel->getFilteredIncome($filters);
        
        // Generate CSV or PDF based on format parameter
        // ... export logic ...
    }
}