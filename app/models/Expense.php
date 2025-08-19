<?php
namespace App\Models;

use App\Core\Model;

class Expense extends Model {
    protected $table = 'expenses';
    
    /**
     * Get expense records with filters
     */
    public function getFilteredExpenses($filters = []) {
        $sql = "SELECT * FROM {$this->table} WHERE 1=1";
        $params = [];
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND date >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND date <= :date_to";
            $params['date_to'] = $filters['date_to'];
        }
        
        if (!empty($filters['category'])) {
            $sql .= " AND category = :category";
            $params['category'] = $filters['category'];
        }
        
        if (!empty($filters['payment_method'])) {
            $sql .= " AND payment_method = :payment_method";
            $params['payment_method'] = $filters['payment_method'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (description LIKE :search OR vendor LIKE :search2)";
            $params['search'] = '%' . $filters['search'] . '%';
            $params['search2'] = '%' . $filters['search'] . '%';
        }
        
        $sql .= " ORDER BY date DESC, created_at DESC";
        
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get expense summary by category
     */
    public function getExpenseByCategory($dateFrom = null, $dateTo = null) {
        $sql = "SELECT 
                    category,
                    COUNT(*) as count,
                    SUM(amount) as total_amount
                FROM {$this->table}
                WHERE 1=1";
        $params = [];
        
        if ($dateFrom) {
            $sql .= " AND date >= :date_from";
            $params['date_from'] = $dateFrom;
        }
        
        if ($dateTo) {
            $sql .= " AND date <= :date_to";
            $params['date_to'] = $dateTo;
        }
        
        $sql .= " GROUP BY category ORDER BY total_amount DESC";
        
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get daily expense summary
     */
    public function getDailySummary($date) {
        $sql = "SELECT 
                    COUNT(*) as total_entries,
                    SUM(amount) as total_expense,
                    MIN(amount) as min_amount,
                    MAX(amount) as max_amount
                FROM {$this->table}
                WHERE date = :date";
        
        $stmt = $this->query($sql, ['date' => $date]);
        return $stmt->fetch();
    }
    
    /**
     * Get expenses by payment method
     */
    public function getExpenseByPaymentMethod($dateFrom = null, $dateTo = null) {
        $sql = "SELECT 
                    payment_method,
                    COUNT(*) as count,
                    SUM(amount) as total_amount
                FROM {$this->table}
                WHERE 1=1";
        $params = [];
        
        if ($dateFrom) {
            $sql .= " AND date >= :date_from";
            $params['date_from'] = $dateFrom;
        }
        
        if ($dateTo) {
            $sql .= " AND date <= :date_to";
            $params['date_to'] = $dateTo;
        }
        
        $sql .= " GROUP BY payment_method ORDER BY total_amount DESC";
        
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get top vendors by expense
     */
    public function getTopVendors($limit = 10, $dateFrom = null, $dateTo = null) {
        $sql = "SELECT 
                    vendor,
                    COUNT(*) as transaction_count,
                    SUM(amount) as total_amount
                FROM {$this->table}
                WHERE vendor IS NOT NULL AND vendor != ''";
        $params = [];
        
        if ($dateFrom) {
            $sql .= " AND date >= :date_from";
            $params['date_from'] = $dateFrom;
        }
        
        if ($dateTo) {
            $sql .= " AND date <= :date_to";
            $params['date_to'] = $dateTo;
        }
        
        $sql .= " GROUP BY vendor ORDER BY total_amount DESC LIMIT " . (int)$limit;
        
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
}