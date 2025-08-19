<?php
namespace App\Models;

use App\Core\Model;

class Staff extends Model {
    protected $table = 'staff';
    
    /**
     * Get active staff members
     */
    public function getActiveStaff() {
        return $this->findAll(['status' => 'active'], 'name ASC');
    }
    
    /**
     * Get staff with their current month statistics
     */
    public function getStaffWithStats($month = null, $year = null) {
        if (!$month) $month = date('n');
        if (!$year) $year = date('Y');
        
        $sql = "SELECT 
                    s.*,
                    COUNT(b.id) as total_bookings,
                    SUM(b.total_amount) as total_revenue,
                    SUM(b.commission_amount) as total_commission
                FROM {$this->table} s
                LEFT JOIN bookings b ON s.id = b.staff_id 
                    AND MONTH(b.date) = :month 
                    AND YEAR(b.date) = :year
                WHERE s.status = 'active'
                GROUP BY s.id
                ORDER BY s.name";
        
        $stmt = $this->query($sql, ['month' => $month, 'year' => $year]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get staff performance report
     */
    public function getPerformanceReport($staffId, $dateFrom, $dateTo) {
        $sql = "SELECT 
                    COUNT(*) as total_bookings,
                    SUM(total_amount) as total_revenue,
                    SUM(commission_amount) as total_commission,
                    AVG(total_amount) as avg_booking_value,
                    MIN(date) as first_booking,
                    MAX(date) as last_booking
                FROM bookings
                WHERE staff_id = :staff_id
                    AND date BETWEEN :date_from AND :date_to";
        
        $stmt = $this->query($sql, [
            'staff_id' => $staffId,
            'date_from' => $dateFrom,
            'date_to' => $dateTo
        ]);
        
        return $stmt->fetch();
    }
    
    /**
     * Update staff commission rate
     */
    public function updateCommissionRate($id, $rate) {
        return $this->update($id, ['commission_rate' => $rate]);
    }
    
    /**
     * Get staff by department
     */
    public function getStaffByDepartment($department) {
        return $this->findAll([
            'department' => $department,
            'status' => 'active'
        ], 'name ASC');
    }
}