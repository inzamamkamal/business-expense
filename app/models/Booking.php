<?php
namespace App\Models;

use App\Core\Model;

class Booking extends Model {
    protected $table = 'bookings';
    
    /**
     * Get bookings with filters
     */
    public function getFilteredBookings($filters = []) {
        $sql = "SELECT b.*, s.name as staff_name 
                FROM {$this->table} b 
                LEFT JOIN staff s ON b.staff_id = s.id 
                WHERE 1=1";
        $params = [];
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND b.date >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND b.date <= :date_to";
            $params['date_to'] = $filters['date_to'];
        }
        
        if (!empty($filters['staff_id'])) {
            $sql .= " AND b.staff_id = :staff_id";
            $params['staff_id'] = $filters['staff_id'];
        }
        
        if (!empty($filters['status'])) {
            $sql .= " AND b.status = :status";
            $params['status'] = $filters['status'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (b.guest_name LIKE :search OR b.reference_no LIKE :search2)";
            $params['search'] = '%' . $filters['search'] . '%';
            $params['search2'] = '%' . $filters['search'] . '%';
        }
        
        $sql .= " ORDER BY b.date DESC, b.created_at DESC";
        
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get booking with staff details
     */
    public function getBookingWithStaff($id) {
        $sql = "SELECT b.*, s.name as staff_name, s.commission_rate 
                FROM {$this->table} b 
                LEFT JOIN staff s ON b.staff_id = s.id 
                WHERE b.id = :id";
        
        $stmt = $this->query($sql, ['id' => $id]);
        return $stmt->fetch();
    }
    
    /**
     * Get daily bookings summary
     */
    public function getDailySummary($date) {
        $sql = "SELECT 
                    COUNT(*) as total_bookings,
                    SUM(total_amount) as total_revenue,
                    SUM(CASE WHEN payment_status = 'paid' THEN total_amount ELSE 0 END) as paid_amount,
                    SUM(CASE WHEN payment_status = 'pending' THEN total_amount ELSE 0 END) as pending_amount
                FROM {$this->table} 
                WHERE date = :date";
        
        $stmt = $this->query($sql, ['date' => $date]);
        return $stmt->fetch();
    }
    
    /**
     * Get bookings by status
     */
    public function getBookingsByStatus($status, $limit = null) {
        $sql = "SELECT b.*, s.name as staff_name 
                FROM {$this->table} b 
                LEFT JOIN staff s ON b.staff_id = s.id 
                WHERE b.status = :status 
                ORDER BY b.date DESC";
        
        if ($limit) {
            $sql .= " LIMIT " . (int)$limit;
        }
        
        $stmt = $this->query($sql, ['status' => $status]);
        return $stmt->fetchAll();
    }
    
    /**
     * Calculate commission for booking
     */
    public function calculateCommission($bookingId) {
        $booking = $this->getBookingWithStaff($bookingId);
        
        if ($booking && $booking['commission_rate']) {
            return $booking['total_amount'] * ($booking['commission_rate'] / 100);
        }
        
        return 0;
    }
    
    /**
     * Update payment status
     */
    public function updatePaymentStatus($id, $status) {
        return $this->update($id, [
            'payment_status' => $status,
            'payment_date' => date('Y-m-d H:i:s')
        ]);
    }
}