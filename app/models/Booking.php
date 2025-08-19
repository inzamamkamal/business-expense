<?php

class Booking extends Model {
    protected $table = 'bookings';
    
    /**
     * Create new booking with validation
     */
    public function createBooking($data) {
        // Generate booking ID
        $data['booking_id'] = $this->generateBookingId();
        
        // Sanitize data
        $data['customer_name'] = Security::sanitizeInput($data['customerName']);
        $data['contact_number'] = Security::sanitizeInput($data['contactNumber']);
        $data['booking_date'] = $data['bookingDate'];
        $data['booking_time'] = $data['bookingTime'];
        $data['total_person'] = (int)$data['totalPerson'];
        $data['advance_paid'] = (float)$data['advancePaid'];
        $data['taken_by'] = Security::sanitizeInput($data['takenBy']);
        $data['payment_method'] = Security::sanitizeInput($data['paymentMethod']);
        $data['event_type'] = Security::sanitizeInput($data['eventType']);
        $data['is_dj'] = $data['ISDJ'] === 'yes' ? 1 : 0;
        $data['booking_type'] = Security::sanitizeInput($data['bookingType']);
        $data['special_requests'] = Security::sanitizeInput($data['specialRequests'] ?? '');
        $data['status'] = 'active';
        $data['created_at'] = date('Y-m-d H:i:s');
        
        // Remove original keys
        unset($data['customerName'], $data['contactNumber'], $data['bookingDate'], 
              $data['bookingTime'], $data['totalPerson'], $data['advancePaid'], 
              $data['takenBy'], $data['paymentMethod'], $data['eventType'], 
              $data['ISDJ'], $data['bookingType'], $data['specialRequests']);
        
        return $this->create($data);
    }
    
    /**
     * Get bookings with pagination and filters
     */
    public function getBookings($page = 1, $limit = 20, $filters = []) {
        $offset = ($page - 1) * $limit;
        $conditions = [];
        $params = [];
        
        // Apply filters
        if (!empty($filters['date'])) {
            $conditions[] = "booking_date = ?";
            $params[] = $filters['date'];
        }
        
        if (!empty($filters['status'])) {
            $conditions[] = "status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['search'])) {
            $conditions[] = "(customer_name LIKE ? OR contact_number LIKE ? OR booking_id LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $whereClause = !empty($conditions) ? ' WHERE ' . implode(' AND ', $conditions) : '';
        
        $sql = "SELECT * FROM {$this->table} $whereClause ORDER BY booking_date DESC, booking_time DESC LIMIT $limit OFFSET $offset";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get booking by booking ID
     */
    public function findByBookingId($bookingId) {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE booking_id = ?");
        $stmt->execute([$bookingId]);
        return $stmt->fetch();
    }
    
    /**
     * Complete booking
     */
    public function completeBooking($id, $finalAmount = null, $notes = null) {
        $data = [
            'status' => 'completed',
            'completed_at' => date('Y-m-d H:i:s')
        ];
        
        if ($finalAmount !== null) {
            $data['final_amount'] = (float)$finalAmount;
        }
        
        if ($notes !== null) {
            $data['completion_notes'] = Security::sanitizeInput($notes);
        }
        
        return $this->update($id, $data);
    }
    
    /**
     * Generate unique booking ID
     */
    private function generateBookingId() {
        do {
            $bookingId = 'BK' . date('Ymd') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM {$this->table} WHERE booking_id = ?");
            $stmt->execute([$bookingId]);
        } while ($stmt->fetchColumn() > 0);
        
        return $bookingId;
    }
    
    /**
     * Get booking statistics
     */
    public function getStatistics($startDate = null, $endDate = null) {
        $conditions = [];
        $params = [];
        
        if ($startDate) {
            $conditions[] = "booking_date >= ?";
            $params[] = $startDate;
        }
        
        if ($endDate) {
            $conditions[] = "booking_date <= ?";
            $params[] = $endDate;
        }
        
        $whereClause = !empty($conditions) ? ' WHERE ' . implode(' AND ', $conditions) : '';
        
        $sql = "SELECT 
                    COUNT(*) as total_bookings,
                    SUM(advance_paid) as total_advance,
                    SUM(CASE WHEN status = 'completed' THEN final_amount ELSE advance_paid END) as total_revenue,
                    COUNT(CASE WHEN status = 'active' THEN 1 END) as active_bookings,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_bookings
                FROM {$this->table} $whereClause";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetch();
    }
}