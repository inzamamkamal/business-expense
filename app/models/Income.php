<?php
namespace App\Models;

use App\Core\Model;

class Income extends Model {
    protected $table = 'income';
    
    /**
     * Get income records with filters
     */
    public function getFilteredIncome($filters = []) {
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
        
        if (!empty($filters['search'])) {
            $sql .= " AND (description LIKE :search OR reference_no LIKE :search2)";
            $params['search'] = '%' . $filters['search'] . '%';
            $params['search2'] = '%' . $filters['search'] . '%';
        }
        
        $sql .= " ORDER BY date DESC, created_at DESC";
        
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get income summary by category
     */
    public function getIncomeByCategory($dateFrom = null, $dateTo = null) {
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
     * Get daily income summary
     */
    public function getDailySummary($date) {
        $sql = "SELECT 
                    COUNT(*) as total_entries,
                    SUM(amount) as total_income,
                    MIN(amount) as min_amount,
                    MAX(amount) as max_amount
                FROM {$this->table}
                WHERE date = :date";
        
        $stmt = $this->query($sql, ['date' => $date]);
        return $stmt->fetch();
    }
    
    /**
     * Get monthly income trend
     */
    public function getMonthlyTrend($year) {
        $sql = "SELECT 
                    MONTH(date) as month,
                    COUNT(*) as count,
                    SUM(amount) as total
                FROM {$this->table}
                WHERE YEAR(date) = :year
                GROUP BY MONTH(date)
                ORDER BY month";
        
        $stmt = $this->query($sql, ['year' => $year]);
        return $stmt->fetchAll();
    }
}