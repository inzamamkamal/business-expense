<?php

class Expense extends Model {
    protected $table = 'expenses';
    
    /**
     * Create new expense record
     */
    public function createExpense($data) {
        $expenseData = [
            'category' => Security::sanitizeInput($data['category']),
            'amount' => (float)$data['amount'],
            'date' => $data['date'],
            'description' => Security::sanitizeInput($data['description'] ?? ''),
            'vendor' => Security::sanitizeInput($data['vendor'] ?? ''),
            'payment_method' => Security::sanitizeInput($data['payment_method'] ?? 'cash'),
            'approved_by' => Security::sanitizeInput($data['approved_by'] ?? Auth::username()),
            'receipt_number' => Security::sanitizeInput($data['receipt_number'] ?? ''),
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        return $this->create($expenseData);
    }
    
    /**
     * Get expense records with filters
     */
    public function getExpenseRecords($startDate = null, $endDate = null, $category = null) {
        $conditions = [];
        $params = [];
        
        if ($startDate) {
            $conditions[] = "date >= ?";
            $params[] = $startDate;
        }
        
        if ($endDate) {
            $conditions[] = "date <= ?";
            $params[] = $endDate;
        }
        
        if ($category) {
            $conditions[] = "category = ?";
            $params[] = $category;
        }
        
        $whereClause = !empty($conditions) ? ' WHERE ' . implode(' AND ', $conditions) : '';
        
        $sql = "SELECT * FROM {$this->table} $whereClause ORDER BY date DESC, created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get expense summary by category
     */
    public function getExpenseSummary($startDate = null, $endDate = null) {
        $conditions = [];
        $params = [];
        
        if ($startDate) {
            $conditions[] = "date >= ?";
            $params[] = $startDate;
        }
        
        if ($endDate) {
            $conditions[] = "date <= ?";
            $params[] = $endDate;
        }
        
        $whereClause = !empty($conditions) ? ' WHERE ' . implode(' AND ', $conditions) : '';
        
        $sql = "SELECT 
                    category,
                    SUM(amount) as total_amount,
                    COUNT(*) as total_records,
                    AVG(amount) as average_amount
                FROM {$this->table} $whereClause 
                GROUP BY category
                ORDER BY total_amount DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get monthly expense report
     */
    public function getMonthlyReport($year = null, $month = null) {
        $year = $year ?? date('Y');
        $month = $month ?? date('m');
        
        $sql = "SELECT 
                    DATE(date) as expense_date,
                    SUM(amount) as daily_total,
                    COUNT(*) as daily_count,
                    category
                FROM {$this->table} 
                WHERE YEAR(date) = ? AND MONTH(date) = ?
                GROUP BY DATE(date), category
                ORDER BY expense_date DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$year, $month]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get expense categories
     */
    public function getCategories() {
        $sql = "SELECT DISTINCT category FROM {$this->table} ORDER BY category";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return array_column($stmt->fetchAll(), 'category');
    }
}