<?php
namespace App\Models;

use App\Core\Model;

class Lock extends Model {
    protected $table = 'locks';
    
    /**
     * Check if a date is locked
     */
    public function isDateLocked($date) {
        $lock = $this->findOne(['locked_date' => $date]);
        return $lock !== null;
    }
    
    /**
     * Lock a date
     */
    public function lockDate($date, $lockedBy) {
        // Check if already locked
        if ($this->isDateLocked($date)) {
            return false;
        }
        
        return $this->create([
            'locked_date' => $date,
            'locked_by' => $lockedBy,
            'locked_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Unlock a date
     */
    public function unlockDate($date) {
        $stmt = $this->query("DELETE FROM {$this->table} WHERE locked_date = :date", [
            'date' => $date
        ]);
        
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Get all locked dates for a month
     */
    public function getLockedDatesForMonth($month, $year) {
        $sql = "SELECT * FROM {$this->table}
                WHERE MONTH(locked_date) = :month
                    AND YEAR(locked_date) = :year
                ORDER BY locked_date";
        
        $stmt = $this->query($sql, [
            'month' => $month,
            'year' => $year
        ]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get lock details
     */
    public function getLockDetails($date) {
        $sql = "SELECT l.*, u.username as locked_by_username
                FROM {$this->table} l
                LEFT JOIN users u ON l.locked_by = u.id
                WHERE l.locked_date = :date";
        
        $stmt = $this->query($sql, ['date' => $date]);
        return $stmt->fetch();
    }
}