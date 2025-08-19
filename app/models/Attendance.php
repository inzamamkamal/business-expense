<?php
namespace App\Models;

use App\Core\Model;

class Attendance extends Model {
    protected $table = 'attendance';
    
    /**
     * Get attendance records for a specific date
     */
    public function getAttendanceByDate($date) {
        $sql = "SELECT a.*, s.name as staff_name, s.department 
                FROM {$this->table} a
                JOIN staff s ON a.staff_id = s.id
                WHERE a.date = :date
                ORDER BY s.department, s.name";
        
        $stmt = $this->query($sql, ['date' => $date]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get attendance for a staff member
     */
    public function getStaffAttendance($staffId, $month = null, $year = null) {
        if (!$month) $month = date('n');
        if (!$year) $year = date('Y');
        
        $sql = "SELECT * FROM {$this->table}
                WHERE staff_id = :staff_id
                    AND MONTH(date) = :month
                    AND YEAR(date) = :year
                ORDER BY date";
        
        $stmt = $this->query($sql, [
            'staff_id' => $staffId,
            'month' => $month,
            'year' => $year
        ]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Mark attendance
     */
    public function markAttendance($staffId, $date, $status, $checkIn = null, $checkOut = null) {
        // Check if attendance already exists
        $existing = $this->findOne([
            'staff_id' => $staffId,
            'date' => $date
        ]);
        
        if ($existing) {
            // Update existing record
            return $this->update($existing['id'], [
                'status' => $status,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        } else {
            // Create new record
            return $this->create([
                'staff_id' => $staffId,
                'date' => $date,
                'status' => $status,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
    }
    
    /**
     * Get attendance summary for a month
     */
    public function getMonthlySummary($month = null, $year = null) {
        if (!$month) $month = date('n');
        if (!$year) $year = date('Y');
        
        $sql = "SELECT 
                    s.id,
                    s.name,
                    s.department,
                    COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_days,
                    COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_days,
                    COUNT(CASE WHEN a.status = 'leave' THEN 1 END) as leave_days,
                    COUNT(CASE WHEN a.status = 'holiday' THEN 1 END) as holiday_days
                FROM staff s
                LEFT JOIN {$this->table} a ON s.id = a.staff_id
                    AND MONTH(a.date) = :month
                    AND YEAR(a.date) = :year
                WHERE s.status = 'active'
                GROUP BY s.id
                ORDER BY s.department, s.name";
        
        $stmt = $this->query($sql, ['month' => $month, 'year' => $year]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get late arrivals
     */
    public function getLateArrivals($date, $cutoffTime = '09:30:00') {
        $sql = "SELECT a.*, s.name as staff_name, s.department
                FROM {$this->table} a
                JOIN staff s ON a.staff_id = s.id
                WHERE a.date = :date
                    AND a.status = 'present'
                    AND a.check_in > :cutoff_time
                ORDER BY a.check_in";
        
        $stmt = $this->query($sql, [
            'date' => $date,
            'cutoff_time' => $cutoffTime
        ]);
        
        return $stmt->fetchAll();
    }
}