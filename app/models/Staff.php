<?php

class Staff extends Model {
    protected $table = 'staff';
    
    /**
     * Create new staff member
     */
    public function createStaff($data) {
        $staffData = [
            'name' => Security::sanitizeInput($data['name']),
            'contact_number' => Security::sanitizeInput($data['contact_number']),
            'position' => Security::sanitizeInput($data['position']),
            'salary' => (float)($data['salary'] ?? 0),
            'hire_date' => $data['hire_date'] ?? date('Y-m-d'),
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        return $this->create($staffData);
    }
    
    /**
     * Get active staff members
     */
    public function getActiveStaff() {
        return $this->findAll(['status' => 'active'], 'name ASC');
    }
    
    /**
     * Get staff with attendance data
     */
    public function getStaffWithAttendance($date = null) {
        $date = $date ?? date('Y-m-d');
        
        $sql = "SELECT s.*, 
                       a.status as attendance_status,
                       a.check_in_time,
                       a.check_out_time,
                       a.notes as attendance_notes
                FROM {$this->table} s 
                LEFT JOIN attendance a ON s.id = a.staff_id AND a.date = ?
                WHERE s.status = 'active'
                ORDER BY s.name ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$date]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Update staff information
     */
    public function updateStaff($id, $data) {
        $updateData = [];
        
        if (isset($data['name'])) {
            $updateData['name'] = Security::sanitizeInput($data['name']);
        }
        
        if (isset($data['contact_number'])) {
            $updateData['contact_number'] = Security::sanitizeInput($data['contact_number']);
        }
        
        if (isset($data['position'])) {
            $updateData['position'] = Security::sanitizeInput($data['position']);
        }
        
        if (isset($data['salary'])) {
            $updateData['salary'] = (float)$data['salary'];
        }
        
        if (isset($data['status'])) {
            $updateData['status'] = $data['status'];
        }
        
        $updateData['updated_at'] = date('Y-m-d H:i:s');
        
        return $this->update($id, $updateData);
    }
    
    /**
     * Get staff statistics
     */
    public function getStatistics() {
        $sql = "SELECT 
                    COUNT(*) as total_staff,
                    COUNT(CASE WHEN status = 'active' THEN 1 END) as active_staff,
                    COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive_staff,
                    AVG(salary) as average_salary,
                    SUM(salary) as total_payroll
                FROM {$this->table}";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetch();
    }
}