<?php

class Attendance extends Model {
    protected $table = 'attendance';
    
    /**
     * Mark attendance for a staff member
     */
    public function markAttendance($data) {
        $attendanceData = [
            'staff_id' => (int)$data['staff_id'],
            'date' => $data['date'] ?? date('Y-m-d'),
            'status' => Security::sanitizeInput($data['status']), // present, absent, late, half_day
            'check_in_time' => $data['check_in_time'] ?? null,
            'check_out_time' => $data['check_out_time'] ?? null,
            'notes' => Security::sanitizeInput($data['notes'] ?? ''),
            'marked_by' => Security::sanitizeInput($data['marked_by'] ?? Auth::username()),
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        // Check if attendance already exists for this staff and date
        $existing = $this->findAttendance($attendanceData['staff_id'], $attendanceData['date']);
        
        if ($existing) {
            unset($attendanceData['created_at']);
            $attendanceData['updated_at'] = date('Y-m-d H:i:s');
            return $this->update($existing['id'], $attendanceData);
        }
        
        return $this->create($attendanceData);
    }
    
    /**
     * Find attendance record for specific staff and date
     */
    public function findAttendance($staffId, $date) {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE staff_id = ? AND date = ?");
        $stmt->execute([$staffId, $date]);
        return $stmt->fetch();
    }
    
    /**
     * Get attendance records with staff information
     */
    public function getAttendanceWithStaff($startDate = null, $endDate = null, $staffId = null) {
        $conditions = [];
        $params = [];
        
        if ($startDate) {
            $conditions[] = "a.date >= ?";
            $params[] = $startDate;
        }
        
        if ($endDate) {
            $conditions[] = "a.date <= ?";
            $params[] = $endDate;
        }
        
        if ($staffId) {
            $conditions[] = "a.staff_id = ?";
            $params[] = $staffId;
        }
        
        $whereClause = !empty($conditions) ? ' WHERE ' . implode(' AND ', $conditions) : '';
        
        $sql = "SELECT a.*, s.name as staff_name, s.position
                FROM {$this->table} a
                JOIN staff s ON a.staff_id = s.id
                $whereClause
                ORDER BY a.date DESC, s.name ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get attendance summary for a staff member
     */
    public function getStaffAttendanceSummary($staffId, $startDate = null, $endDate = null) {
        $conditions = ['staff_id = ?'];
        $params = [$staffId];
        
        if ($startDate) {
            $conditions[] = "date >= ?";
            $params[] = $startDate;
        }
        
        if ($endDate) {
            $conditions[] = "date <= ?";
            $params[] = $endDate;
        }
        
        $whereClause = ' WHERE ' . implode(' AND ', $conditions);
        
        $sql = "SELECT 
                    COUNT(*) as total_days,
                    COUNT(CASE WHEN status = 'present' THEN 1 END) as present_days,
                    COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_days,
                    COUNT(CASE WHEN status = 'late' THEN 1 END) as late_days,
                    COUNT(CASE WHEN status = 'half_day' THEN 1 END) as half_days
                FROM {$this->table}
                $whereClause";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetch();
    }
    
    /**
     * Get monthly attendance report
     */
    public function getMonthlyReport($year = null, $month = null) {
        $year = $year ?? date('Y');
        $month = $month ?? date('m');
        
        $sql = "SELECT 
                    a.staff_id,
                    s.name as staff_name,
                    s.position,
                    COUNT(*) as total_days,
                    COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_days,
                    COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_days,
                    COUNT(CASE WHEN a.status = 'late' THEN 1 END) as late_days,
                    COUNT(CASE WHEN a.status = 'half_day' THEN 1 END) as half_days,
                    ROUND((COUNT(CASE WHEN a.status = 'present' THEN 1 END) * 100.0 / COUNT(*)), 2) as attendance_percentage
                FROM {$this->table} a
                JOIN staff s ON a.staff_id = s.id
                WHERE YEAR(a.date) = ? AND MONTH(a.date) = ?
                GROUP BY a.staff_id, s.name, s.position
                ORDER BY s.name";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$year, $month]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get daily attendance for all staff
     */
    public function getDailyAttendance($date = null) {
        $date = $date ?? date('Y-m-d');
        
        $sql = "SELECT s.id as staff_id, s.name, s.position,
                       COALESCE(a.status, 'not_marked') as status,
                       a.check_in_time, a.check_out_time, a.notes
                FROM staff s
                LEFT JOIN {$this->table} a ON s.id = a.staff_id AND a.date = ?
                WHERE s.status = 'active'
                ORDER BY s.name";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$date]);
        
        return $stmt->fetchAll();
    }
}