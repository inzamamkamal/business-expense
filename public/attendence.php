<?php
include '../includes/session.php';
require '../config/db.php';
require '../includes/functions.php';

// CSRF Protection
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Handle attendance submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($csrf_token, $_POST['csrf_token'])) {
        $_SESSION['error'] = 'Invalid CSRF token';
        header('Location: attendance.php');
        exit;
    }
    
    if (isset($_POST['submit_attendance'])) {
        // Validate date
        $attendance_date = filter_var($_POST['attendance_date'], FILTER_SANITIZE_STRING);
        if (!DateTime::createFromFormat('Y-m-d', $attendance_date)) {
            $_SESSION['error'] = 'Invalid date format';
            header('Location: attendance.php');
            exit;
        }

        // Validate future dates
        $today = new DateTime();
        $selected_date = new DateTime($attendance_date);
        if ($selected_date > $today) {
            $_SESSION['error'] = 'Cannot record attendance for future dates';
            header('Location: attendance.php');
            exit;
        }

        $pdo->beginTransaction();
        
        try {
            foreach ($_POST['attendance'] as $staff_id => $data) {
                $staff_id = (int)$staff_id;
                $status = filter_var($data['status'], FILTER_SANITIZE_STRING);
                
                // Validate status
                $allowed_status = ['Present', 'Absent', 'Late', 'Half Day'];
                if (!in_array($status, $allowed_status)) {
                    throw new Exception("Invalid status for staff ID: $staff_id");
                }
                
                // Validate times
                $check_in_time = !empty($data['check_in']) ? filter_var($data['check_in'], FILTER_SANITIZE_STRING) : null;
                $check_out_time = !empty($data['check_out']) ? filter_var($data['check_out'], FILTER_SANITIZE_STRING) : null;
                
                if ($check_in_time && !DateTime::createFromFormat('H:i', $check_in_time)) {
                    throw new Exception("Invalid check-in time for staff ID: $staff_id");
                }
                
                if ($check_out_time && !DateTime::createFromFormat('H:i', $check_out_time)) {
                    throw new Exception("Invalid check-out time for staff ID: $staff_id");
                }
                
                // Calculate late minutes (example logic)
                $late_by_minutes = 0;
                if ($status === 'Late' && $check_in_time) {
                    $scheduled_time = new DateTime('09:00');
                    $actual_time = new DateTime($check_in_time);
                    if ($actual_time > $scheduled_time) {
                        $interval = $scheduled_time->diff($actual_time);
                        $late_by_minutes = $interval->h * 60 + $interval->i;
                    }
                }
                
                // Check if record exists
                $stmt = $pdo->prepare("SELECT id FROM attendance WHERE staff_id = ? AND attendance_date = ?");
                $stmt->execute([$staff_id, $attendance_date]);
                $exists = $stmt->fetch();
                
                if ($exists) {
                    // Update existing record
                    $stmt = $pdo->prepare("UPDATE attendance SET 
                        status = ?, 
                        check_in_time = ?,
                        check_out_time = ?,
                        late_by_minutes = ?,
                        updated_at = CURRENT_TIMESTAMP 
                        WHERE id = ?");
                    $stmt->execute([
                        $status, 
                        $check_in_time,
                        $check_out_time,
                        $late_by_minutes,
                        $exists['id']
                    ]);
                } else {
                    // Insert new record
                    $stmt = $pdo->prepare("INSERT INTO attendance 
                        (staff_id, attendance_date, status, check_in_time, check_out_time, late_by_minutes) 
                        VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $staff_id, 
                        $attendance_date, 
                        $status, 
                        $check_in_time,
                        $check_out_time,
                        $late_by_minutes
                    ]);
                }
            }
            
            $pdo->commit();
            $_SESSION['success'] = 'Attendance recorded successfully!';
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error'] = 'Error: ' . $e->getMessage();
        }
    }
}

// Get all active staff
$staff = $pdo->query("SELECT * FROM staff WHERE status = 'Active'")->fetchAll();

// Get today's attendance if exists
$attendance_today = [];
$today = date('Y-m-d');
$stmt = $pdo->prepare("SELECT staff_id, status, check_in_time, check_out_time FROM attendance WHERE attendance_date = ?");
$stmt->execute([$today]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $attendance_today[$row['staff_id']] = $row;
}

// Get attendance summary for dashboard
$attendance_summary = $pdo->query("
    SELECT 
        COUNT(*) AS total_staff,
        SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) AS present_count,
        SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) AS absent_count,
        SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) AS late_count,
        SUM(CASE WHEN status = 'Half Day' THEN 1 ELSE 0 END) AS halfday_count
    FROM attendance 
    WHERE attendance_date = CURDATE()
")->fetch(PDO::FETCH_ASSOC);

// If no attendance recorded today, initialize with staff counts
if (!$attendance_summary || $attendance_summary['total_staff'] === null) {
    $total_staff = $pdo->query("SELECT COUNT(*) FROM staff WHERE status = 'Active'")->fetchColumn();
    $attendance_summary = [
        'total_staff' => $total_staff,
        'present_count' => 0,
        'absent_count' => 0,
        'late_count' => 0,
        'halfday_count' => 0
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>BTS DISC 2.0 - Staff Attendance</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <style>
    :root {
      --primary: #4361ee;
      --secondary: #3f37c9;
      --success: #4cc9f0;
      --danger: #f72585;
      --warning: #f8961e;
      --info: #4895ef;
      --halfday: #7209b7;
      --dark: #1e1e2d;
      --light: #f8f9fa;
      --gray: #6c757d;
      --card-bg: rgba(255, 255, 255, 0.05);
      --card-border: rgba(255, 255, 255, 0.1);
    }
    
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    
    body {
      min-height: 100vh;
      background: linear-gradient(135deg, #1a2a6c, #3a1b6c);
      padding: 20px;
      color: #fff;
    }
    
    .dashboard {
      background: rgba(255, 255, 255, 0.08);
      backdrop-filter: blur(10px);
      border-radius: 16px;
      padding: 30px;
      width: 100%;
      max-width: 1400px;
      margin: 0 auto;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
    }
    
    .dashboard-header {
      margin-bottom: 30px;
      padding-bottom: 20px;
      border-bottom: 1px solid var(--card-border);
      text-align: center;
    }
    
    .dashboard-header h2 {
      font-size: 28px;
      margin-bottom: 8px;
      background: linear-gradient(to right, #4cc9f0, #4361ee);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }
    
    .welcome-text {
      color: rgba(255, 255, 255, 0.8);
      font-size: 16px;
      max-width: 600px;
      margin: 0 auto;
    }
    
    .header-actions {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: 20px;
      flex-wrap: wrap;
      gap: 15px;
    }
    
    .user-info {
      display: flex;
      align-items: center;
      gap: 12px;
    }
    
    .user-icon {
      width: 40px;
      height: 40px;
      background: linear-gradient(135deg, #7e4c2e, #5a3521);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-weight: bold;
      font-size: 18px;
    }
    
    .username {
      font-weight: 600;
      font-size: 16px;
    }
    
    .back-btn {
      display: flex;
      align-items: center;
      gap: 8px;
      color: #fff;
      text-decoration: none;
      padding: 10px 15px;
      background: rgba(255, 255, 255, 0.1);
      border-radius: 8px;
      transition: all 0.3s ease;
    }
    
    .back-btn:hover {
      background: rgba(255, 255, 255, 0.15);
      transform: translateY(-2px);
    }
    
    /* Notification styles */
    .notification {
      padding: 15px;
      border-radius: 10px;
      margin-bottom: 25px;
      text-align: center;
      font-weight: 500;
      display: none;
    }
    
    .success-message {
      background: rgba(46, 204, 113, 0.15);
      border: 1px solid #2ecc71;
      color: #2ecc71;
    }
    
    .error-message {
      background: rgba(231, 76, 60, 0.15);
      border: 1px solid #e74c3c;
      color: #e74c3c;
    }
    
    /* Attendance Section */
    .attendance-section {
      background: var(--card-bg);
      border-radius: 14px;
      padding: 25px;
      margin-top: 25px;
      border: 1px solid var(--card-border);
    }
    
    .section-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 25px;
      flex-wrap: wrap;
      gap: 15px;
    }
    
    .section-title {
      font-size: 22px;
      font-weight: 600;
      background: linear-gradient(to right, #4cc9f0, #4895ef);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }
    
    .date-selector {
      display: flex;
      align-items: center;
      gap: 10px;
      background: rgba(255, 255, 255, 0.07);
      padding: 10px 15px;
      border-radius: 10px;
    }
    
    .date-label {
      color: rgba(255, 255, 255, 0.8);
      font-size: 15px;
    }
    
    .date-input {
      padding: 8px 12px;
      border-radius: 8px;
      border: none;
      background: rgba(255, 255, 255, 0.1);
      color: white;
      font-size: 15px;
      min-width: 160px;
    }
    
    .staff-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
      gap: 20px;
      margin-top: 20px;
    }
    
    .staff-card {
      background: rgba(255, 255, 255, 0.07);
      border-radius: 14px;
      padding: 20px;
      display: flex;
      flex-direction: column;
      gap: 15px;
      transition: all 0.3s ease;
      border: 2px solid transparent;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }
    
    .staff-card.present {
      border-color: #2ecc71;
      background: rgba(46, 204, 113, 0.08);
    }
    
    .staff-card.absent {
      border-color: #e74c3c;
      background: rgba(231, 76, 60, 0.08);
    }
    
    .staff-card.late {
      border-color: #f39c12;
      background: rgba(243, 156, 18, 0.08);
    }
    
    .staff-card.halfday {
      border-color: #7209b7;
      background: rgba(114, 9, 183, 0.08);
    }
    
    .staff-header {
      display: flex;
      align-items: center;
      gap: 15px;
    }
    
    .staff-avatar {
      width: 60px;
      height: 60px;
      border-radius: 50%;
      background: linear-gradient(135deg, #6a11cb, #2575fc);
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-weight: bold;
      font-size: 24px;
      flex-shrink: 0;
    }
    
    .staff-info {
      flex-grow: 1;
    }
    
    .staff-name {
      font-weight: 600;
      font-size: 18px;
      margin-bottom: 5px;
    }
    
    .staff-position {
      color: rgba(255, 255, 255, 0.7);
      font-size: 14px;
    }
    
    .time-inputs {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
      margin-top: 10px;
    }
    
    .time-group {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }
    
    .time-label {
      font-size: 13px;
      color: rgba(255, 255, 255, 0.7);
    }
    
    .time-input {
      padding: 10px;
      border-radius: 8px;
      border: 1px solid rgba(255, 255, 255, 0.1);
      background: rgba(255, 255, 255, 0.05);
      color: white;
      font-size: 14px;
      width: 100%;
    }
    
    .attendance-actions {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
      margin-top: 10px;
    }
    
    .att-btn {
      flex: 1;
      min-width: 70px;
      padding: 10px;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      border: none;
      cursor: pointer;
      font-size: 14px;
      font-weight: 500;
      transition: all 0.3s ease;
    }
    
    .present-btn {
      background: rgba(46, 204, 113, 0.15);
      color: #2ecc71;
    }
    
    .present-btn:hover, .present-btn.active {
      background: #2ecc71;
      color: white;
    }
    
    .absent-btn {
      background: rgba(231, 76, 60, 0.15);
      color: #e74c3c;
    }
    
    .absent-btn:hover, .absent-btn.active {
      background: #e74c3c;
      color: white;
    }
    
    .late-btn {
      background: rgba(243, 156, 18, 0.15);
      color: #f39c12;
    }
    
    .late-btn:hover, .late-btn.active {
      background: #f39c12;
      color: white;
    }
    
    .halfday-btn {
      background: rgba(114, 9, 183, 0.15);
      color: #7209b7;
    }
    
    .halfday-btn:hover, .halfday-btn.active {
      background: #7209b7;
      color: white;
    }
    
    .summary-cards {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 20px;
      margin-top: 30px;
    }
    
    .summary-card {
      background: var(--card-bg);
      border-radius: 14px;
      padding: 20px;
      text-align: center;
      transition: all 0.3s ease;
      border-top: 4px solid;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }
    
    .summary-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
    }
    
    .card-title {
      font-size: 15px;
      margin-bottom: 10px;
      color: rgba(255, 255, 255, 0.7);
    }
    
    .card-value {
      font-size: 34px;
      font-weight: 700;
    }
    
    .present-card {
      border-color: #2ecc71;
    }
    
    .absent-card {
      border-color: #e74c3c;
    }
    
    .late-card {
      border-color: #f39c12;
    }
    
    .halfday-card {
      border-color: #7209b7;
    }
    
    .total-card {
      border-color: #3498db;
    }
    
    .form-actions {
      display: flex;
      justify-content: center;
      gap: 15px;
      margin-top: 30px;
      flex-wrap: wrap;
    }
    
    .submit-btn {
      padding: 14px 25px;
      border-radius: 10px;
      border: none;
      background: linear-gradient(135deg, #4361ee, #3a0ca3);
      color: white;
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      display: flex;
      align-items: center;
      gap: 8px;
      min-width: 200px;
      justify-content: center;
    }
    
    .submit-btn:hover {
      transform: translateY(-3px);
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
    }
    
    .mark-all-btn {
      background: linear-gradient(135deg, #2ecc71, #27ae60);
    }
    
    .mark-all-btn:hover {
      background: linear-gradient(135deg, #27ae60, #219653);
    }
    
    /* Report Section */
    .report-section {
      background: var(--card-bg);
      border-radius: 14px;
      padding: 25px;
      margin-top: 30px;
      border: 1px solid var(--card-border);
    }
    
    .report-filters {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 15px;
      margin-bottom: 25px;
    }
    
    .filter-group {
      display: flex;
      flex-direction: column;
    }
    
    .filter-label {
      display: block;
      margin-bottom: 8px;
      font-size: 14px;
      color: rgba(255, 255, 255, 0.7);
    }
    
    .filter-select, .filter-input {
      padding: 12px 15px;
      border-radius: 8px;
      border: 1px solid rgba(255, 255, 255, 0.1);
      background: rgba(255, 255, 255, 0.05);
      color: white;
      font-size: 15px;
      width: 100%;
    }
    
    .filter-btn {
      align-self: flex-end;
      margin-top: auto;
    }
    
    .attendance-table {
      width: 100%;
      border-collapse: collapse;
      background: rgba(255, 255, 255, 0.03);
      border-radius: 12px;
      overflow: hidden;
    }
    
    .attendance-table th {
      background: rgba(67, 97, 238, 0.25);
      color: white;
      text-align: left;
      padding: 16px 20px;
      font-weight: 600;
      font-size: 15px;
    }
    
    .attendance-table td {
      padding: 14px 20px;
      border-bottom: 1px solid rgba(255, 255, 255, 0.05);
      font-size: 14px;
    }
    
    .attendance-table tr:last-child td {
      border-bottom: none;
    }
    
    .attendance-table tr:hover td {
      background: rgba(255, 255, 255, 0.05);
    }
    
    .status-present {
      color: #2ecc71;
      font-weight: 600;
    }
    
    .status-absent {
      color: #e74c3c;
      font-weight: 600;
    }
    
    .status-late {
      color: #f39c12;
      font-weight: 600;
    }
    
    .status-halfday {
      color: #7209b7;
      font-weight: 600;
    }
    
    .action-btn {
      padding: 8px 12px;
      border-radius: 6px;
      border: none;
      background: rgba(67, 97, 238, 0.15);
      color: #4361ee;
      font-size: 13px;
      cursor: pointer;
      transition: all 0.3s ease;
      display: flex;
      align-items: center;
      gap: 5px;
    }
    
    .action-btn:hover {
      background: #4361ee;
      color: white;
    }
    
    .dashboard-footer {
      margin-top: 40px;
      color: rgba(255, 255, 255, 0.6);
      font-size: 14px;
      padding-top: 20px;
      border-top: 1px solid rgba(255, 255, 255, 0.1);
      text-align: center;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
      .dashboard {
        padding: 20px;
      }
      
      .staff-grid {
        grid-template-columns: 1fr;
      }
      
      .section-header {
        flex-direction: column;
        align-items: flex-start;
      }
      
      .date-selector {
        width: 100%;
      }
      
      .form-actions {
        flex-direction: column;
        align-items: center;
      }
      
      .submit-btn {
        width: 100%;
        max-width: 100%;
      }
    }
    
    /* Loading spinner */
    .loading-spinner {
      display: none;
      border: 3px solid rgba(255, 255, 255, 0.3);
      border-radius: 50%;
      border-top: 3px solid #4361ee;
      width: 20px;
      height: 20px;
      animation: spin 1s linear infinite;
      margin-left: 10px;
    }
    
    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
  </style>
</head>
<body>
  <div class="dashboard">
    <div class="dashboard-header">
      <h2>Staff Attendance System</h2>
      <div class="welcome-text">Comprehensive attendance tracking with real-time reporting</div>
      
      <div class="header-actions">
        <a href="/btsapp/dashboard.php" class="back-btn">
          <i class="fas fa-arrow-left"></i> Dashboard
        </a>
        
        <div class="user-info">
          <div class="user-icon"><?= strtoupper(substr($_SESSION['username'], 0, 1)) ?></div>
          <div class="username"><?= htmlspecialchars($_SESSION['username']) ?></div>
        </div>
      </div>
    </div>
    
    <?php if (isset($_SESSION['success'])): ?>
      <div class="notification success-message" id="success-message">
        <i class="fas fa-check-circle"></i> <?= $_SESSION['success'] ?>
      </div>
      <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
      <div class="notification error-message" id="error-message">
        <i class="fas fa-exclamation-circle"></i> <?= $_SESSION['error'] ?>
      </div>
      <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="attendance-section">
      <div class="section-header">
        <h3 class="section-title">Daily Attendance Tracking</h3>
        <div class="date-selector">
          <span class="date-label">Attendance Date:</span>
          <input type="date" class="date-input" id="attendance-date" value="<?= date('Y-m-d') ?>">
        </div>
      </div>
      
      <form method="post" id="attendance-form">
        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
        <input type="hidden" name="attendance_date" id="form-attendance-date" value="<?= date('Y-m-d') ?>">
        
        <div class="staff-grid">
          <?php foreach ($staff as $member): ?>
            <?php 
            $att_data = $attendance_today[$member['id']] ?? [
                'status' => '',
                'check_in_time' => '',
                'check_out_time' => ''
            ];
            
            $initials = '';
            $name_parts = explode(' ', $member['full_name']);
            foreach ($name_parts as $part) {
                $initials .= strtoupper(substr($part, 0, 1));
            }
            ?>
            <div class="staff-card <?= $att_data['status'] ? strtolower($att_data['status']) : '' ?>">
              <div class="staff-header">
                <div class="staff-avatar"><?= $initials ?></div>
                <div class="staff-info">
                  <div class="staff-name"><?= htmlspecialchars($member['full_name']) ?></div>
                  <div class="staff-position"><?= htmlspecialchars($member['position']) ?></div>
                </div>
              </div>
              
              <div class="time-inputs">
                <div class="time-group">
                  <label class="time-label">Check In</label>
                  <input type="time" class="time-input" 
                    name="attendance[<?= $member['id'] ?>][check_in]" 
                    value="<?= $att_data['check_in_time'] ? htmlspecialchars($att_data['check_in_time']) : '' ?>">
                </div>
                
                <div class="time-group">
                  <label class="time-label">Check Out</label>
                  <input type="time" class="time-input" 
                    name="attendance[<?= $member['id'] ?>][check_out]" 
                    value="<?= $att_data['check_out_time'] ? htmlspecialchars($att_data['check_out_time']) : '' ?>">
                </div>
              </div>
              
              <div class="attendance-actions">
                <input type="hidden" name="attendance[<?= $member['id'] ?>][status]" 
                  id="status_<?= $member['id'] ?>" value="<?= $att_data['status'] ?>">
                  
                <button type="button" class="att-btn present-btn <?= $att_data['status'] === 'Present' ? 'active' : '' ?>" 
                  data-staff="<?= $member['id'] ?>" data-status="Present">
                  <i class="fas fa-check"></i> Present
                </button>
                
                <button type="button" class="att-btn absent-btn <?= $att_data['status'] === 'Absent' ? 'active' : '' ?>" 
                  data-staff="<?= $member['id'] ?>" data-status="Absent">
                  <i class="fas fa-times"></i> Absent
                </button>
                
                <button type="button" class="att-btn late-btn <?= $att_data['status'] === 'Late' ? 'active' : '' ?>" 
                  data-staff="<?= $member['id'] ?>" data-status="Late">
                  <i class="fas fa-clock"></i> Late
                </button>
                
                <button type="button" class="att-btn halfday-btn <?= $att_data['status'] === 'Half Day' ? 'active' : '' ?>" 
                  data-staff="<?= $member['id'] ?>" data-status="Half Day">
                  <i class="fas fa-adjust"></i> Half Day
                </button>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        
        <div class="summary-cards">
          <div class="summary-card present-card">
            <div class="card-title">Present Today</div>
            <div class="card-value"><?= $attendance_summary['present_count'] ?></div>
          </div>
          
          <div class="summary-card absent-card">
            <div class="card-title">Absent Today</div>
            <div class="card-value"><?= $attendance_summary['absent_count'] ?></div>
          </div>
          
          <div class="summary-card late-card">
            <div class="card-title">Late Today</div>
            <div class="card-value"><?= $attendance_summary['late_count'] ?></div>
          </div>
          
          <div class="summary-card halfday-card">
            <div class="card-title">Half Day</div>
            <div class="card-value"><?= $attendance_summary['halfday_count'] ?></div>
          </div>
          
          <div class="summary-card total-card">
            <div class="card-title">Total Staff</div>
            <div class="card-value"><?= $attendance_summary['total_staff'] ?></div>
          </div>
        </div>
        
        <div class="form-actions">
          <button type="button" id="mark-all-present" class="submit-btn mark-all-btn">
            <i class="fas fa-check-circle"></i> Mark All Present
          </button>
          
          <button type="submit" name="submit_attendance" class="submit-btn" id="save-attendance">
            <i class="fas fa-save"></i> Save Attendance
            <div class="loading-spinner" id="loading-spinner"></div>
          </button>
        </div>
      </form>
    </div>
    
    <!-- Report Section -->
    <div class="report-section">
      <div class="section-header">
        <h3 class="section-title">Attendance History</h3>
      </div>
      
      <div class="report-filters">
        <div class="filter-group">
          <label class="filter-label">Staff Member</label>
          <select class="filter-select">
            <option>All Staff</option>
            <?php foreach ($staff as $member): ?>
              <option><?= htmlspecialchars($member['full_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        
        <div class="filter-group">
          <label class="filter-label">Date Range</label>
          <select class="filter-select">
            <option>Today</option>
            <option>This Week</option>
            <option>This Month</option>
            <option>Last 7 Days</option>
            <option>Last 30 Days</option>
          </select>
        </div>
        
        <div class="filter-group">
          <label class="filter-label">Status</label>
          <select class="filter-select">
            <option>All Statuses</option>
            <option>Present</option>
            <option>Absent</option>
            <option>Late</option>
            <option>Half Day</option>
          </select>
        </div>
        
        <div class="filter-group">
          <label class="filter-label">Date</label>
          <input type="date" class="filter-input" value="<?= date('Y-m-d') ?>">
        </div>
        
        <div class="filter-group filter-btn">
          <button class="submit-btn">
            <i class="fas fa-filter"></i> Apply Filters
          </button>
        </div>
      </div>
      
      <div style="overflow-x: auto;">
        <table class="attendance-table">
          <thead>
            <tr>
              <th>Date</th>
              <th>Staff Member</th>
              <th>Position</th>
              <th>Status</th>
              <th>Check In</th>
              <th>Check Out</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php
            // Get attendance records
            $stmt = $pdo->query("
                SELECT a.*, s.full_name, s.position 
                FROM attendance a
                JOIN staff s ON a.staff_id = s.id
                ORDER BY a.attendance_date DESC
            ");
            while ($record = $stmt->fetch(PDO::FETCH_ASSOC)):
            ?>
            <tr>
              <td><?= date('M j, Y', strtotime($record['attendance_date'])) ?></td>
              <td><?= htmlspecialchars($record['full_name']) ?></td>
              <td><?= htmlspecialchars($record['position']) ?></td>
              <td class="status-<?= strtolower($record['status']) ?>"><?= $record['status'] ?></td>
              <td><?= $record['check_in_time'] ? htmlspecialchars($record['check_in_time']) : '-' ?></td>
              <td><?= $record['check_out_time'] ? htmlspecialchars($record['check_out_time']) : '-' ?></td>
              <td>
                <button class="action-btn">
                  <i class="fas fa-edit"></i> Edit
                </button>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
      
      <div style="text-align: center; margin-top: 30px;">
        <button class="submit-btn">
          <i class="fas fa-file-export"></i> Export to Excel
        </button>
      </div>
    </div>
    
    <div class="dashboard-footer">
      BTS DISC 2.0 Financial System | Staff Attendance Module
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Set today's date as default
      const dateEl = document.getElementById('attendance-date');
      dateEl.valueAsDate = new Date();
      
      // Update form date when changed
      dateEl.addEventListener('change', function() {
        document.getElementById('form-attendance-date').value = this.value;
      });
      
      // Initialize time inputs
      flatpickr('.time-input', {
        enableTime: true,
        noCalendar: true,
        dateFormat: "H:i",
        time_24hr: true
      });
      
      // Attendance marking functionality
      document.querySelectorAll('.att-btn').forEach(btn => {
        btn.addEventListener('click', function() {
          const staffId = this.getAttribute('data-staff');
          const status = this.getAttribute('data-status');
          
          // Update hidden input value
          document.getElementById('status_' + staffId).value = status;
          
          // Update button active state
          const parent = this.closest('.attendance-actions');
          parent.querySelectorAll('.att-btn').forEach(b => {
            b.classList.remove('active');
          });
          this.classList.add('active');
          
          // Update card appearance
          const card = this.closest('.staff-card');
          card.className = 'staff-card ' + status.toLowerCase().replace(' ', '');
          
          // Update summary counts
          updateSummaryCounts();
        });
      });
      
      // Function to update summary counts
      function updateSummaryCounts() {
        let presentCount = 0;
        let absentCount = 0;
        let lateCount = 0;
        let halfdayCount = 0;
        let totalCount = <?= count($staff) ?>;
        
        document.querySelectorAll('[id^="status_"]').forEach(input => {
          if (input.value === 'Present') presentCount++;
          if (input.value === 'Absent') absentCount++;
          if (input.value === 'Late') lateCount++;
          if (input.value === 'Half Day') halfdayCount++;
        });
        
        document.querySelector('.present-card .card-value').textContent = presentCount;
        document.querySelector('.absent-card .card-value').textContent = absentCount;
        document.querySelector('.late-card .card-value').textContent = lateCount;
        document.querySelector('.halfday-card .card-value').textContent = halfdayCount;
      }
      
      // Mark all present
      document.getElementById('mark-all-present').addEventListener('click', function() {
        if (!confirm('Are you sure you want to mark all staff as present?')) return;
        
        document.querySelectorAll('.staff-card').forEach(card => {
          const staffId = card.querySelector('[id^="status_"]').id.split('_')[1];
          
          // Update hidden input
          document.getElementById('status_' + staffId).value = 'Present';
          
          // Update buttons
          const buttons = card.querySelectorAll('.att-btn');
          buttons.forEach(btn => btn.classList.remove('active'));
          card.querySelector('.present-btn').classList.add('active');
          
          // Update card appearance
          card.className = 'staff-card present';
        });
        
        // Update counts
        updateSummaryCounts();
        
        // Show success message
        showNotification('All staff members marked as present!', 'success');
      });
      
      // Form submission loading indicator
      const form = document.getElementById('attendance-form');
      const saveBtn = document.getElementById('save-attendance');
      const spinner = document.getElementById('loading-spinner');
      
      form.addEventListener('submit', function() {
        saveBtn.disabled = true;
        spinner.style.display = 'inline-block';
        saveBtn.innerHTML = '<i class="fas fa-save"></i> Saving...';
      });
      
      // Auto-hide messages
      const messages = document.querySelectorAll('.notification');
      messages.forEach(msg => {
        if (msg.textContent.trim() !== '') {
          msg.style.display = 'block';
          setTimeout(() => {
            msg.style.display = 'none';
          }, 5000);
        }
      });
      
      // Show notification function
      function showNotification(message, type) {
        const notification = document.createElement('div');
        notification.className = `notification ${type}-message`;
        notification.innerHTML = `<i class="fas fa-${type === 'success' ? 'check' : 'exclamation'}-circle"></i> ${message}`;
        document.querySelector('.dashboard').insertBefore(notification, document.querySelector('.attendance-section'));
        
        setTimeout(() => {
          notification.style.display = 'none';
        }, 5000);
      }
      
      // Time validation
      document.querySelectorAll('.time-input').forEach(input => {
        input.addEventListener('change', function() {
          const time = this.value;
          if (time && !/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/.test(time)) {
            alert('Please enter a valid time in HH:MM format');
            this.value = '';
          }
        });
      });
    });
  </script>
</body>
</html>