-- BTS DISC 2.0 Database Schema
-- Professional Business Management System

-- Users table for authentication
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','super_admin','user') DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Staff table
CREATE TABLE IF NOT EXISTS `staff` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `contact_number` varchar(15) NOT NULL,
  `position` varchar(50) NOT NULL,
  `salary` decimal(10,2) DEFAULT '0.00',
  `hire_date` date NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bookings table
CREATE TABLE IF NOT EXISTS `bookings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_id` varchar(20) NOT NULL,
  `customer_name` varchar(100) NOT NULL,
  `contact_number` varchar(15) NOT NULL,
  `booking_date` date NOT NULL,
  `booking_time` time NOT NULL,
  `total_person` int(11) NOT NULL,
  `advance_paid` decimal(10,2) NOT NULL,
  `final_amount` decimal(10,2) DEFAULT NULL,
  `event_type` varchar(50) NOT NULL,
  `booking_type` varchar(20) NOT NULL,
  `is_dj` tinyint(1) DEFAULT '0',
  `payment_method` varchar(20) NOT NULL,
  `taken_by` varchar(50) NOT NULL,
  `special_requests` text,
  `status` enum('active','completed','cancelled') DEFAULT 'active',
  `completion_notes` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `booking_id` (`booking_id`),
  KEY `idx_booking_date` (`booking_date`),
  KEY `idx_status` (`status`),
  KEY `idx_customer_name` (`customer_name`),
  KEY `idx_contact_number` (`contact_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Attendance table
CREATE TABLE IF NOT EXISTS `attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `status` enum('present','absent','late','half_day') NOT NULL,
  `check_in_time` time DEFAULT NULL,
  `check_out_time` time DEFAULT NULL,
  `notes` text,
  `marked_by` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_staff_date` (`staff_id`,`date`),
  KEY `idx_date` (`date`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_attendance_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Income table
CREATE TABLE IF NOT EXISTS `income` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `source` varchar(100) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `date` date NOT NULL,
  `description` text,
  `category` varchar(50) DEFAULT 'general',
  `payment_method` varchar(20) DEFAULT 'cash',
  `received_by` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_date` (`date`),
  KEY `idx_category` (`category`),
  KEY `idx_amount` (`amount`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Expenses table
CREATE TABLE IF NOT EXISTS `expenses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category` varchar(50) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `date` date NOT NULL,
  `description` text,
  `vendor` varchar(100) DEFAULT NULL,
  `payment_method` varchar(20) DEFAULT 'cash',
  `approved_by` varchar(50) NOT NULL,
  `receipt_number` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_date` (`date`),
  KEY `idx_category` (`category`),
  KEY `idx_amount` (`amount`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Settlements table
CREATE TABLE IF NOT EXISTS `settlements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL,
  `settlement_date` date NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `type` enum('salary','bonus','advance','deduction') NOT NULL,
  `description` text,
  `payment_method` varchar(20) DEFAULT 'cash',
  `processed_by` varchar(50) NOT NULL,
  `status` enum('completed','pending') DEFAULT 'completed',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_staff_id` (`staff_id`),
  KEY `idx_settlement_date` (`settlement_date`),
  KEY `idx_type` (`type`),
  CONSTRAINT `fk_settlements_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Date locks table (for preventing edits to locked dates)
CREATE TABLE IF NOT EXISTS `locks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `locked_date` date NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `locked_by` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `locked_date` (`locked_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default admin user (password: admin123)
INSERT IGNORE INTO `users` (`username`, `password_hash`, `role`) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin');

-- Insert sample data for demonstration (optional)
INSERT IGNORE INTO `staff` (`name`, `contact_number`, `position`, `salary`, `hire_date`) VALUES
('John Doe', '9876543210', 'Manager', 25000.00, '2023-01-15'),
('Jane Smith', '9876543211', 'Chef', 20000.00, '2023-02-01'),
('Mike Johnson', '9876543212', 'Waiter', 15000.00, '2023-03-01'),
('Sarah Wilson', '9876543213', 'Cleaner', 12000.00, '2023-03-15');

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS `idx_bookings_composite` ON `bookings` (`booking_date`, `status`);
CREATE INDEX IF NOT EXISTS `idx_attendance_composite` ON `attendance` (`date`, `status`);
CREATE INDEX IF NOT EXISTS `idx_income_composite` ON `income` (`date`, `category`);
CREATE INDEX IF NOT EXISTS `idx_expenses_composite` ON `expenses` (`date`, `category`);
CREATE INDEX IF NOT EXISTS `idx_settlements_composite` ON `settlements` (`settlement_date`, `type`);

-- Views for reporting (optional)
CREATE OR REPLACE VIEW `v_monthly_income` AS
SELECT 
    YEAR(date) as year,
    MONTH(date) as month,
    category,
    SUM(amount) as total_amount,
    COUNT(*) as record_count
FROM income 
GROUP BY YEAR(date), MONTH(date), category;

CREATE OR REPLACE VIEW `v_monthly_expenses` AS
SELECT 
    YEAR(date) as year,
    MONTH(date) as month,
    category,
    SUM(amount) as total_amount,
    COUNT(*) as record_count
FROM expenses 
GROUP BY YEAR(date), MONTH(date), category;

CREATE OR REPLACE VIEW `v_staff_attendance_summary` AS
SELECT 
    s.id as staff_id,
    s.name as staff_name,
    s.position,
    YEAR(a.date) as year,
    MONTH(a.date) as month,
    COUNT(*) as total_days,
    SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_days,
    SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_days,
    SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_days,
    SUM(CASE WHEN a.status = 'half_day' THEN 1 ELSE 0 END) as half_days,
    ROUND((SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) * 100.0 / COUNT(*)), 2) as attendance_percentage
FROM staff s
LEFT JOIN attendance a ON s.id = a.staff_id
WHERE s.status = 'active'
GROUP BY s.id, s.name, s.position, YEAR(a.date), MONTH(a.date);

-- Stored procedures for common operations (optional)
DELIMITER //

CREATE PROCEDURE IF NOT EXISTS `GetMonthlyReport`(
    IN report_year INT,
    IN report_month INT
)
BEGIN
    -- Income summary
    SELECT 'INCOME' as type, category, SUM(amount) as total, COUNT(*) as records
    FROM income 
    WHERE YEAR(date) = report_year AND MONTH(date) = report_month
    GROUP BY category
    
    UNION ALL
    
    -- Expense summary
    SELECT 'EXPENSE' as type, category, SUM(amount) as total, COUNT(*) as records
    FROM expenses 
    WHERE YEAR(date) = report_year AND MONTH(date) = report_month
    GROUP BY category;
END //

CREATE PROCEDURE IF NOT EXISTS `GetStaffPerformance`(
    IN staff_id INT,
    IN start_date DATE,
    IN end_date DATE
)
BEGIN
    SELECT 
        s.name,
        s.position,
        COUNT(a.id) as total_days,
        SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_days,
        SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_days,
        SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_days,
        ROUND((SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) * 100.0 / COUNT(a.id)), 2) as attendance_percentage
    FROM staff s
    LEFT JOIN attendance a ON s.id = a.staff_id 
        AND a.date BETWEEN start_date AND end_date
    WHERE s.id = staff_id
    GROUP BY s.id, s.name, s.position;
END //

DELIMITER ;

-- Triggers for audit trail (optional)
CREATE TRIGGER IF NOT EXISTS `tr_bookings_audit` 
AFTER UPDATE ON `bookings`
FOR EACH ROW
BEGIN
    IF NEW.status != OLD.status THEN
        INSERT INTO audit_log (table_name, record_id, action, old_values, new_values, changed_by, changed_at)
        VALUES ('bookings', NEW.id, 'UPDATE', 
                CONCAT('status:', OLD.status), 
                CONCAT('status:', NEW.status), 
                USER(), NOW());
    END IF;
END;

-- Create audit log table
CREATE TABLE IF NOT EXISTS `audit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `table_name` varchar(50) NOT NULL,
  `record_id` int(11) NOT NULL,
  `action` enum('INSERT','UPDATE','DELETE') NOT NULL,
  `old_values` text,
  `new_values` text,
  `changed_by` varchar(100) NOT NULL,
  `changed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_table_record` (`table_name`, `record_id`),
  KEY `idx_changed_at` (`changed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;