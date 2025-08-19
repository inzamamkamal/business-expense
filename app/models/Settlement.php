<?php

class Settlement extends Model {
    protected $table = 'settlements';
    
    /**
     * Create new settlement record
     */
    public function createSettlement($data) {
        $settlementData = [
            'staff_id' => (int)$data['staff_id'],
            'settlement_date' => $data['settlement_date'],
            'amount' => (float)$data['amount'],
            'type' => Security::sanitizeInput($data['type']), // salary, bonus, advance, deduction
            'description' => Security::sanitizeInput($data['description'] ?? ''),
            'payment_method' => Security::sanitizeInput($data['payment_method'] ?? 'cash'),
            'processed_by' => Security::sanitizeInput($data['processed_by'] ?? Auth::username()),
            'status' => 'completed',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        return $this->create($settlementData);
    }
    
    /**
     * Get settlements with staff information
     */
    public function getSettlementsWithStaff($startDate = null, $endDate = null, $staffId = null) {
        $conditions = [];
        $params = [];
        
        if ($startDate) {
            $conditions[] = "s.settlement_date >= ?";
            $params[] = $startDate;
        }
        
        if ($endDate) {
            $conditions[] = "s.settlement_date <= ?";
            $params[] = $endDate;
        }
        
        if ($staffId) {
            $conditions[] = "s.staff_id = ?";
            $params[] = $staffId;
        }
        
        $whereClause = !empty($conditions) ? ' WHERE ' . implode(' AND ', $conditions) : '';
        
        $sql = "SELECT s.*, st.name as staff_name, st.position
                FROM {$this->table} s
                JOIN staff st ON s.staff_id = st.id
                $whereClause
                ORDER BY s.settlement_date DESC, s.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get settlement summary for a staff member
     */
    public function getStaffSettlementSummary($staffId, $startDate = null, $endDate = null) {
        $conditions = ['staff_id = ?'];
        $params = [$staffId];
        
        if ($startDate) {
            $conditions[] = "settlement_date >= ?";
            $params[] = $startDate;
        }
        
        if ($endDate) {
            $conditions[] = "settlement_date <= ?";
            $params[] = $endDate;
        }
        
        $whereClause = ' WHERE ' . implode(' AND ', $conditions);
        
        $sql = "SELECT 
                    type,
                    SUM(amount) as total_amount,
                    COUNT(*) as total_records
                FROM {$this->table}
                $whereClause
                GROUP BY type
                ORDER BY type";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get monthly settlement report
     */
    public function getMonthlyReport($year = null, $month = null) {
        $year = $year ?? date('Y');
        $month = $month ?? date('m');
        
        $sql = "SELECT 
                    s.type,
                    s.staff_id,
                    st.name as staff_name,
                    SUM(s.amount) as total_amount,
                    COUNT(*) as total_records
                FROM {$this->table} s
                JOIN staff st ON s.staff_id = st.id
                WHERE YEAR(s.settlement_date) = ? AND MONTH(s.settlement_date) = ?
                GROUP BY s.type, s.staff_id, st.name
                ORDER BY st.name, s.type";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$year, $month]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Calculate staff balance (total received - total deductions)
     */
    public function getStaffBalance($staffId, $startDate = null, $endDate = null) {
        $conditions = ['staff_id = ?'];
        $params = [$staffId];
        
        if ($startDate) {
            $conditions[] = "settlement_date >= ?";
            $params[] = $startDate;
        }
        
        if ($endDate) {
            $conditions[] = "settlement_date <= ?";
            $params[] = $endDate;
        }
        
        $whereClause = ' WHERE ' . implode(' AND ', $conditions);
        
        $sql = "SELECT 
                    SUM(CASE WHEN type IN ('salary', 'bonus') THEN amount ELSE 0 END) as total_received,
                    SUM(CASE WHEN type IN ('advance', 'deduction') THEN amount ELSE 0 END) as total_deductions,
                    (SUM(CASE WHEN type IN ('salary', 'bonus') THEN amount ELSE 0 END) - 
                     SUM(CASE WHEN type IN ('advance', 'deduction') THEN amount ELSE 0 END)) as balance
                FROM {$this->table}
                $whereClause";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetch();
    }
}