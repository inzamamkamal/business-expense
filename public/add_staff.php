<?php
// Fixed PDO implementation
include '../includes/session.php';
require '../config/db.php';
require '../includes/functions.php';


if (!isset($_SESSION['username']) || !isset($_SESSION['role']) || 
    !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    session_destroy();
    header('Location: ../index.php');
    exit();
}

// Initialize variables
$id = $full_name = $position = $contact_number = $aadhar = $address = $hire_date = $monthly_salary = $status = $image = "";
$edit_mode = false;
$success_message = "";

// Define upload directory and create if doesn't exist
$uploadDirectory = '../uploads/staff/';
if (!file_exists($uploadDirectory)) {
    mkdir($uploadDirectory, 0777, true);
}

// Handle insert/update
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = $_POST['id'] ?? '';
    $full_name = $_POST['full_name'];
    $position = $_POST['position'];
    $contact_number = $_POST['contact_number'];
    $aadhar = $_POST['aadhar'];
    $address = $_POST['address'];
    $hire_date = $_POST['hire_date'];
    $monthly_salary = $_POST['monthly_salary'];
    $status = $_POST['status'];
    
    // Handle file upload
    $image = $_POST['old_image'] ?? ''; // Keep existing image if exists
    $uploadOk = true;
    $errorMessage = "";
    
    if (isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
        $target_file = $uploadDirectory . basename($_FILES['image']['name']);
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        
        // Check if image file is actual image
        $check = getimagesize($_FILES['image']['tmp_name']);
        if ($check === false) {
            $uploadOk = false;
            $errorMessage = "File is not an image.";
        }
        
        // Check file size (max 2MB)
        if ($_FILES['image']['size'] > 2000000) {
            $uploadOk = false;
            $errorMessage = "Sorry, your file is too large (max 2MB).";
        }
        
        // Allow certain file formats
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($imageFileType, $allowedTypes)) {
            $uploadOk = false;
            $errorMessage = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
        }
        
        // Generate unique filename
        $newFilename = uniqid('staff_', true) . '.' . $imageFileType;
        $target_file = $uploadDirectory . $newFilename;
        
        if ($uploadOk) {
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                // Delete old image if exists and new one uploaded successfully
                if (!empty($image)) {
                    $oldImagePath = $uploadDirectory . $image;
                    if (file_exists($oldImagePath)) {
                        unlink($oldImagePath);
                    }
                }
                $image = $newFilename;
            } else {
                $uploadOk = false;
                $errorMessage = "Sorry, there was an error uploading your file.";
            }
        }
    } else if (empty($image) && !$id) {
        // For new entries, image is required
        $uploadOk = false;
        $errorMessage = "Staff photo is required.";
    }
    
    // Validate Aadhar number
    if (!empty($aadhar) && !preg_match('/^\d{12}$/', $aadhar)) {
        $uploadOk = false;
        $errorMessage = "Aadhar number must be exactly 12 digits.";
    }
    
    if ($uploadOk) {
        try {
            if ($id) {
                // Update using proper PDO
                $stmt = $pdo->prepare("UPDATE staff SET full_name=:full_name, position=:position, contact_number=:contact_number, aadhar=:aadhar, address=:address, hire_date=:hire_date, monthly_salary=:monthly_salary, status=:status, image=:image WHERE id=:id");
                $stmt->execute([
                    'full_name' => $full_name,
                    'position' => $position,
                    'contact_number' => $contact_number,
                    'aadhar' => $aadhar,
                    'address' => $address,
                    'hire_date' => $hire_date,
                    'monthly_salary' => $monthly_salary,
                    'status' => $status,
                    'image' => $image,
                    'id' => $id
                ]);
                $action = "updated";
            } else {
                // Insert using proper PDO
                $stmt = $pdo->prepare("INSERT INTO staff (full_name, position, contact_number, aadhar, address, hire_date, monthly_salary, status, image) VALUES (:full_name, :position, :contact_number, :aadhar, :address, :hire_date, :monthly_salary, :status, :image)");
                $stmt->execute([
                    'full_name' => $full_name,
                    'position' => $position,
                    'contact_number' => $contact_number,
                    'aadhar' => $aadhar,
                    'address' => $address,
                    'hire_date' => $hire_date,
                    'monthly_salary' => $monthly_salary,
                    'status' => $status,
                    'image' => $image
                ]);
                $action = "added";
            }
            
            $success_message = "Staff member $action successfully!";
            
            // Reset form only for new entries
            if (!$id) {
                $id = $full_name = $position = $contact_number = $aadhar = $address = $hire_date = $monthly_salary = $status = $image = "";
            }
        } catch (PDOException $e) {
            $success_message = "Error: " . $e->getMessage();
        }
    } else {
        $success_message = "Error: " . $errorMessage;
    }
}

// Handle edit request
if (isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM staff WHERE id = :id");
    $stmt->execute(['id' => $edit_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($row) {
        $id = $row['id'];
        $full_name = $row['full_name'];
        $position = $row['position'];
        $contact_number = $row['contact_number'];
        $aadhar = $row['aadhar'];
        $address = $row['address'];
        $hire_date = $row['hire_date'];
        $monthly_salary = $row['monthly_salary'];
        $status = $row['status'];
        $image = $row['image'];
        $edit_mode = true;
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $delete_id = $_GET['delete'];
    try {
        // Get image path first
        $stmt = $pdo->prepare("SELECT image FROM staff WHERE id = :id");
        $stmt->execute(['id' => $delete_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            $imagePath = $uploadDirectory . $row['image'];
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }
        }
        
        $stmt = $pdo->prepare("DELETE FROM staff WHERE id = :id");
        $stmt->execute(['id' => $delete_id]);
        $success_message = "Staff member deleted successfully!";
    } catch (PDOException $e) {
        $success_message = "Error deleting staff member: " . $e->getMessage();
    }
}

// Get all staff
$stmt = $pdo->query("SELECT * FROM staff ORDER BY id DESC");
$staff_members = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BTS Staff Master</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        :root {
            --primary: #3498db;
            --primary-dark: #2980b9;
            --secondary: #2c3e50;
            --success: #27ae60;
            --warning: #f39c12;
            --danger: #e74c3c;
            --light: #f8f9fa;
            --dark: #343a40;
            --gray: #6c757d;
            --light-gray: #e9ecef;
            --aadhar-color: #1a5276;
            --view-color: #9b59b6;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e4eff8 100%);
            color: #333;
            padding: 20px;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        header {
            text-align: center;
            margin-bottom: 30px;
            padding: 20px;
        }
        
        .logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .logo i {
            font-size: 2.5rem;
            color: var(--primary);
        }
        
        h1 {
            font-size: 2.2rem;
            color: var(--secondary);
            margin-bottom: 10px;
        }
        
        .subtitle {
            color: #7f8c8d;
            font-size: 1.1rem;
        }
        
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            padding: 25px;
            margin-bottom: 30px;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .card-title {
            font-size: 1.4rem;
            color: var(--secondary);
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #ecf0f1;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card-title i {
            color: var(--primary);
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
            position: relative;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #34495e;
        }
        
        input, select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s;
            background-color: #fff;
        }
        
        input:focus, select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
            outline: none;
        }
        
        .btn-group {
            display: flex;
            gap: 15px;
            margin-top: 15px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 12px 25px;
            border-radius: 8px;
            border: none;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
        }
        
        .btn-secondary {
            background: #ecf0f1;
            color: #7f8c8d;
        }
        
        .btn-secondary:hover {
            background: #d5dbdb;
            transform: translateY(-2px);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, var(--danger), #c0392b);
            color: white;
        }
        
        .btn-danger:hover {
            background: linear-gradient(135deg, #c0392b, #a93226);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(192, 57, 43, 0.3);
        }
        
        .btn-success {
            background: linear-gradient(135deg, var(--success), #219653);
            color: white;
        }
        
        .btn-view {
            background: linear-gradient(135deg, var(--view-color), #8e44ad);
            color: white;
        }
        
        .btn-view:hover {
            background: linear-gradient(135deg, #8e44ad, #7d3c98);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(142, 68, 173, 0.3);
        }
        
        .btn-aadhar {
            background: linear-gradient(135deg, var(--aadhar-color), #0e3a57);
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        th {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            text-align: left;
            padding: 15px;
            font-weight: 600;
        }
        
        th:first-child {
            border-top-left-radius: 8px;
        }
        
        th:last-child {
            border-top-right-radius: 8px;
        }
        
        td {
            padding: 12px 15px;
            border-bottom: 1px solid #ecf0f1;
        }
        
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        tr:hover {
            background-color: #f1f9ff;
        }
        
        .status-active {
            background: rgba(46, 204, 113, 0.15);
            color: var(--success);
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: 500;
            display: inline-block;
        }
        
        .status-leave {
            background: rgba(241, 196, 15, 0.15);
            color: var(--warning);
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: 500;
            display: inline-block;
        }
        
        .status-terminated {
            background: rgba(231, 76, 60, 0.15);
            color: var(--danger);
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: 500;
            display: inline-block;
        }
        
        .status-suspended {
            background: rgba(149, 165, 166, 0.15);
            color: var(--gray);
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: 500;
            display: inline-block;
        }
        
        .actions {
            display: flex;
            gap: 8px;
        }
        
        .btn-sm {
            padding: 8px 12px;
            font-size: 14px;
        }
        
        .success-message {
            background: rgba(46, 204, 113, 0.15);
            border-left: 4px solid var(--success);
            color: var(--success);
            padding: 15px;
            margin-bottom: 25px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.5s ease;
        }
        
        .error-message {
            background: rgba(231, 76, 60, 0.15);
            border-left: 4px solid var(--danger);
            color: var(--danger);
            padding: 15px;
            margin-bottom: 25px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.5s ease;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #7f8c8d;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #bdc3c7;
        }
        
        .empty-state h3 {
            font-size: 1.4rem;
            margin-bottom: 10px;
            color: #7f8c8d;
        }
        
        .empty-state p {
            margin-bottom: 20px;
        }
        
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
            padding: 20px;
            text-align: center;
            transition: transform 0.3s;
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary);
            margin: 10px 0;
        }
        
        .stat-label {
            color: var(--gray);
            font-size: 1rem;
        }
        
        /* Passport photo styles */
        .passport-photo {
            width: 100px;
            height: 120px;
            border: 1px solid #ddd;
            border-radius: 5px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f8f9fa;
            position: relative;
        }
        
        .passport-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .passport-placeholder {
            text-align: center;
            color: #95a5a6;
            padding: 10px;
        }
        
        .passport-placeholder i {
            font-size: 2rem;
            margin-bottom: 5px;
            display: block;
        }
        
        /* Image preview */
        .image-preview {
            margin-top: 10px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .image-preview-container {
            width: 150px;
            height: 180px;
            border: 1px dashed #3498db;
            border-radius: 5px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 10px;
            background-color: #f8f9fa;
        }
        
        .image-preview-container img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        
        .image-preview-text {
            font-size: 0.9rem;
            color: #7f8c8d;
        }
        
        /* Aadhar styles */
        .aadhar-badge {
            display: inline-block;
            background: rgba(26, 82, 118, 0.1);
            color: var(--aadhar-color);
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        .aadhar-info {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.9rem;
        }
        
        .aadhar-icon {
            color: var(--aadhar-color);
            font-size: 1.2rem;
        }
        
        /* Staff Details Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            background: white;
            border-radius: 12px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 5px 25px rgba(0,0,0,0.3);
            animation: modalOpen 0.4s ease-out;
        }
        
        .modal-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 20px;
            border-top-left-radius: 12px;
            border-top-right-radius: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-title {
            font-size: 1.5rem;
            font-weight: 600;
        }
        
        .close-modal {
            font-size: 1.8rem;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .close-modal:hover {
            transform: scale(1.2);
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .staff-details {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .staff-photo {
            width: 100%;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        .staff-photo img {
            width: 100%;
            height: auto;
            display: block;
        }
        
        .staff-info h3 {
            font-size: 1.4rem;
            margin-bottom: 15px;
            color: var(--secondary);
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .detail-row {
            display: grid;
            grid-template-columns: 150px 1fr;
            margin-bottom: 15px;
            align-items: center;
        }
        
        .detail-label {
            font-weight: 600;
            color: #7f8c8d;
        }
        
        .detail-value {
            font-weight: 500;
        }
        
        .salary-section {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .salary-section h3 {
            font-size: 1.4rem;
            margin-bottom: 20px;
            color: var(--secondary);
        }
        
        .salary-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .salary-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .salary-card .label {
            font-size: 1rem;
            color: #7f8c8d;
            margin-bottom: 8px;
        }
        
        .salary-card .value {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .salary-card .sub-value {
            font-size: 0.9rem;
            color: #7f8c8d;
            margin-top: 5px;
        }
        
        .salary-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .salary-table th {
            background: var(--view-color);
            color: white;
            padding: 12px 15px;
            text-align: left;
        }
        
        .salary-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
        }
        
        .salary-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        .no-salary {
            text-align: center;
            padding: 30px;
            color: #7f8c8d;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        /* Keyframe animation */
        @keyframes modalOpen {
            from { opacity: 0; transform: translateY(-50px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .btn-group {
                flex-direction: column;
            }
            
            table {
                display: block;
                overflow-x: auto;
            }
            
            .passport-photo {
                width: 80px;
                height: 100px;
            }
            
            .image-preview-container {
                width: 120px;
                height: 150px;
            }
            
            .staff-details {
                grid-template-columns: 1fr;
            }
            
            .salary-summary {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 480px) {
            .actions {
                flex-direction: column;
            }
            
            .passport-photo {
                width: 60px;
                height: 80px;
            }
        }
        
        .search-container {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }
        
        .search-container input {
            flex: 1;
            max-width: 300px;
        }
        
        .action-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .photo-cell {
            width: 120px;
        }
        
        .aadhar-input {
            position: relative;
        }
        
        .aadhar-input i {
            position: absolute;
            left: 12px;
            top: 42px;
            color: var(--aadhar-color);
        }
        
        .aadhar-input input {
            padding-left: 35px;
        }
        
        .aadhar-stats {
            background: linear-gradient(135deg, var(--aadhar-color), #0e3a57);
            color: white;
        }
        
        .aadhar-stats i {
            color: white;
        }
        
        .aadhar-stats .stat-value {
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="logo">
                <i class="fas fa-users-cog"></i>
                <h1>BTS Staff Management System</h1>
            </div>
            <p class="subtitle">Efficiently manage your BTS staff members with photos and Aadhar</p>
        </header>
        
        <?php if (!empty($success_message)): ?>
            <div class="<?= strpos($success_message, 'Error') === false ? 'success-message' : 'error-message' ?>">
                <i class="fas <?= strpos($success_message, 'Error') === false ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
                <span><?= $success_message ?></span>
            </div>
        <?php endif; ?>

        <a href="/btsapp/dashboard.php" class="btn btn-secondary" style="margin-bottom: 20px;">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
        
        <div class="stats-container">
            <div class="stat-card">
                <i class="fas fa-users fa-2x" style="color: #3498db;"></i>
                <div class="stat-value"><?= count($staff_members) ?></div>
                <div class="stat-label">Total Staff</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-user-check fa-2x" style="color: #27ae60;"></i>
                <div class="stat-value"><?= count(array_filter($staff_members, fn($m) => $m['status'] === 'Active')) ?></div>
                <div class="stat-label">Active Staff</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-id-card fa-2x" style="color: white;"></i>
                <div class="stat-value"><?= count(array_filter($staff_members, fn($m) => !empty($m['aadhar']))) ?></div>
                <div class="stat-label">Aadhar Registered</div>
            </div>
            <div class="stat-card aadhar-stats">
                <i class="fas fa-coins fa-2x"></i>
                <div class="stat-value">₹<?= number_format(array_sum(array_column($staff_members, 'monthly_salary')), 2) ?></div>
                <div class="stat-label">Monthly Salary</div>
            </div>
        </div>
        
        <div class="card">
            <h2 class="card-title"><i class="fas <?= $edit_mode ? 'fa-user-edit' : 'fa-user-plus' ?>"></i> <?= $edit_mode ? 'Update Staff Member' : 'Add New Staff Member' ?></h2>
            
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="id" value="<?= $id ?>">
                <?php if ($edit_mode && !empty($image)): ?>
                    <input type="hidden" name="old_image" value="<?= $image ?>">
                <?php endif; ?>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="image">Staff Photo *</label>
                        <input type="file" name="image" id="imageInput" accept="image/*" <?= !$edit_mode ? 'required' : '' ?>>
                        <small>Max 2MB, JPG, PNG, or JPEG (Passport size recommended)</small>

                        <div class="image-preview">
                            <div class="image-preview-container">
                                <?php if ($edit_mode && !empty($image)): ?>
                                    <img src="<?= $uploadDirectory . $image ?>" alt="Current Staff Photo" id="imagePreview">
                                <?php else: ?>
                                    <div id="imagePreview" class="passport-placeholder">
                                        <i class="fas fa-user"></i>
                                        <span>Photo Preview</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="image-preview-text">Photo will be cropped to passport size</div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="full_name">Full Name *</label>
                        <input type="text" name="full_name" required value="<?= htmlspecialchars($full_name) ?>" placeholder="Enter full name">
                    </div>
                    
                    <div class="form-group">
                        <label for="position">Position *</label>
                        <input type="text" name="position" required value="<?= htmlspecialchars($position) ?>" placeholder="Enter position">
                    </div>
                    
                    <div class="form-group">
                        <label for="contact_number">Contact Number</label>
                        <input type="tel" name="contact_number" value="<?= htmlspecialchars($contact_number) ?>" placeholder="Enter contact number">
                    </div>
                    
                    <div class="form-group aadhar-input">
                        <label for="aadhar">Aadhar Number</label>
                        <i class="fas fa-id-card"></i>
                        <input type="text" name="aadhar" id="aadharInput" value="<?= htmlspecialchars($aadhar) ?>" placeholder="Enter 12-digit Aadhar number" maxlength="12">
                    </div>
                    
                    <div class="form-group">
                        <label for="address">Address</label>
                        <input type="text" name="address" value="<?= htmlspecialchars($address) ?>" placeholder="Enter address">
                    </div>
                    
                    <div class="form-group">
                        <label for="hire_date">Hire Date *</label>
                        <input type="date" name="hire_date" required value="<?= $hire_date ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="monthly_salary">Monthly Salary (₹) *</label>
                        <input type="number" step="0.01" min="0" name="monthly_salary" required value="<?= $monthly_salary ?>" placeholder="Enter monthly salary">
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Status *</label>
                        <select name="status" required>
                            <option value="Active" <?= $status == 'Active' ? 'selected' : '' ?>>Active</option>
                            <option value="Terminated" <?= $status == 'Terminated' ? 'selected' : '' ?>>Terminated</option>
                            <option value="Suspended" <?= $status == 'Suspended' ? 'selected' : '' ?>>Suspended</option>
                        </select>
                    </div>
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> <?= $edit_mode ? 'Update Staff' : 'Add Staff' ?>
                    </button>
                    
                    <?php if ($edit_mode): ?>
                        <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel Edit
                        </a>
                    <?php else: ?>
                        <button type="reset" class="btn btn-secondary" id="resetFormBtn">
                            <i class="fas fa-undo"></i> Reset Form
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <div class="card">
            <div class="action-header">
                <h2 class="card-title"><i class="fas fa-list"></i> Staff Directory</h2>
                <div class="search-container">
                    <input type="text" id="searchInput" placeholder="Search staff members...">
                    <button class="btn btn-secondary" id="clearSearchBtn">
                        <i class="fas fa-times"></i> Clear
                    </button>
                </div>
            </div>
            
            <?php if (!empty($staff_members)): ?>
                <div class="table-responsive">
                    <table id="staffTable">
                        <thead>
                            <tr>
                                <th>Photo</th>
                                <th>Name</th>
                                <th>Position</th>
                                <th>Contact</th>
                                <th>Aadhar</th>
                                <th>Hire Date</th>
                                <th>Salary</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($staff_members as $row): ?>
                                <?php
                                // Fetch salary payments for this staff member
                                $stmt = $pdo->prepare("SELECT * FROM expenses 
                                                      WHERE staff_id = :staff_id 
                                                      ORDER BY expense_time DESC");
                                $stmt->execute(['staff_id' => $row['id']]);
                                $salary_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                $total_paid = 0;
                                foreach ($salary_payments as $payment) {
                                    $total_paid += $payment['amount'];
                                }
                                ?>
                                <tr>
                                    <td class="photo-cell">
                                        <div class="passport-photo">
                                            <?php if (!empty($row['image'])): ?>
                                                <img src="<?= $uploadDirectory . $row['image'] ?>" alt="<?= htmlspecialchars($row['full_name']) ?>">
                                            <?php else: ?>
                                                <div class="passport-placeholder">
                                                    <i class="fas fa-user"></i>
                                                    <span>No Photo</span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($row['full_name']) ?></td>
                                    <td><?= htmlspecialchars($row['position']) ?></td>
                                    <td><?= htmlspecialchars($row['contact_number']) ?></td>
                                    <td>
                                        <?php if (!empty($row['aadhar'])): ?>
                                            <div class="aadhar-info">
                                                <i class="fas fa-id-card aadhar-icon"></i>
                                                <span>•••• •••• <?= substr($row['aadhar'], -4) ?></span>
                                            </div>
                                        <?php else: ?>
                                            <span class="aadhar-badge">Not Provided</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('d M Y', strtotime($row['hire_date'])) ?></td>
                                    <td>₹<?= number_format($row['monthly_salary'], 2) ?></td>
                                    <td>
                                        <?php 
                                        $status_class = str_replace(' ', '-', strtolower($row['status']));
                                        echo "<span class='status-$status_class'>" . $row['status'] . "</span>";
                                        ?>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <button class="btn btn-view btn-sm view-staff" 
                                                    data-id="<?= $row['id'] ?>" 
                                                    data-name="<?= htmlspecialchars($row['full_name']) ?>"
                                                    data-position="<?= htmlspecialchars($row['position']) ?>"
                                                    data-contact="<?= htmlspecialchars($row['contact_number']) ?>"
                                                    data-aadhar="<?= htmlspecialchars($row['aadhar']) ?>"
                                                    data-address="<?= htmlspecialchars($row['address']) ?>"
                                                    data-hire-date="<?= date('d M Y', strtotime($row['hire_date'])) ?>"
                                                    data-monthly-salary="<?= ($row['monthly_salary']) ?>"
                                                    data-status="<?= $row['status'] ?>"
                                                    data-image="<?= !empty($row['image']) ? $uploadDirectory . $row['image'] : '' ?>"
                                                    data-payments='<?= json_encode($salary_payments) ?>'
                                                    data-total-paid="<?= $total_paid ?>">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                            <a href="?edit=<?= $row['id'] ?>" class="btn btn-primary btn-sm">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <a href="?delete=<?= $row['id'] ?>" class="btn btn-danger btn-sm"
                                               onclick="return confirm('Are you sure you want to delete <?= htmlspecialchars($row['full_name']) ?>?')">
                                                <i class="fas fa-trash-alt"></i> 
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-user-slash"></i>
                    <h3>No Staff Members Found</h3>
                    <p>Add your first staff member using the form above</p>
                    <a href="#" class="btn btn-primary" onclick="document.querySelector('form').scrollIntoView({behavior: 'smooth'})">
                        <i class="fas fa-user-plus"></i> Add Staff Member
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Staff Details Modal -->
    <div class="modal" id="staffDetailsModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="modalStaffName">Staff Details</h2>
                <span class="close-modal" id="closeModal">&times;</span>
            </div>
            <div class="modal-body">
                <div class="staff-details">
                    <div class="staff-photo">
                        <img id="modalStaffImage" src="" alt="Staff Photo">
                    </div>
                    <div class="staff-info">
                        <h3>Personal Information</h3>
                        <div class="detail-row">
                            <div class="detail-label">Full Name:</div>
                            <div class="detail-value" id="modalFullName"></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Position:</div>
                            <div class="detail-value" id="modalPosition"></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Contact:</div>
                            <div class="detail-value" id="modalContact"></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Aadhar:</div>
                            <div class="detail-value" id="modalAadhar"></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Address:</div>
                            <div class="detail-value" id="modalAddress"></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Hire Date:</div>
                            <div class="detail-value" id="modalHireDate"></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Monthly Salary:</div>
                            <div class="detail-value" id="modalMonthlySalary"></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Status:</div>
                            <div class="detail-value" id="modalStatus"></div>
                        </div>
                    </div>
                </div>
                
                <div class="salary-section">
                    <h3>Salary Payments</h3>
                    
                    <div class="salary-summary">
                        <div class="salary-card">
                            <div class="label">Monthly Salary</div>
                            <div class="value" id="modalSalaryValue">₹0.00</div>
                        </div>
                        <div class="salary-card">
                            <div class="label">Total Paid</div>
                            <div class="value" id="modalTotalPaid">₹0.00</div>
                            <div class="sub-value" id="modalPaymentCount">0 payments</div>
                        </div>
                        <div class="salary-card">
                            <div class="label">Balance</div>
                            <div class="value" id="modalBalance">₹0.00</div>
                        </div>
                    </div>
                    
                    <table class="salary-table" id="salaryPaymentsTable">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Description</th>
                                <th>Amount</th>
                                <th>Payment Mode</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Salary payments will be inserted here -->
                        </tbody>
                    </table>
                    
                    <div id="noSalaryMessage" class="no-salary" style="display: none;">
                        <i class="fas fa-money-bill-wave fa-2x"></i>
                        <h3>No Salary Payments Recorded</h3>
                        <p>This staff member hasn't received any salary payments yet.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Focus on the first input field when page loads
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelector('input').focus();
            
            // Auto-hide messages after 5 seconds
            const messages = document.querySelector('.success-message, .error-message');
            if (messages) {
                setTimeout(() => {
                    messages.style.opacity = '0';
                    setTimeout(() => {
                        messages.style.display = 'none';
                    }, 500);
                }, 5000);
            }
            
            // Search functionality
            const searchInput = document.getElementById('searchInput');
            const clearSearchBtn = document.getElementById('clearSearchBtn');
            
            if (searchInput) {
                searchInput.addEventListener('keyup', function() {
                    const filter = this.value.toLowerCase();
                    const table = document.getElementById('staffTable');
                    const tr = table.getElementsByTagName('tr');
                    
                    for (let i = 1; i < tr.length; i++) {
                        const td = tr[i].getElementsByTagName('td');
                        let found = false;
                        for (let j = 0; j < td.length; j++) {
                            if (td[j].textContent.toLowerCase().indexOf(filter) > -1) {
                                found = true;
                                break;
                            }
                        }
                        tr[i].style.display = found ? '' : 'none';
                    }
                });
            }
            
            if (clearSearchBtn) {
                clearSearchBtn.addEventListener('click', function() {
                    searchInput.value = '';
                    const tr = document.querySelectorAll('#staffTable tr');
                    tr.forEach(row => row.style.display = '');
                });
            }
            
            // Image preview functionality
            const imageInput = document.getElementById('imageInput');
            const imagePreview = document.getElementById('imagePreview');
            
            if (imageInput && imagePreview) {
                imageInput.addEventListener('change', function() {
                    const file = this.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            if (imagePreview.tagName === 'IMG') {
                                imagePreview.src = e.target.result;
                            } else {
                                const img = document.createElement('img');
                                img.src = e.target.result;
                                img.alt = "Image Preview";
                                imagePreview.parentNode.replaceChild(img, imagePreview);
                            }
                        }
                        reader.readAsDataURL(file);
                    }
                });
            }
            
            // Reset form button
            const resetFormBtn = document.getElementById('resetFormBtn');
            if (resetFormBtn) {
                resetFormBtn.addEventListener('click', function() {
                    // Reset the image preview
                    const previewContainer = document.querySelector('.image-preview-container');
                    if (previewContainer.querySelector('img')) {
                        const placeholder = document.createElement('div');
                        placeholder.id = 'imagePreview';
                        placeholder.className = 'passport-placeholder';
                        placeholder.innerHTML = '<i class="fas fa-user"></i><span>Photo Preview</span>';
                        previewContainer.innerHTML = '';
                        previewContainer.appendChild(placeholder);
                    }
                });
            }
            
            // Aadhar input validation
            const aadharInput = document.getElementById('aadharInput');
            if (aadharInput) {
                aadharInput.addEventListener('input', function() {
                    // Remove non-digit characters
                    this.value = this.value.replace(/\D/g, '');
                    
                    // Limit to 12 digits
                    if (this.value.length > 12) {
                        this.value = this.value.slice(0, 12);
                    }
                });
            }
            
            // Staff View Modal Functionality
            const modal = document.getElementById('staffDetailsModal');
            const closeModalBtn = document.getElementById('closeModal');
            const viewButtons = document.querySelectorAll('.view-staff');
            
            // Open modal
            viewButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // Get data from data attributes
                    const staffId = this.getAttribute('data-id');
                    const fullName = this.getAttribute('data-name');
                    const position = this.getAttribute('data-position');
                    const contact = this.getAttribute('data-contact');
                    const aadhar = this.getAttribute('data-aadhar');
                    const address = this.getAttribute('data-address');
                    const hireDate = this.getAttribute('data-hire-date');
                    const monthlySalary = this.getAttribute('data-monthly-salary');
                    const status = this.getAttribute('data-status');
                    const image = this.getAttribute('data-image');
                    const payments = JSON.parse(this.getAttribute('data-payments'));
                    const totalPaid = parseFloat(this.getAttribute('data-total-paid'));
                    
                    // Update modal content
                    document.getElementById('modalStaffName').textContent = fullName;
                    document.getElementById('modalFullName').textContent = fullName;
                    document.getElementById('modalPosition').textContent = position;
                    document.getElementById('modalContact').textContent = contact || 'Not provided';
                    document.getElementById('modalAadhar').textContent = aadhar ? aadhar : 'Not provided';
                    document.getElementById('modalAddress').textContent = address || 'Not provided';
                    document.getElementById('modalHireDate').textContent = hireDate;
                    document.getElementById('modalMonthlySalary').textContent = '₹' + monthlySalary;
                    document.getElementById('modalStatus').textContent = status;
                    
                    // Set status style
                    const statusEl = document.getElementById('modalStatus');
                    statusEl.className = 'detail-value';
                    statusEl.classList.add('status-' + status.toLowerCase());
                    
                    // Set staff image
                    const staffImage = document.getElementById('modalStaffImage');
                    if (image) {
                        staffImage.src = image;
                        staffImage.style.display = 'block';
                    } else {
                        staffImage.style.display = 'none';
                    }
                    
                    // Update salary information
                    document.getElementById('modalSalaryValue').textContent = '₹' + monthlySalary;
                    document.getElementById('modalTotalPaid').textContent = '₹' + totalPaid;
                    console.log(parseInt(monthlySalary),parseInt(totalPaid),123,monthlySalary-totalPaid);
                    // Calculate balance
                    const balance = (monthlySalary) - (totalPaid);
                    const balanceEl = document.getElementById('modalBalance');
                    balanceEl.textContent = '₹' + Math.abs(balance).toFixed(2);
                    if (balance > 0) {
                        balanceEl.style.color = '#e74c3c';
                    } else if (balance < 0) {
                        balanceEl.style.color = '#27ae60';
                    } else {
                        balanceEl.style.color = '#2c3e50';
                    }
                    
                    // Update payment count
                    const paymentCount = payments.length;
                    document.getElementById('modalPaymentCount').textContent = paymentCount + ' payment' + (paymentCount !== 1 ? 's' : '');
                    
                    // Populate payments table
                    const tableBody = document.querySelector('#salaryPaymentsTable tbody');
                    tableBody.innerHTML = '';
                    
                    if (paymentCount > 0) {
                        document.getElementById('noSalaryMessage').style.display = 'none';
                        
                        payments.forEach(payment => {
                            const row = document.createElement('tr');
                            
                            const dateCell = document.createElement('td');
                            dateCell.textContent = new Date(payment.expense_time).toLocaleDateString();
                            
                            const descCell = document.createElement('td');
                            descCell.textContent = payment.expense_name;
                            
                            const amountCell = document.createElement('td');
                            amountCell.textContent = '₹' + parseFloat(payment.amount).toFixed(2);
                            amountCell.style.fontWeight = '600';
                            
                            const modeCell = document.createElement('td');
                            modeCell.textContent = payment.payment_mode;
                            
                            row.appendChild(dateCell);
                            row.appendChild(descCell);
                            row.appendChild(amountCell);
                            row.appendChild(modeCell);
                            
                            tableBody.appendChild(row);
                        });
                    } else {
                        document.getElementById('noSalaryMessage').style.display = 'block';
                    }
                    
                    // Show the modal
                    modal.style.display = 'flex';
                });
            });
            
            // Close modal
            closeModalBtn.addEventListener('click', function() {
                modal.style.display = 'none';
            });
            
            // Close modal when clicking outside
            window.addEventListener('click', function(event) {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>