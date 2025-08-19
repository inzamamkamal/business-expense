
<?php
// Start session and include database connection

include '../includes/session.php';
require '../config/db.php';


if (!isset($_SESSION['username']) || !isset($_SESSION['role']) || 
    !in_array($_SESSION['role'], ['admin', 'super_admin','user'])) {
    session_destroy();
    header('Location: ../index.php');
    exit();
}

$role = $_SESSION['role'];

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle API requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $response = ['status' => 'error', 'message' => 'Invalid CSRF token'];
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    
    $action = $_POST['action'] ?? '';
    $response = ['status' => 'error', 'message' => 'Invalid request'];
    
    switch ($action) {
        case 'create_booking':
            $response = createBooking($pdo);
            break;
            
        case 'get_bookings':
            $response = getBookings($pdo);
            break;
            
        case 'delete_booking':
            $response = deleteBooking($pdo);
            break;
            
        case 'get_booking_details':
            $response = getBookingDetails($pdo);
            break;
            
        case 'complete_booking':
            $response = completeBooking($pdo);
            break;
            
        default:
            $response = ['status' => 'error', 'message' => 'Invalid action'];
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Create a new booking
function createBooking($pdo) {
    $required = ['customerName', 'contactNumber', 'bookingDate', 'bookingTime', 'totalPerson', 'advancePaid', 'takenBy', 'paymentMethod', 'eventType', 'ISDJ', 'bookingType'];
    $data = $_POST;

    // echo "Debugging data: " . print_r($data, true);
    // die;
    
    // Validate required fields
    foreach ($required as $field) {
        if (empty($data[$field])) {
            return ['status' => 'error', 'message' => "Missing required field: $field"];
        }
    }
    
    // Validate date format
    if (!DateTime::createFromFormat('Y-m-d', $data['bookingDate'])) {
        return ['status' => 'error', 'message' => "Invalid date format"];
    }
    
    // Validate time format
    if (!DateTime::createFromFormat('H:i', $data['bookingTime'])) {
        return ['status' => 'error', 'message' => "Invalid time format"];
    }
    
    // Validate advance payment
    if (!is_numeric($data['advancePaid']) || $data['advancePaid'] < 0) {
        return ['status' => 'error', 'message' => "Invalid advance payment amount"];
    }
    
    // Generate booking ID
    $bookingId = generateBookingId($pdo);
    
    // Prepare data with validation
    $customerName = htmlspecialchars($data['customerName']);
    $contactNumber = preg_replace('/[^0-9+]/', '', $data['contactNumber']);
    $bookingDate = $data['bookingDate'];
    $bookingTime = $data['bookingTime'];
    $totalPerson = htmlspecialchars($data['totalPerson']);
    $advancePaid = (float)$data['advancePaid'];
    $takenBy = htmlspecialchars($data['takenBy']);
    $specialRequest = htmlspecialchars($data['specialRequest'] ?? '');
    $paymentMethod = in_array($data['paymentMethod'], ['cash', 'upi']) ? $data['paymentMethod'] : 'cash';
    $eventType = htmlspecialchars($data['eventType']);
    $isDj = htmlspecialchars($data['ISDJ']);
    $isDj = $isDj === 'Yes' ? 1 : 0; // Convert to boolean
    $bookingType = htmlspecialchars($data['bookingType']);
    // Insert booking using prepared statement
    $sql = "INSERT INTO bookings (
        booking_id, 
        customer_name, 
        contact_number, 
        booking_date, 
        booking_time, 
        total_persons, 
        advance_paid,
        advance_payment_method,
        advance_taken_by, 
        special_requests,
        event_type,
        is_dj,
        booking_type,
        status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        $bookingId, 
        $customerName, 
        $contactNumber, 
        $bookingDate, 
        $bookingTime, 
        $totalPerson, 
        $advancePaid,
        $paymentMethod,
        $takenBy, 
        $specialRequest,
        $eventType,
        $isDj,
        $bookingType
    ]);
    
    if ($result) {
        return [
            'status' => 'success', 
            'message' => 'Booking created successfully!',
            'booking_id' => $bookingId,
            'booking_data' => [
                'id' => $pdo->lastInsertId(),
                'booking_id' => $bookingId,
                'customer_name' => $customerName,
                'contact_number' => $contactNumber,
                'booking_date' => $bookingDate,
                'booking_time' => $bookingTime,
                'total_persons' => $totalPerson,
                'advance_paid' => $advancePaid,
                'advance_payment_method' => $paymentMethod,
                'advance_taken_by' => $takenBy,
                'event_type' => $eventType,
                'is_dj' => $isDj,
                'booking_type' => $bookingType,
                'special_requests' => $specialRequest,
                'status' => 'pending'
            ]
        ];
    } else {
        return ['status' => 'error', 'message' => 'Failed to create booking'];
    }
}

// Get bookings with filtering
function getBookings($pdo) {
    $filter = $_POST['filter'] ?? 'next30days';
    $search = $_POST['search'] ?? '';
    
    // Base query
    $sql = "SELECT * FROM bookings";
    
    // Apply date filters
    $today = date('Y-m-d');
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    $startOfWeek = date('Y-m-d', strtotime('monday this week'));
    $endOfWeek = date('Y-m-d', strtotime('sunday this week'));
    $nextWeekStart = date('Y-m-d', strtotime('monday next week'));
    $nextWeekEnd = date('Y-m-d', strtotime('sunday next week'));
    $next30Start = date('Y-m-d');
    $next30End = date('Y-m-d', strtotime('+30 days'));
    
    $where = [];
    $params = [];
    
    switch ($filter) {
        case 'today':
            $where[] = "booking_date = ?";
            $params[] = $today;
            break;
        case 'tomorrow':
            $where[] = "booking_date = ?";
            $params[] = $tomorrow;
            break;
        case 'thisWeek':
            $where[] = "booking_date BETWEEN ? AND ?";
            $params[] = $startOfWeek;
            $params[] = $endOfWeek;
            break;
        case 'nextWeek':
            $where[] = "booking_date BETWEEN ? AND ?";
            $params[] = $nextWeekStart;
            $params[] = $nextWeekEnd;
            break;
        case 'next30days':
            $where[] = "booking_date BETWEEN ? AND ?";
            $params[] = $next30Start;
            $params[] = $next30End;
            break;
        // 'all' filter doesn't need a date condition
    }
    
    // Apply search
    if (!empty($search)) {
        $safeSearch = '%' . $search . '%';
        $where[] = "(customer_name LIKE ? OR 
                     contact_number LIKE ? OR 
                     booking_id LIKE ? OR 
                     advance_taken_by LIKE ? OR
                     event_type LIKE ? OR
                     booking_type LIKE ?)";
        $params[] = $safeSearch;
        $params[] = $safeSearch;
        $params[] = $safeSearch;
        $params[] = $safeSearch;
        $params[] = $safeSearch;
        $params[] = $safeSearch;
    }
    
    // Build WHERE clause
    if (!empty($where)) {
        $sql .= " WHERE " . implode(' AND ', $where);
    }
    
    // Order by date and time
    $sql .= " ORDER BY booking_date ASC, booking_time ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    if (!$stmt) {
        return ['status' => 'error', 'message' => 'Failed to get bookings'];
    }
    
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'status' => 'success',
        'bookings' => $bookings
    ];
}

// Get booking details by ID
function getBookingDetails($pdo) {
    if (empty($_POST['id']) || !is_numeric($_POST['id'])) {
        return ['status' => 'error', 'message' => 'Invalid booking ID'];
    }
    
    $id = (int)$_POST['id'];
    
    $sql = "SELECT * FROM bookings WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($booking) {
        return [
            'status' => 'success',
            'booking' => $booking
        ];
    } else {
        return ['status' => 'error', 'message' => 'Booking not found'];
    }
}

// Complete a booking
function completeBooking($pdo) {
    $required = ['id', 'finalPaid', 'finalTakenBy', 'paymentMethod'];
    $data = $_POST;
    
    // Validate required fields
    foreach ($required as $field) {
        if (empty($data[$field])) {
            return ['status' => 'error', 'message' => "Missing required field: $field"];
        }
    }
    
    // Validate ID
    if (!is_numeric($data['id'])) {
        return ['status' => 'error', 'message' => "Invalid booking ID"];
    }
    
    // Validate final payment
    if (!is_numeric($data['finalPaid']) || $data['finalPaid'] < 0) {
        return ['status' => 'error', 'message' => "Invalid final payment amount"];
    }
    
    // Prepare data with validation
    $id = (int)$data['id'];
    $finalPaid = (float)$data['finalPaid'];
    $finalTakenBy = htmlspecialchars($data['finalTakenBy']);
    $paymentMethod = in_array($data['paymentMethod'], ['cash', 'upi']) ? $data['paymentMethod'] : 'cash';
    $isDj = isset($data['isDj']) ? (int)$data['isDj'] : 0;

    // Update booking using prepared statement
    $sql = "UPDATE bookings SET
        final_paid = ?,
        final_payment_method = ?,
        final_taken_by = ?,
        is_dj = ?,
        status = 'completed'
    WHERE id = ?";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        $finalPaid,
        $paymentMethod,
        $finalTakenBy,
        $isDj,
        $id
    ]);
    
    if ($result) {
        return [
            'status' => 'success', 
            'message' => 'Booking completed successfully!',
            'booking_id' => $id
        ];
    } else {
        return ['status' => 'error', 'message' => 'Failed to complete booking'];
    }
}

// Delete a booking
function deleteBooking($pdo) {
    if (empty($_POST['id']) || !is_numeric($_POST['id'])) {
        return ['status' => 'error', 'message' => 'Invalid booking ID'];
    }
    
    $id = (int)$_POST['id'];
    
    // Use prepared statement to prevent SQL injection
    $sql = "DELETE FROM bookings WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([$id]);
    
    if ($result) {
        if ($stmt->rowCount() > 0) {
            return ['status' => 'success', 'message' => 'Booking deleted successfully'];
        } else {
            return ['status' => 'error', 'message' => 'No booking found with that ID'];
        }
    } else {
        return ['status' => 'error', 'message' => 'Failed to delete booking'];
    }
}

// Generate unique booking ID
function generateBookingId($pdo) {
    $prefix = "BTS-";
    
    // Get the last inserted ID using prepared statement
    $stmt = $pdo->query("SELECT MAX(id) as max_id FROM bookings");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $nextId = $row['max_id'] ? $row['max_id'] + 1 : 1;
    
    return $prefix . str_pad($nextId, 4, '0', STR_PAD_LEFT);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BTS 2.0 - Booking Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        :root {
            --primary: #6a11cb;
            --secondary: #2575fc;
            --accent: #ff6b6b;
            --light: #f8f9fa;
            --dark: #212529;
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
            --gray: #6c757d;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e4edf5 100%);
            color: var(--dark);
            padding: 20px;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        header {
            background: linear-gradient(90deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: 20px 30px;
            position: relative;
            overflow: hidden;
        }
        
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            z-index: 2;
            position: relative;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .logo i {
            font-size: 2.2rem;
            color: #fff;
            background: rgba(255, 255, 255, 0.2);
            padding: 10px;
            border-radius: 10px;
        }
        
        .logo h1 {
            font-size: 1.8rem;
            font-weight: 700;
        }
        
        .logo span {
            font-weight: 300;
            opacity: 0.9;
            font-size: 1rem;
        }
        
        .header-actions {
            display: flex;
            gap: 15px;
        }
        
        .btn {
            padding: 10px 18px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            border: none;
            font-size: 14px;
        }
        
        .btn-primary {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(5px);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .btn-primary:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-3px);
        }
        
        .tabs {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            flex-wrap: wrap;
        }
        
        .tab-btn {
            padding: 8px 16px;
            background: rgba(255, 255, 255, 0.15);
            border: none;
            border-radius: 6px;
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .tab-btn:hover, .tab-btn.active {
            background: rgba(255, 255, 255, 0.3);
        }
        
        .main-content {
            display: flex;
            min-height: 600px;
            flex-wrap: wrap;
        }
        
        .booking-form-section {
            flex: 1;
            min-width: 500px;
            padding: 25px;
            background: var(--light);
            border-right: 1px solid #eaeaea;
        }
        
        .section-title {
            font-size: 1.6rem;
            color: var(--primary);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-title i {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            padding: 8px;
            border-radius: 8px;
            color: white;
            font-size: 1.2rem;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: var(--gray);
            font-size: 0.95rem;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ced4da;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--secondary);
            box-shadow: 0 0 0 3px rgba(37, 117, 252, 0.15);
            outline: none;
        }
        
        .payment-method {
            display: flex;
            gap: 15px;
            margin-top: 8px;
        }
        
        .payment-option {
            flex: 1;
            padding: 12px;
            border-radius: 8px;
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            cursor: pointer;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .payment-option.active {
            border-color: var(--primary);
            background: rgba(106, 17, 203, 0.05);
        }
        
        .payment-option i {
            font-size: 1.5rem;
            margin-bottom: 8px;
            color: var(--primary);
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .btn-submit {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 14px 28px;
            border-radius: 8px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1;
        }
        
        .btn-submit:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(106, 17, 203, 0.3);
        }
        
        .btn-reset {
            background: #e9ecef;
            color: var(--gray);
            padding: 14px 20px;
            border-radius: 8px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            flex: 1;
        }
        
        .btn-reset:hover {
            background: #dee2e6;
        }
        
        .bookings-section {
            flex: 2;
            min-width: 500px;
            padding: 25px;
            background: white;
        }
        
        .filter-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .filter-group {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .date-filter {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .date-btn {
            padding: 7px 14px;
            background: #e9ecef;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            font-size: 14px;
        }
        
        .date-btn:hover, .date-btn.active {
            background: var(--secondary);
            color: white;
        }
        
        .search-box {
            position: relative;
            flex: 1;
            min-width: 200px;
        }
        
        .search-box input {
            padding: 10px 15px 10px 40px;
            border: 1px solid #ced4da;
            border-radius: 8px;
            width: 100%;
            font-size: 14px;
        }
        
        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
        }
        
        .bookings-table-container {
            overflow-x: auto;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            margin-top: 15px;
        }
        
        .bookings-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
        }
        
        .bookings-table th {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 14px 18px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
        }
        
        .bookings-table td {
            padding: 14px 18px;
            border-bottom: 1px solid #edf2f7;
            font-size: 14px;
        }
        
        .bookings-table tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        
        .bookings-table tr:hover {
            background-color: #e9ecef;
        }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-confirmed {
            background: #d4edda;
            color: #155724;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-completed {
            background: #cce5ff;
            color: #004085;
        }
        
        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }
        
        .payment-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        
        .payment-cash {
            background: #d4edda;
            color: #155724;
        }
        
        .payment-upi {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .type-badge {
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        
        .badge-menu {
            background: #e0f7fa;
            color: #006064;
        }
        
        .badge-buffet {
            background: #f3e5f5;
            color: #4a148c;
        }
        
        .badge-yes {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .badge-no {
            background: #ffebee;
            color: #c62828;
        }
        
        .action-cell {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .action-btn {
            padding: 7px 10px;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 13px;
            border: none;
            transition: all 0.2s ease;
        }
        
        .btn-view {
            background: var(--primary);
            color: white;
        }
        
        .btn-done {
            background: var(--success);
            color: white;
        }
        
        .btn-delete {
            background: var(--danger);
            color: white;
        }
        
        .action-btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .page-btn {
            padding: 7px 14px;
            background: #e9ecef;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            font-size: 14px;
        }
        
        .page-btn.active {
            background: var(--secondary);
            color: white;
        }
        
        .receipt-preview, .completion-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
        }
        
        .receipt-preview.active, .completion-modal.active {
            opacity: 1;
            pointer-events: all;
        }
        
        .receipt-content, .completion-content {
            background: white;
            width: 90%;
            max-width: 500px;
            border-radius: 15px;
            overflow: hidden;
            transform: scale(0.9);
            transition: transform 0.3s ease;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
        }
        
        .receipt-preview.active .receipt-content,
        .completion-modal.active .completion-content {
            transform: scale(1);
        }
        
        .receipt-header, .completion-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 20px;
            text-align: center;
            position: relative;
        }
        
        .receipt-header h2, .completion-header h2 {
            font-size: 1.6rem;
            margin-bottom: 5px;
        }
        
        .receipt-header p, .completion-header p {
            opacity: 0.9;
            font-size: 0.95rem;
        }
        
        .receipt-logo, .completion-logo {
            position: absolute;
            top: 20px;
            left: 20px;
            font-size: 1.8rem;
            background: rgba(255, 255, 255, 0.2);
            padding: 8px;
            border-radius: 8px;
        }
        
        .receipt-body, .completion-body {
            padding: 25px;
        }
        
        .receipt-details, .completion-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .receipt-item, .completion-item {
            margin-bottom: 12px;
        }
        
        .receipt-label, .completion-label {
            font-weight: 600;
            color: var(--gray);
            margin-bottom: 5px;
            font-size: 14px;
        }
        
        .receipt-value, .completion-value {
            font-size: 15px;
            color: var(--dark);
        }
        
        .receipt-total {
            background: #f8f9fa;
            padding: 18px;
            border-radius: 10px;
            text-align: center;
            margin-top: 15px;
        }
        
        .receipt-total .amount {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary);
            margin: 10px 0;
        }
        
        .receipt-footer {
            padding: 18px;
            text-align: center;
            border-top: 1px dashed #ced4da;
            margin-top: 15px;
            color: var(--gray);
            font-size: 14px;
        }
        
        .receipt-note {
            background: #fff3cd;
            padding: 14px;
            border-radius: 8px;
            margin-top: 18px;
            color: #856404;
            font-weight: 500;
            font-size: 14px;
        }
        
        .receipt-actions, .completion-actions {
            display: flex;
            justify-content: center;
            gap: 15px;
            padding: 18px;
            flex-wrap: wrap;
        }
        
        .btn-print, .btn-complete {
            background: var(--primary);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            flex: 1;
        }
        
        .btn-close {
            background: var(--gray);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            flex: 1;
        }
        
        footer {
            background: #f8f9fa;
            padding: 18px;
            text-align: center;
            color: var(--gray);
            border-top: 1px solid #eaeaea;
            font-size: 14px;
        }
        
        .no-bookings {
            text-align: center;
            padding: 40px;
            color: var(--gray);
            font-size: 1.1rem;
        }
        
        .no-bookings i {
            font-size: 3rem;
            margin-bottom: 20px;
            color: #dee2e6;
        }
        
        @media (max-width: 1100px) {
            .main-content {
                flex-direction: column;
            }
            
            .booking-form-section {
                border-right: none;
                border-bottom: 1px solid #eaeaea;
            }
        }
        
        @media (max-width: 992px) {
            .bookings-table th:nth-child(5),
            .bookings-table td:nth-child(5),
            .bookings-table th:nth-child(6),
            .bookings-table td:nth-child(6) {
                display: none;
            }
        }
        
        @media (max-width: 768px) {
            .header-top {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .filter-bar {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .section-title {
                font-size: 1.4rem;
            }
            
            .bookings-table th:nth-child(4),
            .bookings-table td:nth-child(4),
            .bookings-table th:nth-child(8),
            .bookings-table td:nth-child(8) {
                display: none;
            }
            
            .receipt-details, .completion-details {
                grid-template-columns: 1fr;
            }
            
            .payment-method {
                flex-direction: column;
            }
        }
        
        @media (max-width: 576px) {
            .date-filter {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .date-btn {
                width: 100%;
            }
            
            .action-cell {
                flex-direction: column;
            }
        }
        
        /* Animation for new bookings */
        @keyframes highlight {
            0% { background-color: rgba(37, 117, 252, 0.1); }
            100% { background-color: transparent; }
        }
        
        .new-booking {
            animation: highlight 2s ease;
        }
        
        /* Notification styles */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 25px;
            border-radius: 8px;
            color: white;
            font-weight: 600;
            z-index: 2000;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            opacity: 0;
            transform: translateY(-20px);
            transition: all 0.3s ease;
        }
        
        .notification.show {
            opacity: 1;
            transform: translateY(0);
        }
        
        .notification.success {
            background: linear-gradient(135deg, #28a745, #218838);
        }
        
        .notification.error {
            background: linear-gradient(135deg, #dc3545, #c82333);
        }
        
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1500;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
        }
        
        .loading-overlay.active {
            opacity: 1;
            pointer-events: all;
        }
        
        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .btn-submit:disabled {
            background: #6c757d !important;
            cursor: not-allowed;
            transform: none !important;
            box-shadow: none !important;
        }
        
        .security-info {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid var(--success);
            font-size: 14px;
            color: var(--gray);
        }
        
        .security-info i {
            margin-right: 8px;
            color: var(--success);
        }
        
        /* DJ toggle switch */
        .dj-toggle {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }
        
        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        
        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .slider {
            background-color: var(--success);
        }
        
        input:checked + .slider:before {
            transform: translateX(26px);
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="header-top">
                <div class="logo">
                    <i class="fas fa-calendar-check"></i>
                    <div>
                        <h1>BTS 2.0 Booking System</h1>
                        <span>Secure & Advanced Booking Management</span>
                    </div>
                </div>
                <div class="header-actions">
                    <button class="btn btn-primary" id="refreshBtn">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                  
                    <a class="btn btn-primary" href="/btsapp/dashboard.php">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                
                </div>
            </div>
            
            <div class="tabs">
                <button class="tab-btn active" id="newBookingTab">
                    <i class="fas fa-plus-circle"></i> New Booking
                </button>
                <button class="tab-btn" id="allBookingsTab">
                    <i class="fas fa-list"></i> All Bookings
                </button>
            </div>
        </header>
        
        <div class="main-content">
            <section class="booking-form-section" id="formSection">
                <h2 class="section-title">
                    <i class="fas fa-book"></i> New Booking Form
                </h2>
                
                <form id="bookingForm">
                    <input type="hidden" id="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="customerName">Full Name *</label>
                            <input type="text" id="customerName" class="form-control" placeholder="Enter full name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="contactNumber">Contact Number *</label>
                            <input type="tel" id="contactNumber" class="form-control" placeholder="Enter phone number" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="bookingDate">Booking Date *</label>
                            <input type="date" id="bookingDate" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="bookingTime">Booking Time *</label>
                            <input type="time" id="bookingTime" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="totalPerson">Total Persons *</label>
                            <input type="text" id="totalPerson" class="form-control" placeholder="e.g., 10-12 or 4" required>
                        </div>

                        <div class="form-group">
                            <label for="eventType">Event Type *</label>
                            <select id="eventType" class="form-control" required>
                                <option value="">-- Select Event Type --</option>
                                <option value="Ring Ceremony">Ring Ceremony</option>
                                <option value="Birthday">Birthday</option>
                                <option value="Corporate Party">Corporate Party</option>
                                <option value="Farewell">Farewell</option>
                                <option value="Get Together">Get Together</option>
                                <option value="Anniversary">Anniversary</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="ISDJ">Is DJ *</label>
                            <select id="ISDJ" class="form-control" required>
                                <option value="">-- Select Option --</option>
                                <option value="Yes">Yes</option>
                                <option value="No">No</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="advancePaid">Advance Paid (₹) *</label>
                            <input type="number" id="advancePaid" class="form-control" placeholder="Enter amount" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="takenBy">Booking Taken By *</label>
                            <input type="text" id="takenBy" class="form-control" placeholder="Staff member name" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Payment Method *</label>
                            <div class="payment-method">
                                <div class="payment-option active" data-value="cash">
                                    <i class="fas fa-money-bill-wave"></i>
                                    <div>Cash</div>
                                </div>
                                <div class="payment-option" data-value="upi">
                                    <i class="fas fa-mobile-alt"></i>
                                    <div>UPI</div>
                                </div>
                            </div>
                            <input type="hidden" id="paymentMethod" value="cash" required>
                        </div>

                        <div class="form-group">
                            <label for="bookingType">Booking Type *</label>
                            <select id="bookingType" class="form-control" required>
                                <option value="">-- Select Option --</option>
                                <option value="Menu">Menu</option>
                                <option value="Buffet">Buffet</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="specialRequest">Special Requests</label>
                        <textarea id="specialRequest" class="form-control" rows="3" placeholder="Any special requirements..."></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn-submit" id="submitBtn">
                            <i class="fas fa-check-circle"></i> Confirm Booking
                        </button>
                        <button type="reset" class="btn-reset" id="resetBtn">
                            <i class="fas fa-redo"></i> Reset Form
                        </button>
                    </div>
                    
                    <div class="security-info">
                        <i class="fas fa-shield-alt"></i> 
                        This form uses advanced security measures including CSRF protection, input validation, and secure data handling.
                    </div>
                </form>
            </section>
            
            <section class="bookings-section" id="bookingsSection" style="display:none">
                <h2 class="section-title">
                    <i class="fas fa-list"></i> Bookings Management
                </h2>
                
                <div class="filter-bar">
                    <div class="date-filter">
                        <button class="date-btn" data-filter="today">Today</button>
                        <button class="date-btn" data-filter="tomorrow">Tomorrow</button>
                        <button class="date-btn" data-filter="thisWeek">This Week</button>
                        <button class="date-btn" data-filter="nextWeek">Next Week</button>
                        <button class="date-btn active" data-filter="next30days">Next 30 Days</button>
                        <button class="date-btn" data-filter="all">All Dates</button>
                    </div>
                    
                    <div class="filter-group">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" id="searchInput" placeholder="Search bookings...">
                        </div>
                        <button class="btn btn-primary" id="filterBtn">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                    </div>
                </div>
                
                <div class="bookings-table-container">
                    <table class="bookings-table" id="bookingsTable">
                        <thead>
                            <tr>
                                <th>Booking ID</th>
                                <th>Booked Date</th>
                                <th>Name</th>
                                <th>Party Date & Time</th>
                                <th>Contact</th>
                                <th>Persons</th>
                                <th>Status</th>
                                <th>Event Type</th>
                                <th>Booking Type</th>
                                <th>DJ</th>
                                <th>Advance</th>
                                <?php if($role == 'admin' || $role == 'super_admin') echo  '<th>Final</th>'  ?>
                                <th>Adv. Payment</th>
                                <?php if($role == 'admin' || $role == 'super_admin') echo  '<th>Final Payment</th>'  ?>
                                
                                <th>Taken By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="bookingsTableBody">
                            <!-- Bookings will be populated here dynamically -->
                        </tbody>
                    </table>
                    
                    <div class="no-bookings" id="noBookingsMessage">
                        <i class="fas fa-calendar-times"></i>
                        <h3>No bookings found</h3>
                        <p>Create a new booking using the form</p>
                    </div>
                </div>
                
                <div class="pagination" id="pagination">
                    <!-- <button class="page-btn active">1</button>
                    <button class="page-btn">2</button>
                    <button class="page-btn">3</button>
                    <button class="page-btn">Next</button> -->
                </div>
            </section>
        </div>
        
        <footer>
            <p>BTS 2.0 Booking System &copy; 2023 | All Rights Reserved</p>
            <p>Contact: +91 98765 43210 | Email: bookings@bts2.com</p>
        </footer>
    </div>
    
    <!-- Receipt Preview Modal -->
    <!-- Your receipt HTML -->
<div class="receipt-preview" id="receiptPreview">
  <div class="receipt-content">
    <div class="receipt-header">
      <i class="fas fa-calendar-alt receipt-logo"></i>
      <h2>Booking Confirmation</h2>
      <p class="sub-heading">BTS 2.0 Premium Experience</p>
    </div>

    <div class="receipt-body">
      <div class="receipt-details">
        <div class="details-column">
          <div class="receipt-item">
            <span class="receipt-label">Booking ID</span>
            <span class="receipt-value" id="receiptId">BTS-001</span>
          </div>
          <div class="receipt-item">
            <span class="receipt-label">Customer Name</span>
            <span class="receipt-value" id="receiptName">John Smith</span>
          </div>
          <div class="receipt-item">
            <span class="receipt-label">Contact Number</span>
            <span class="receipt-value" id="receiptContact">+1 555-123-4567</span>
          </div>
          <div class="receipt-item">
            <span class="receipt-label">Event Type</span>
            <span class="receipt-value" id="receiptEventType">Birthday</span>
          </div>
          <div class="receipt-item">
            <span class="receipt-label">Booking Type</span>
            <span class="receipt-value" id="receiptBookingType">Buffet</span>
          </div>
        </div>

        <div class="details-column">
          <div class="receipt-item">
            <span class="receipt-label">Booking Date</span>
            <span class="receipt-value" id="receiptDate">15 July 2023</span>
          </div>
          <div class="receipt-item">
            <span class="receipt-label">Booking Time</span>
            <span class="receipt-value" id="receiptTime">7:30 PM</span>
          </div>
          <div class="receipt-item">
            <span class="receipt-label">Total Persons</span>
            <span class="receipt-value" id="receiptPersons">8-10</span>
          </div>
          <div class="receipt-item">
            <span class="receipt-label">DJ Required</span>
            <span class="receipt-value" id="receiptIsDj">Yes</span>
          </div>
          <div class="receipt-item">
            <span class="receipt-label">Taken By</span>
            <span class="receipt-value" id="receiptTakenBy">Staff Name</span>
          </div>
        </div>
      </div>

      <div class="receipt-total">
        <div class="total-label">Advance Paid</div>
        <div class="amount" id="receiptAdvance">₹ 2,500</div>
        <div class="payment-method" id="receiptPaymentMethod">Payment Method: Cash</div>
      </div>

      <div class="receipt-item full-width">
        <span class="receipt-label">Special Requests</span>
        <span class="receipt-value" id="receiptSpecialRequest">None</span>
      </div>

      <div class="receipt-note">
        <i class="fas fa-exclamation-circle"></i>
        <span>Please note: Advance payments are non-refundable</span>
      </div>

      <div class="receipt-footer">
        <p>Thank you for choosing <strong>BTS 2.0</strong>! We look forward to serving you.</p>
        <p>For any inquiries, contact <strong>+91 98765 43210</strong></p>
      </div>
    </div>

    <div class="receipt-actions no-print">
      <button class="btn-print" id="printReceiptBtn">
        <i class="fas fa-print"></i> Print Receipt
      </button>
      <button class="btn-close" id="closeReceiptBtn">
        <i class="fas fa-times"></i> Close
      </button>
    </div>
  </div>
</div>

    
    <!-- Booking Completion Modal -->
    <div class="completion-modal" id="completionModal">
        <div class="completion-content">
            <div class="completion-header">
                <i class="fas fa-check-circle completion-logo"></i>
                <h2>Complete Booking</h2>
                <p>Finalize booking details and mark as completed</p>
            </div>
            
            <div class="completion-body">
                <form id="completionForm">
                    <input type="hidden" id="completionBookingId" value="">
                    
                    <div class="form-group">
                        <label for="finalPaid">Final Amount Paid (₹) *</label>
                        <input type="number" id="finalPaid" class="form-control" placeholder="Enter final amount" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="finalTakenBy">Final Taken By *</label>
                        <input type="text" id="finalTakenBy" class="form-control" placeholder="Staff member name" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Final Payment Method *</label>
                        <div class="payment-method">
                            <div class="payment-option active" data-value="cash">
                                <i class="fas fa-money-bill-wave"></i>
                                <div>Cash</div>
                            </div>
                            <div class="payment-option" data-value="upi">
                                <i class="fas fa-mobile-alt"></i>
                                <div>UPI</div>
                            </div>
                        </div>
                        <input type="hidden" id="finalPaymentMethod" value="cash" required>
                    </div>
                    
                    <div class="form-group dj-toggle">
                        <label>DJ Service Provided:</label>
                        <label class="switch">
                            <input type="checkbox" id="isDjToggle">
                            <span class="slider"></span>
                        </label>
                        <span id="djStatusLabel">No</span>
                    </div>
                    
                    <div class="completion-actions">
                        <button type="submit" class="btn-complete" id="completeBookingBtn">
                            <i class="fas fa-check-circle"></i> Mark as Completed
                        </button>
                        <button type="button" class="btn-close" id="closeCompletionBtn">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Notification element -->
    <div id="notification" class="notification"></div>
    
    <!-- Loading overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
    </div>
    
    <script>
        // Initialize variables
        let bookings = [];
        let currentFilter = 'next30days';
        let currentBookingId = null;
        const userRole = '<?php echo $role; ?>';
        
        // DOM Elements
        const form = document.getElementById('bookingForm');
        const submitBtn = document.getElementById('submitBtn');
        const bookingsTableBody = document.getElementById('bookingsTableBody');
        const noBookingsMessage = document.getElementById('noBookingsMessage');
        const bookingsTable = document.getElementById('bookingsTable');
        const pagination = document.getElementById('pagination');
        const dateFilterButtons = document.querySelectorAll('.date-btn');
        const searchInput = document.getElementById('searchInput');
        const filterBtn = document.getElementById('filterBtn');
        const refreshBtn = document.getElementById('refreshBtn');
        const notification = document.getElementById('notification');
        const loadingOverlay = document.getElementById('loadingOverlay');
        const paymentOptions = document.querySelectorAll('.payment-option');
        const paymentMethodInput = document.getElementById('paymentMethod');
        const completionForm = document.getElementById('completionForm');
        const djToggle = document.getElementById('isDjToggle');
        const djStatusLabel = document.getElementById('djStatusLabel');
        
        // Set default date to today
        document.getElementById('bookingDate').valueAsDate = new Date();
        
        // Set default time to next hour
        const setDefaultTime = () => {
            const now = new Date();
            const nextHour = new Date(now.getTime() + 60 * 60 * 1000);
            document.getElementById('bookingTime').value = 
                `${String(nextHour.getHours()).padStart(2, '0')}:${String(nextHour.getMinutes()).padStart(2, '0')}`;
        };
        
        setDefaultTime();
        
        // Format date
        const formatDate = (dateString) => {
            const options = { year: 'numeric', month: 'short', day: 'numeric' };
            return new Date(dateString).toLocaleDateString('en-US', options);
        };
        
        // Format time
        const formatTime = (timeString) => {
            const [hours, minutes] = timeString.split(':');
            const hour = parseInt(hours);
            return hour > 12 ? `${hour - 12}:${minutes} PM` : `${hour}:${minutes} AM`;
        };
        
        // Format currency
        const formatCurrency = (amount) => {
            return '₹ ' + parseFloat(amount).toLocaleString('en-IN');
        };
        
        // Get status badge
        const getStatusBadge = (status) => {
            switch(status) {
                case 'pending':
                    return `<span class="status-badge status-pending">Pending</span>`;
                case 'confirmed':
                    return `<span class="status-badge status-confirmed">Confirmed</span>`;
                case 'completed':
                    return `<span class="status-badge status-completed">Completed</span>`;
                case 'cancelled':
                    return `<span class="status-badge status-cancelled">Cancelled</span>`;
                default:
                    return `<span class="status-badge">${status}</span>`;
            }
        };
        
        // Show notification
        const showNotification = (message, isSuccess = true) => {
            notification.textContent = message;
            notification.className = isSuccess ? 'notification success' : 'notification error';
            notification.classList.add('show');
            
            setTimeout(() => {
                notification.classList.remove('show');
            }, 3000);
        };
        
        // Show loading spinner
        const showLoading = (show) => {
            if (show) {
                loadingOverlay.classList.add('active');
            } else {
                loadingOverlay.classList.remove('active');
            }
        };
        
        // Create new booking
        const createBooking = (e) => {
            e.preventDefault();
            
            // Get form values
            const formData = new FormData();
            formData.append('action', 'create_booking');
            formData.append('customerName', document.getElementById('customerName').value);
            formData.append('contactNumber', document.getElementById('contactNumber').value);
            formData.append('bookingDate', document.getElementById('bookingDate').value);
            formData.append('bookingTime', document.getElementById('bookingTime').value);
            formData.append('totalPerson', document.getElementById('totalPerson').value);
            formData.append('advancePaid', document.getElementById('advancePaid').value);
            formData.append('takenBy', document.getElementById('takenBy').value);
            formData.append('specialRequest', document.getElementById('specialRequest').value);
            formData.append('paymentMethod', document.getElementById('paymentMethod').value);
            formData.append('eventType', document.getElementById('eventType').value);
            formData.append('ISDJ', document.getElementById('ISDJ').value);
            formData.append('bookingType', document.getElementById('bookingType').value);
            formData.append('csrf_token', document.getElementById('csrf_token').value);
            
            // Disable submit button
            submitBtn.disabled = true;
            showLoading(true);
            
            // Send to server
            fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    // Reset form
                    form.reset();
                    setDefaultTime();
                    document.getElementById('bookingDate').valueAsDate = new Date();
                    
                    // Show success message
                    showNotification(`${data.message} ID: ${data.booking_id}`, true);
                    
                    // Add new booking to table with highlight
                    if (data.booking_data) {
                        bookings.unshift(data.booking_data);
                        renderBookings();
                        const newRow = document.querySelector(`tr[data-id="${data.booking_data.id}"]`);
                        if (newRow) {
                            newRow.classList.add('new-booking');
                        }
                    }
                    
                    // Switch to bookings tab
                    showBookingsSection();
                } else {
                    throw new Error(data.message);
                }
            })
            .catch(error => {
                showNotification(`Error: ${error.message}`, false);
            })
            .finally(() => {
                submitBtn.disabled = false;
                showLoading(false);
            });
        };
        
        // Load bookings from server
        const loadBookings = () => {
            showLoading(true);
            
            const formData = new FormData();
            formData.append('action', 'get_bookings');
            formData.append('filter', currentFilter);
            formData.append('search', searchInput.value);
            formData.append('csrf_token', document.getElementById('csrf_token').value);
            
            fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    bookings = data.bookings;
                    renderBookings();
                } else {
                    throw new Error(data.message);
                }
            })
            .catch(error => {
                showNotification('Failed to load bookings: ' + error.message, false);
            })
            .finally(() => {
                showLoading(false);
            });
        };
        
        // Delete booking
        const deleteBooking = (id) => {
            if (confirm('Are you sure you want to delete this booking?')) {
                showLoading(true);
                
                const formData = new FormData();
                formData.append('action', 'delete_booking');
                formData.append('id', id);
                formData.append('csrf_token', document.getElementById('csrf_token').value);
                
                fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        showNotification(data.message, true);
                        loadBookings();
                    } else {
                        throw new Error(data.message);
                    }
                })
                .catch(error => {
                    showNotification('Failed to delete booking: ' + error.message, false);
                })
                .finally(() => {
                    showLoading(false);
                });
            }
        };
        
        // Show bookings section
        const showBookingsSection = () => {
            document.getElementById('formSection').style.display = 'none';
            document.getElementById('bookingsSection').style.display = 'block';
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.getElementById('allBookingsTab').classList.add('active');
            loadBookings();
        };
        
        // Render bookings table
        const renderBookings = () => {
            // Clear table body
            bookingsTableBody.innerHTML = '';
            
            // Show/hide no bookings message
            if (bookings.length === 0) {
                noBookingsMessage.style.display = 'block';
                bookingsTable.style.display = 'none';
                pagination.style.display = 'none';
                return;
            }
            
            noBookingsMessage.style.display = 'none';
            bookingsTable.style.display = 'table';
            pagination.style.display = 'flex';
            
            // Populate table
            bookings.forEach(booking => {
                const row = document.createElement('tr');
                row.setAttribute('data-id', booking.id);
                
                // Format booking type badge
                const bookingTypeBadge = booking.booking_type === 'Menu' ? 
                    '<span class="type-badge badge-menu">Menu</span>' : 
                    '<span class="type-badge badge-buffet">Buffet</span>';
                
                // Format DJ badge
                const djBadge = booking.is_dj === 1 ? 
                    '<span class="type-badge badge-yes">Yes</span>' : 
                    '<span class="type-badge badge-no">No</span>';
                
                // Format status
                const statusBadge = getStatusBadge(booking.status);
        
                let actionButtons = `
                    <button class="action-btn btn-view" data-id="${booking.id}">
                        <i class="fas fa-receipt"></i> Receipt
                    </button>
                `;
                
                if (booking.status !== 'completed' && booking.status !== 'cancelled') {
                    actionButtons += `
                        <button class="action-btn btn-done" data-id="${booking.id}">
                            <i class="fas fa-check"></i> Done
                        </button>
                    `;
                }
                
                if (userRole === 'super_admin') {
                    actionButtons += `
                        <button class="action-btn btn-delete" data-id="${booking.id}">
                            <i class="fas fa-trash"></i>
                        </button>
                    `;
                }
                
                row.innerHTML = `
                    <td>${booking.booking_id}</td>
                    <td style="color: darkblue; font-weight: bold;">
    ${formatDate(booking.created_at)}
</td>
                    <td>${booking.customer_name}</td>
                    <td style="color: darkgreen; font-weight: bold;">
    ${formatDate(booking.booking_date)}, ${formatTime(booking.booking_time)}
</td>

                    <td>${booking.contact_number}</td>
                    <td>${booking.total_persons}</td>
                    <td>${statusBadge}</td>
                    <td>${booking.event_type}</td>
                    <td>${bookingTypeBadge}</td>
                    <td>${djBadge}</td>
                    <td>${formatCurrency(booking.advance_paid)}</td>
                    ${(userRole === 'admin' || userRole === 'super_admin') ? 
                        `<td>${formatCurrency(booking.final_paid)}</td>` : ''}
                    <td><span class="payment-badge ${booking.advance_payment_method === 'cash' ? 'payment-cash' : 'payment-upi'}">${booking.advance_payment_method.toUpperCase()}</span></td>
                    <td><span class="payment-badge ${booking.advance_payment_method === 'cash' ? 'payment-cash' : 'payment-upi'}">${booking.final_payment_method}</span></td>
                    <td>${booking.final_taken_by}</td>
                    <td class="action-cell">
                        ${actionButtons}
                    </td>
                `;
                
                bookingsTableBody.appendChild(row);
            });
            
            // Add event listeners to action buttons
            document.querySelectorAll('.btn-view').forEach(btn => {
                btn.addEventListener('click', () => {
                    const bookingId = btn.getAttribute('data-id');
                    const booking = bookings.find(b => b.id == bookingId);
                    if (booking) showReceipt(booking);
                });
            });
            
            document.querySelectorAll('.btn-done').forEach(btn => {
                btn.addEventListener('click', () => {
                    const bookingId = btn.getAttribute('data-id');
                    const booking = bookings.find(b => b.id == bookingId);
                    if (booking) showCompletionForm(booking);
                });
            });
            
            document.querySelectorAll('.btn-delete').forEach(btn => {
                btn.addEventListener('click', () => {
                    const bookingId = btn.getAttribute('data-id');
                    if (userRole == 'super_admin') {
                        deleteBooking(bookingId);
                    } else {
                        showNotification('You are not authorized to delete bookings', false);
                    }
                });
            });
        };
        
        // Show completion form
        const showCompletionForm = (booking) => {
            document.getElementById('completionBookingId').value = booking.id;
            document.getElementById('finalPaid').value = '';
            document.getElementById('finalTakenBy').value = '';
            
            // Set DJ toggle based on current value
            const isDj = booking.is_dj === 1;
            document.getElementById('isDjToggle').checked = isDj;
            djStatusLabel.textContent = isDj ? 'Yes' : 'No';
            
            // Reset payment method
            document.querySelectorAll('.completion-modal .payment-option').forEach(opt => opt.classList.remove('active'));
            document.querySelector('.completion-modal .payment-option[data-value="cash"]').classList.add('active');
            document.getElementById('finalPaymentMethod').value = 'cash';
            
            document.getElementById('completionModal').classList.add('active');
        };
        
        // Complete booking
        const completeBooking = (e) => {
            e.preventDefault();
            
            const formData = new FormData();
            formData.append('action', 'complete_booking');
            formData.append('id', document.getElementById('completionBookingId').value);
            formData.append('finalPaid', document.getElementById('finalPaid').value);
            formData.append('finalTakenBy', document.getElementById('finalTakenBy').value);
            formData.append('paymentMethod', document.getElementById('finalPaymentMethod').value);
            formData.append('isDj', document.getElementById('isDjToggle').checked ? 1 : 0);
            formData.append('csrf_token', document.getElementById('csrf_token').value);
            
            // Disable submit button
            const completeBtn = document.getElementById('completeBookingBtn');
            completeBtn.disabled = true;
            showLoading(true);
            
            // Send to server
            fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    // Close modal
                    document.getElementById('completionModal').classList.remove('active');
                    
                    // Show success message
                    showNotification(data.message, true);
                    
                    // Reload bookings
                    loadBookings();
                } else {
                    throw new Error(data.message);
                }
            })
            .catch(error => {
                showNotification(`Error: ${error.message}`, false);
            })
            .finally(() => {
                completeBtn.disabled = false;
                showLoading(false);
            });
        };
        
        // // Show receipt
        // const showReceipt = (booking) => {
        //     document.getElementById('receiptId').textContent = booking.booking_id;
        //     document.getElementById('receiptName').textContent = booking.customer_name;
        //     document.getElementById('receiptContact').textContent = booking.contact_number;
        //     document.getElementById('receiptDate').textContent = formatDate(booking.booking_date);
        //     document.getElementById('receiptTime').textContent = formatTime(booking.booking_time);
        //     document.getElementById('receiptPersons').textContent = booking.total_persons;
        //     document.getElementById('receiptAdvance').textContent = formatCurrency(booking.advance_paid);
        //     document.getElementById('receiptPaymentMethod').textContent = `Payment Method: ${booking.advance_payment_method.toUpperCase()}`;
        //     document.getElementById('receiptEventType').textContent = booking.event_type;
        //     document.getElementById('receiptBookingType').textContent = booking.booking_type;
        //     document.getElementById('receiptIsDj').textContent = booking.is_dj === 1 ? 'Yes' : 'No';
        //     document.getElementById('receiptTakenBy').textContent = booking.advance_taken_by;
        //     document.getElementById('receiptSpecialRequest').textContent = booking.special_requests || 'None';
            
        //     document.getElementById('receiptPreview').classList.add('active');
        //     currentBookingId = booking.id;
        // };
        
        // Show receipt
const showReceipt = (booking) => {
    const setText = (id, value, prefix = '') => {
        document.getElementById(id).textContent = value ? `${prefix}${value}` : 'N/A';
    };

    const isCompleted = booking.status?.toLowerCase() === 'completed';

    // Always shown details
    setText('receiptId', booking.booking_id);
    setText('receiptName', booking.customer_name);
    setText('receiptContact', booking.contact_number);
    setText('receiptDate', formatDate(booking.booking_date));
    setText('receiptTime', formatTime(booking.booking_time));
    setText('receiptPersons', booking.total_persons);
    setText('receiptAdvance', formatCurrency(booking.advance_paid));
    setText('receiptPaymentMethod', booking.advance_payment_method?.toUpperCase(), 'Payment Method: ');
    setText('receiptEventType', booking.event_type);
    setText('receiptBookingType', booking.booking_type);
    setText('receiptIsDj', booking.is_dj === 1 ? 'Yes' : 'No');
    setText('receiptTakenBy', booking.advance_taken_by);
    setText('receiptSpecialRequest', booking.special_requests || 'None');

    if (isCompleted) {
        // Only for completed bookings
        setText('receiptFinalPaid', formatCurrency(booking.final_paid));
        setText('receiptFinalPaymentMethod', booking.final_payment_method?.toUpperCase(), 'Final Payment Method: ');
        setText('receiptFinalTakenBy', booking.final_taken_by);
    } else {
        // Hide final details if not completed
        document.getElementById('receiptFinalPaid').textContent = '';
        document.getElementById('receiptFinalPaymentMethod').textContent = '';
        document.getElementById('receiptFinalTakenBy').textContent = '';
    }

    // Show the receipt
    document.getElementById('receiptPreview').classList.add('active');
    currentBookingId = booking.booking_id; // Use booking_id for file naming
};

        
        // Print receipt
        const printReceipt = () => {
            const receiptContent = document.querySelector('.receipt-content');
            
            // Use html2canvas to capture the receipt content
            html2canvas(receiptContent).then(canvas => {
                const imgData = canvas.toDataURL('image/png');
                const pdf = new jspdf.jsPDF({
                    orientation: 'portrait',
                    unit: 'mm',
                    format: [210, 297] // A4 size
                });
                
                const imgWidth = 190;
                const pageHeight = 297;
                const imgHeight = canvas.height * imgWidth / canvas.width;
                let heightLeft = imgHeight;
                
                let position = 10;
                
                pdf.addImage(imgData, 'PNG', 10, position, imgWidth, imgHeight);
                heightLeft -= pageHeight;
                
                while (heightLeft >= 0) {
                    position = heightLeft - imgHeight;
                    pdf.addPage();
                    pdf.addImage(imgData, 'PNG', 10, position, imgWidth, imgHeight);
                    heightLeft -= pageHeight;
                }
                
                pdf.save(`BTS-Booking-${currentBookingId}.pdf`);
            });
        };
        
        // Initialize event listeners
        const initEventListeners = () => {
            // Form submission
            form.addEventListener('submit', createBooking);
            
            // Completion form submission
            completionForm.addEventListener('submit', completeBooking);
            
            // DJ toggle change
            djToggle.addEventListener('change', function() {
                djStatusLabel.textContent = this.checked ? 'Yes' : 'No';
            });
            
            // Date filter buttons
            dateFilterButtons.forEach(button => {
                button.addEventListener('click', () => {
                    dateFilterButtons.forEach(btn => btn.classList.remove('active'));
                    button.classList.add('active');
                    currentFilter = button.dataset.filter;
                    loadBookings();
                });
            });
            
            // Payment method selection (both forms)
            document.querySelectorAll('.payment-option').forEach(option => {
                option.addEventListener('click', () => {
                    // Find the form this option belongs to
                    const form = option.closest('.payment-method');
                    const methodInput = form.nextElementSibling;
                    
                    // Remove active class from all options in this group
                    form.querySelectorAll('.payment-option').forEach(opt => opt.classList.remove('active'));
                    
                    // Add active class to clicked option
                    option.classList.add('active');
                    methodInput.value = option.dataset.value;
                });
            });
            
            // Search and filter
            filterBtn.addEventListener('click', loadBookings);
            searchInput.addEventListener('keyup', loadBookings);
            
            // Refresh button
            refreshBtn.addEventListener('click', loadBookings);
            
            // Receipt actions
            document.getElementById('closeReceiptBtn').addEventListener('click', () => {
                document.getElementById('receiptPreview').classList.remove('active');
            });
            
            document.getElementById('printReceiptBtn').addEventListener('click', printReceipt);
            
            // Close receipt when clicking outside
            document.getElementById('receiptPreview').addEventListener('click', (e) => {
                if (e.target === document.getElementById('receiptPreview')) {
                    document.getElementById('receiptPreview').classList.remove('active');
                }
            });
            
            // Completion modal actions
            document.getElementById('closeCompletionBtn').addEventListener('click', () => {
                document.getElementById('completionModal').classList.remove('active');
            });
            
            document.getElementById('completionModal').addEventListener('click', (e) => {
                if (e.target === document.getElementById('completionModal')) {
                    document.getElementById('completionModal').classList.remove('active');
                }
            });
            
            // Tab navigation
            document.getElementById('newBookingTab').addEventListener('click', () => {
                document.getElementById('formSection').style.display = 'block';
                document.getElementById('bookingsSection').style.display = 'none';
                document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
                document.getElementById('newBookingTab').classList.add('active');
            });
            
            document.getElementById('allBookingsTab').addEventListener('click', showBookingsSection);
        };
        
        // Initialize the application
        const init = () => {
            initEventListeners();
            loadBookings(); // Load bookings from server on start
        };
        
        // Start the application
        init();
    </script>
</body>
</html>