<?php

class Income extends Model {
    protected $table = 'income';
    
    /**
     * Create new income record
     */
    public function createIncome($data) {
        $incomeData = [
            'source' => Security::sanitizeInput($data['source']),
            'amount' => (float)$data['amount'],
            'date' => $data['date'],
            'description' => Security::sanitizeInput($data['description'] ?? ''),
            'category' => Security::sanitizeInput($data['category'] ?? 'general'),
            'payment_method' => Security::sanitizeInput($data['payment_method'] ?? 'cash'),
            'received_by' => Security::sanitizeInput($data['received_by'] ?? Auth::username()),
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        return $this->create($incomeData);
    }
    
    /**
     * Get income records with filters
     */
    public function getIncomeRecords($startDate = null, $endDate = null, $category = null) {
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
     * Get income summary
     */
    public function getIncomeSummary($startDate = null, $endDate = null) {
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
                    SUM(amount) as total_income,
                    COUNT(*) as total_records,
                    AVG(amount) as average_income,
                    category,
                    SUM(amount) as category_total
                FROM {$this->table} $whereClause 
                GROUP BY category
                ORDER BY category_total DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get monthly income report
     */
    public function getMonthlyReport($year = null, $month = null) {
        $year = $year ?? date('Y');
        $month = $month ?? date('m');
        
        $sql = "SELECT 
                    DATE(date) as income_date,
                    SUM(amount) as daily_total,
                    COUNT(*) as daily_count,
                    category
                FROM {$this->table} 
                WHERE YEAR(date) = ? AND MONTH(date) = ?
                GROUP BY DATE(date), category
                ORDER BY income_date DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$year, $month]);
        
        return $stmt->fetchAll();
    }
}