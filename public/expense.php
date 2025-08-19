<?php
include '../includes/session.php';
require '../config/db.php';
require '../includes/functions.php';

// Redirect to login if session is not valid
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || !isset($_SESSION['username']) ) {
    $_SESSION['error_message'] = "Session Expired | Please log in again.";
    session_destroy();
    header('Location: /btsapp/login.php');
    exit;
}

    $user_id = $_SESSION['user_id'];
    $is_admin = $_SESSION['role'] === 'super_admin' || $_SESSION['role'] === 'user';
    $currentDate = date('Y-m-d');
    $date = isset($_GET['date']) ? htmlspecialchars($_GET['date']) : $currentDate;
    $report_mode = isset($_GET['mode']) ? htmlspecialchars($_GET['mode']) : 'daily';
    $category_filter = isset($_GET['category_id']) ? intval($_GET['category_id']) : null;
    $payment_filter = isset($_GET['payment_mode']) ? htmlspecialchars($_GET['payment_mode']) : 'all';

// Initialize success message
    $success_message = '';
    if (isset($_SESSION['success_message'])) {
        $success_message = $_SESSION['success_message'];
        unset($_SESSION['success_message']);
    }

// Get staff members
    $staff = $pdo->query("SELECT * FROM staff")->fetchAll();

// Find Staff category ID
    $staffCategoryId = null;
    foreach ($pdo->query("SELECT * FROM categories")->fetchAll() as $cat) {
        if (strtolower($cat['name']) == 'staff salary') {
            $staffCategoryId = $cat['id'];
            break;
        }
    }

    // Handle expense submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_expense'])) {
        $name = '';
        $name = htmlspecialchars($_POST['expense_name']);
        $person = htmlspecialchars($_POST['person_name']);
        $amount = floatval($_POST['amount']);
        $mode = htmlspecialchars($_POST['payment_mode']);
        $category_id = intval($_POST['category_id']);
        $expense_date = isset($_POST['expense_date']) ? htmlspecialchars($_POST['expense_date']) : $date;
        $time = $expense_date . ' ' . date('H:i:s');
        $staff_id = null;

        // If category is Staff, get staff ID
        if ($category_id == $staffCategoryId && isset($_POST['staff_id'])) {
            $staff_id = intval($_POST['staff_id']);
            // Get staff name for display
            foreach ($staff as $s) {
                if ($s['id'] == $staff_id) {
                    $name .= $s['full_name'] . " " . $s['position'] . " - salary";
                    break;
                }
            }
        }

        $stmt = $pdo->prepare("INSERT INTO expenses (user_id, expense_name, person_name, amount, payment_mode, category_id, staff_id, expense_time, expense_date, upi_account) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $upi_account = ($mode === 'UPI') ? htmlspecialchars($_POST['upi_account']) : null;
        $stmt->execute([$user_id, $name, $person, $amount, $mode, $category_id, $staff_id, $time, $expense_date, $upi_account]);

        $_SESSION['success_message'] = "Expense added successfully!";
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }

    // Handle expense update
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_expense'])) {
        $id = intval($_POST['expense_id']);
        $name = htmlspecialchars($_POST['expense_name']);
        $person = htmlspecialchars($_POST['person_name']);
        $amount = floatval($_POST['amount']);
        $mode = htmlspecialchars($_POST['payment_mode']);
        $category_id = intval($_POST['category_id']);
        $staff_id = isset($_POST['staff_id']) ? intval($_POST['staff_id']) : null;
        
        // Get original expense to check permissions
        $stmt = $pdo->prepare("SELECT * FROM expenses WHERE id = ?");
        $stmt->execute([$id]);
        $original_expense = $stmt->fetch();
        
        // Check if user can edit (admin or owner)
        if ($is_admin) {
            $time = $original_expense['expense_date'] . ' ' . date('H:i:s', strtotime($original_expense['expense_time']));

            $upi_account = ($mode === 'UPI') ? htmlspecialchars($_POST['upi_account']) : null;
            $stmt = $pdo->prepare("UPDATE expenses SET expense_name = ?, person_name = ?, amount = ?, payment_mode = ?, category_id = ?, staff_id = ?, upi_account = ? WHERE id = ?");
            $stmt->execute([$name, $person, $amount, $mode, $category_id, $staff_id, $upi_account, $id]);
            
            $_SESSION['success_message'] = "Expense updated successfully!";
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        }
    }

    // Get all categories
    $categories = $pdo->query("SELECT * FROM categories where is_dispaly = '1'")->fetchAll();

    // Get expenses based on report mode
    $expenses = [];
    $total = 0;
    $cashTotal = 0;
    $upiTotal = 0;
    $categoryTotals = [];

    $baseQuery = "SELECT e.*, c.name AS category_name 
                FROM expenses e 
                LEFT JOIN categories c ON e.category_id = c.id 
                WHERE 1=1";

    $params = [];
    $query = $baseQuery;

    // Apply date range based on report mode
    switch ($report_mode) {
        case 'daily':
            $query .= " AND DATE(e.expense_time) = ?";
            $params[] = $date;
            break;
        
        case 'weekly':
            $startOfWeek = date('Y-m-d', strtotime('monday this week', strtotime($date)));
            $endOfWeek = date('Y-m-d', strtotime('sunday this week', strtotime($date)));
            $query .= " AND e.expense_time BETWEEN ? AND ?";
            $params[] = $startOfWeek . " 00:00:00";
            $params[] = $endOfWeek . " 23:59:59";
            break;
        
        case 'monthly':
            $startOfMonth = date('Y-m-01', strtotime($date));
            $endOfMonth = date('Y-m-t', strtotime($date));
            $query .= " AND e.expense_time BETWEEN ? AND ?";
            $params[] = $startOfMonth . " 00:00:00";
            $params[] = $endOfMonth . " 23:59:59";
            break;
        
        case 'full':
            // No date filter
            break;
        
        case 'category':
            // Category mode handled separately below
            break;
    }

    // Apply category filter if set
    if ($category_filter) {
        $query .= " AND e.category_id = ?";
        $params[] = $category_filter;
    }

    // Apply payment mode filter
    if ($payment_filter !== 'all') {
        $query .= " AND e.payment_mode = ?";
        $params[] = $payment_filter;
    }

    $query .= " ORDER BY e.expense_time DESC";

    // Execute query for all modes except category
    if ($report_mode !== 'category') {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $expenses = $stmt->fetchAll();
    }

    // Special handling for category mode
    if ($report_mode === 'category') {
        // Get all expenses for category mode
        $allExpensesQuery = "SELECT e.*, c.name AS category_name 
                            FROM expenses e 
                            LEFT JOIN categories c ON e.category_id = c.id 
                            WHERE 1=1";
        
        $allParams = [];
        
        if ($category_filter) {
            $allExpensesQuery .= " AND e.category_id = ?";
            $allParams[] = $category_filter;
        }
        
        if ($payment_filter !== 'all') {
            $allExpensesQuery .= " AND e.payment_mode = ?";
            $allParams[] = $payment_filter;
        }
        
        $allExpensesQuery .= " ORDER BY e.expense_time DESC";
        
        $stmt = $pdo->prepare($allExpensesQuery);
        $stmt->execute($allParams);
        $allExpenses = $stmt->fetchAll();
        
        // If specific category is selected, filter expenses
        if ($category_filter) {
            $expenses = $allExpenses;
        }
    } else {
        $allExpenses = $expenses;
    }

    // Calculate totals for both all expenses and filtered expenses
    foreach ($allExpenses as $expense) {
        $catId = $expense['category_id'] ?? 'uncategorized';
        if (!isset($categoryTotals[$catId])) {
            $categoryTotals[$catId] = [
                'name' => $expense['category_name'] ?? 'Uncategorized',
                'total' => 0,
                'count' => 0
            ];
        }
        $categoryTotals[$catId]['total'] += $expense['amount'];
        $categoryTotals[$catId]['count']++;
    }

    foreach ($expenses as $expense) {
        $total += $expense['amount'];
        
        if ($expense['payment_mode'] === 'Cash') {
            $cashTotal += $expense['amount'];
        } else {
            $upiTotal += $expense['amount'];
        }
    }
    ?>

    <?php

    function getMissingDates($pdo, $startDate, $endDate) {
        // Get all dates with expenses
        $stmt = $pdo->prepare("SELECT DISTINCT DATE(expense_time) AS expense_date 
                            FROM expenses 
                            WHERE expense_time BETWEEN :start AND :end");
        $stmt->execute([
            'start' => $startDate . ' 00:00:00',
            'end' => $endDate . ' 23:59:59'
        ]);
        $expenseDates = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Generate all dates in range
        $allDates = [];
        $current = strtotime($startDate);
        $end = strtotime($endDate);
        
        while ($current <= $end) {
            $allDates[] = date('Y-m-d', $current);
            $current = strtotime('+1 day', $current);
        }
        // Find dates without expenses
        return array_diff($allDates, $expenseDates);
    }

    // Handle AJAX request for missing dates
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_missing_dates') {
        $startDate = htmlspecialchars($_POST['startDate']);
        $endDate = htmlspecialchars($_POST['endDate']);
        
        $missingDates = getMissingDates($pdo, $startDate, $endDate);
        
        // Format dates for display
        $formattedDates = [];
        foreach ($missingDates as $date) {
            $formattedDates[] = [
                'date' => $date,
                'formatted' => date('l, F j, Y', strtotime($date))
            ];
        }
        
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'dates' => $formattedDates]);
        exit;
    }

    ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Expense Tracker - Comprehensive Reports</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e4edf5 100%);
            color: #333;
            padding: 20px;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        header {
            background: linear-gradient(90deg, #2c3e50 0%, #4a6491 100%);
            color: white;
            padding: 20px 30px;
        }
        
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .logo i {
            font-size: 2.2rem;
            color: #3498db;
        }
        
        .logo h1 {
            font-size: 1.8rem;
            font-weight: 600;
        }
        
        .report-actions {
            display: flex;
            gap: 15px;
        }
        
        .btn {
            padding: 10px 18px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            border: none;
            font-size: 14px;
        }
        
        .btn-print {
            background: #3498db;
            color: white;
        }
        
        .btn-pdf {
            background: #e74c3c;
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        
        .report-controls {
            background: rgba(255, 255, 255, 0.15);
            padding: 15px;
            border-radius: 8px;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
        }
        
        .mode-selector {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .mode-btn {
            padding: 8px 16px;
            background: rgba(255, 255, 255, 0.2);
            border: none;
            border-radius: 6px;
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        .mode-btn:hover, .mode-btn.active {
            background: #3498db;
        }
        
        .date-controls {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .date-controls input, 
        .date-controls select,
        .date-controls button {
            padding: 8px 12px;
            border: none;
            border-radius: 6px;
            background: rgba(255, 255, 255, 0.9);
            font-size: 14px;
        }
        
        .date-controls button {
            background: #3498db;
            color: white;
            font-weight: 600;
            cursor: pointer;
        }
        
        .alert {
            background: #ffecb3;
            color: #7d6608;
            padding: 15px;
            margin: 20px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .alert i {
            font-size: 1.4rem;
        }
        
        .form-inline {
            background: #eaf7ff;
            padding: 20px;
            margin: 0 20px 20px;
            border-radius: 8px;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
        }
        
        .form-inline input, .form-inline select {
            padding: 10px 15px;
            border: 1px solid #d1e7ff;
            border-radius: 6px;
            font-size: 1rem;
            flex: 1;
            min-width: 150px;
        }
        
        .form-inline button {
            background: #28a745;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .summary-cards {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin: 0 20px 20px;
            flex-wrap: wrap;
        }
        
        .summary-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            flex: 1;
            min-width: 200px;
            border-top: 4px solid #3498db;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        }
        
        .summary-card.cash {
            border-top-color: #27ae60;
        }
        
        .summary-card.upi {
            border-top-color: #9b59b6;
        }
        
        .summary-card h3 {
            font-size: 1rem;
            color: #6c757d;
            margin-bottom: 10px;
        }
        
        .summary-card .value {
            font-size: 1.8rem;
            font-weight: 700;
            color: #2c3e50;
        }
        
        .summary-card.cash .value {
            color: #27ae60;
        }
        
        .summary-card.upi .value {
            color: #9b59b6;
        }
        
        .date-group-header {
            background: #f1f5f9;
            padding: 12px 20px;
            margin: 0 20px;
            border-radius: 6px 6px 0 0;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .date-group-total {
            background: #f8f9fa;
            padding: 12px 20px;
            margin: 0 20px 20px;
            border-radius: 0 0 6px 6px;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            border-top: 1px solid #e2e8f0;
        }
        
        table {
            width: calc(100% - 40px);
            margin: 0 20px 20px;
            border-collapse: collapse;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        }
        
        th {
            background: #3498db;
            color: white;
            padding: 14px 16px;
            text-align: left;
            font-weight: 600;
        }
        
        td {
            padding: 14px 16px;
            border-bottom: 1px solid #edf2f7;
        }
        
        tr:hover td {
            background: #f8fafc;
        }
        
        .mode-cell {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .mode-cash {
            color: #27ae60;
        }
        
        .mode-upi {
            color: #9b59b6;
        }
        
        .total-row {
            background: #f1f5f9;
            font-weight: bold;
        }
        
        .total-row td {
            padding: 16px;
            font-size: 1.1rem;
        }
        
        footer {
            background: #f8f9fa;
            padding: 15px;
            text-align: center;
            color: #6c757d;
            font-size: 0.9rem;
            border-top: 1px solid #eaeaea;
        }
        
        .action-cell {
            display: flex;
            gap: 8px;
        }
        
        .action-btn {
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 13px;
            border: none;
            transition: all 0.2s ease;
        }
        
        .edit-btn {
            background: #3498db;
            color: white;
        }
        
        .delete-btn {
            background: #e74c3c;
            color: white;
        }
                
        .action-btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
        
        .category-selector {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 0 20px 20px;
        }
        
        .category-card {
            flex: 1;
            min-width: 150px;
            background: white;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 4px solid #3498db;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .category-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .category-card h3 {
            font-size: 14px;
            color: #555;
            margin-bottom: 8px;
        }
        
        .category-card .value {
            font-size: 18px;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .category-card .count {
            font-size: 12px;
            color: #777;
            margin-top: 5px;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .close-modal {
            font-size: 24px;
            cursor: pointer;
            color: #777;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-group input, 
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        
        .modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn-cancel {
            background: #95a5a6;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        
        .btn-save {
            background: #2ecc71;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        
        .category-filter {
            padding: 8px 12px;
            border: none;
            border-radius: 6px;
            background: rgba(255, 255, 255, 0.9);
            font-size: 14px;
            min-width: 180px;
        }
        
        .print-header {
            display: none;
            text-align: center;
            padding: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .section-title {
            margin: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #3498db;
            color: #2c3e50;
            font-size: 1.5rem;
        }
        
        .category-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 0 20px 20px;
            padding: 15px;
            background: linear-gradient(90deg, #e3f2fd 0%, #bbdefb 100%);
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .category-title {
            font-size: 1.4rem;
            color: #1565c0;
            font-weight: 600;
        }
        
        .back-to-categories {
            padding: 8px 15px;
            background: #1565c0;
            color: white;
            border-radius: 6px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
        }
        
        .back-to-categories:hover {
            background: #0d47a1;
            transform: translateY(-2px);
        }
        
        .filter-panel {
            background: #f1f8ff;
            border-radius: 10px;
            padding: 20px;
            margin: 0 20px 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
        
        .filter-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #d1e7ff;
        }
        
        .filter-header h2 {
            font-size: 1.3rem;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .filter-header i {
            color: #3498db;
        }
        
        .filter-controls {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #4a6583;
        }
        
        .filter-select {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #d1e7ff;
            border-radius: 8px;
            background: white;
            font-size: 15px;
            color: #333;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        .filter-select:focus {
            border-color: #3498db;
            outline: none;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
        }
        
        .filter-status {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e2f0ff;
        }
        
        .filter-tag {
            background: #3498db;
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .filter-tag i {
            cursor: pointer;
        }
        
        .filter-tag.cash {
            background: #27ae60;
        }
        
        .filter-tag.upi {
            background: #9b59b6;
        }
        
        .filter-tag.category {
            background: #e67e22;
        }
        
        .filter-tag.date {
            background: #8e44ad;
        }
        
        .clear-filters {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            margin-top: 10px;
        }
        
        .clear-filters:hover {
            background: #c0392b;
        }
        
        @media print {
            body {
                background: white;
                padding: 0;
                font-size: 12px;
            }
            
            .container {
                box-shadow: none;
                margin: 0;
                padding: 0;
                max-width: 100%;
            }
            
            .header-top, .report-controls, .form-inline, 
            .category-selector, .action-cell, .btn, 
            .alert, .summary-cards, footer, .filter-panel {
                display: none !important;
            }
            
            header {
                background: #2c3e50;
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
                padding: 15px;
                text-align: center;
            }
            
            .print-header {
                display: block;
            }
            
            table {
                width: 100%;
                margin: 10px 0;
                box-shadow: none;
                font-size: 10px;
            }
            
            th {
                background: #3498db !important;
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
                padding: 8px 10px;
            }
            
            td {
                padding: 8px 10px;
            }
            
            .total-row td {
                padding: 10px;
                font-size: 11px;
            }
            
            .logo h1 {
                font-size: 18px;
            }
            
            .summary-cards {
                display: flex;
                margin: 15px 0;
                justify-content: space-around;
            }
            
            .summary-card {
                min-width: 150px;
                padding: 10px;
                box-shadow: none;
                border: 1px solid #ddd;
            }
            
            .summary-card .value {
                font-size: 16px;
            }
            
            .print-footer {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                text-align: center;
                padding: 10px;
                font-size: 10px;
                color: #718096;
                border-top: 1px solid #e2e8f0;
            }
            
            .date-group-header, .date-group-total {
                margin: 5px 0;
                padding: 8px 10px;
            }
        }
        
        @keyframes modalOpen {
            from {opacity: 0; transform: scale(0.9);}
            to {opacity: 1; transform: scale(1);}
        }
        
        .modal-content {
            animation: modalOpen 0.3s ease-out;
        }
        
        @media (max-width: 768px) {
            .form-inline {
                flex-direction: column;
                align-items: stretch;
            }
            
            .summary-cards {
                flex-direction: column;
            }
            
            .mode-selector {
                flex-direction: column;
            }
            
            .date-controls {
                flex-direction: column;
                align-items: stretch;
            }
            
            table {
                font-size: 14px;
            }
            
            th, td {
                padding: 8px 10px;
            }
            
            .filter-controls {
                flex-direction: column;
            }
            
            .filter-group {
                min-width: 100%;
            }
        }

        .btn-missing {
            background: #e67e22;
            color: white;
        }

        .date-range-controls {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
        }

        .date-range-controls .form-group {
            flex: 1;
            min-width: 200px;
        }

        .success-message {
            color: #27ae60;
            padding: 15px;
            background: #d4edda;
            border-radius: 8px;
            text-align: center;
            margin: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .success-message i {
            font-size: 1.5rem;
        }

        .error-message {
            color: #c0392b;
            padding: 15px;
            background: #f8d7da;
            border-radius: 8px;
            text-align: center;
        }

        .staff-select-group {
            display: none;
        }
    </style>
</head>
<body>
<div class="container" id="printArea">
    <header>
        <div class="header-top">
            <div class="logo">
                <i class="fas fa-file-invoice-dollar"></i>
                <h1>Expense Tracker</h1>
            </div>
            <div class="report-actions">
                <button class="btn btn-print" onclick="window.print()">
                    <i class="fas fa-print"></i> Print
                </button>
                <button class="btn btn-pdf" onclick="downloadPDF()">
                    <i class="fas fa-file-pdf"></i> PDF
                </button>
                <button class="btn btn-missing" id="missingDatesBtn">
                    <i class="fas fa-calendar-times"></i> Missing Dates
                </button>
            </div>
        </div>
        
        <div class="report-controls">
            <div class="mode-selector">
                <a href="?mode=daily&date=<?= $date ?><?= $category_filter ? '&category_id='.$category_filter : '' ?>&payment_mode=<?= $payment_filter ?>">
                    <button class="mode-btn <?= $report_mode === 'daily' ? 'active' : '' ?>">Daily</button>
                </a>
                <a href="?mode=weekly&date=<?= $date ?><?= $category_filter ? '&category_id='.$category_filter : '' ?>&payment_mode=<?= $payment_filter ?>">
                    <button class="mode-btn <?= $report_mode === 'weekly' ? 'active' : '' ?>">Weekly</button>
                </a>
                <a href="?mode=monthly&date=<?= $date ?><?= $category_filter ? '&category_id='.$category_filter : '' ?>&payment_mode=<?= $payment_filter ?>">
                    <button class="mode-btn <?= $report_mode === 'monthly' ? 'active' : '' ?>">Monthly</button>
                </a>
                <a href="?mode=full<?= $category_filter ? '&category_id='.$category_filter : '' ?>&payment_mode=<?= $payment_filter ?>">
                    <button class="mode-btn <?= $report_mode === 'full' ? 'active' : '' ?>">Full Report</button>
                </a>
                <a href="?mode=category&payment_mode=<?= $payment_filter ?>">
                    <button class="mode-btn <?= $report_mode === 'category' ? 'active' : '' ?>">Category</button>
                </a>
            </div>
        </div>
    </header>

    <!-- Success Message -->
    <?php if (!empty($success_message)): ?>
        <div class="success-message">
            <i class="fas fa-check-circle"></i> <?= $success_message ?>
        </div>
    <?php endif; ?>

    <div class="print-header">
        <h1>Expense Report</h1>
        <p>
            Report Mode: <strong><?= ucfirst($report_mode) ?></strong> | 
            Date Range: <strong>
            <?php if ($report_mode === 'daily'): ?>
                <?= date('F j, Y', strtotime($date)) ?>
            <?php elseif ($report_mode === 'weekly'): ?>
                <?= date('F j', strtotime($startOfWeek)) ?> - <?= date('F j, Y', strtotime($endOfWeek)) ?>
            <?php elseif ($report_mode === 'monthly'): ?>
                <?= date('F Y', strtotime($date)) ?>
            <?php elseif ($report_mode === 'category'): ?>
                <?= $category_filter ? 'Category: ' . htmlspecialchars($expenses[0]['category_name'] ?? '') : 'All Categories' ?>
            <?php else: ?>
                All Time
            <?php endif; ?>
            </strong>
        </p>
        <p>Generated on <?= date('F j, Y, g:i a') ?></p>
    </div>

    <!-- Filter Panel -->
    <div class="filter-panel">
        <div class="filter-header">
            <h2><i class="fas fa-filter"></i> Filter Expenses</h2>
        </div>
        
        <div class="filter-controls">
            <div class="filter-group">
                <label for="reportMode"><i class="fas fa-calendar"></i> Date Range</label>
                <?php if ($report_mode !== 'full' && $report_mode !== 'category'): ?>
                    <?php if ($report_mode === 'daily'): ?>
                        <input type="date" id="reportDate" value="<?= $date ?>" class="filter-select">
                    <?php elseif ($report_mode === 'weekly'): ?>
                        <input type="week" id="reportWeek" 
                               value="<?= date('Y-\WW', strtotime($date)) ?>"
                               class="filter-select">
                    <?php else: ?>
                        <input type="month" id="reportMonth" 
                               value="<?= date('Y-m', strtotime($date)) ?>"
                               class="filter-select">
                    <?php endif; ?>
                <?php else: ?>
                    <input type="text" value="<?= $report_mode === 'full' ? 'All Time' : 'Category Mode' ?>" class="filter-select" disabled>
                <?php endif; ?>
            </div>
            
            <div class="filter-group">
                <label for="categoryFilter"><i class="fas fa-tag"></i> Category</label>
                <select class="filter-select" id="categoryFilter">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $category_filter == $cat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="paymentFilter"><i class="fas fa-money-bill-wave"></i> Payment Mode</label>
                <select class="filter-select" id="paymentFilter">
                    <option value="all" <?= $payment_filter === 'all' ? 'selected' : '' ?>>All Payment Modes</option>
                    <option value="Cash" <?= $payment_filter === 'Cash' ? 'selected' : '' ?>>Cash</option>
                    <option value="UPI" <?= $payment_filter === 'UPI' ? 'selected' : '' ?>>Digital (UPI)</option>
                </select>
            </div>
        </div>
        
        <div class="filter-status">
            <?php if ($category_filter): ?>
                <?php 
                $category_name = 'All Categories';
                foreach ($categories as $cat) {
                    if ($cat['id'] == $category_filter) {
                        $category_name = $cat['name'];
                        break;
                    }
                }
                ?>
                <div class="filter-tag category">
                    Category: <?= htmlspecialchars($category_name) ?>
                    <i class="fas fa-times" onclick="removeCategoryFilter()"></i>
                </div>
            <?php endif; ?>
            
            <?php if ($payment_filter !== 'all'): ?>
                <div class="filter-tag <?= $payment_filter === 'Cash' ? 'cash' : 'upi' ?>">
                    Payment: <?= $payment_filter ?>
                    <i class="fas fa-times" onclick="removePaymentFilter()"></i>
                </div>
            <?php endif; ?>
            
            <?php if ($report_mode !== 'full' && $report_mode !== 'category'): ?>
                <div class="filter-tag date">
                    <?php if ($report_mode === 'daily'): ?>
                        Date: <?= date('M j, Y', strtotime($date)) ?>
                    <?php elseif ($report_mode === 'weekly'): ?>
                        Week: <?= date('M j', strtotime($startOfWeek)) ?> - <?= date('M j, Y', strtotime($endOfWeek)) ?>
                    <?php else: ?>
                        Month: <?= date('F Y', strtotime($date)) ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($category_filter || $payment_filter !== 'all'): ?>
                <button class="clear-filters" onclick="clearFilters()">
                    <i class="fas fa-times-circle"></i> Clear All Filters
                </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($is_admin): ?>
        <div class="section-title">Add New Expense</div>
        <form class="form-inline" method="post" id="addExpenseForm">
            <input type="text" name="expense_name" placeholder="Expense Name" required>

            <select name="category_id" id="categorySelect" required onchange="toggleStaffSelect()">
                <option value="">Select Category</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
            
            <div class="person-input-group" id="personTextGroup">
                <input type="text" name="person_name" placeholder="Person Name" required>
            </div>
            
            <div class="staff-select-group" id="staffSelectGroup" style="display: none; flex: 1;">
                <select name="staff_id" required>
                    <option value="">Select Staff Member</option>
                    <?php foreach ($staff as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['full_name']) . " " . htmlspecialchars($s['position']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <input type="number" name="amount" placeholder="Amount (₹)" required>
            
            <select name="payment_mode" required onchange="toggleUpiAccount(this)">
                <option value="">Payment Mode</option>
                <option value="Cash">Cash</option>
                <option value="UPI">UPI</option>
            </select>
            <div id="upiAccountGroup" style="display: none; flex: 1;">
                <select name="upi_account" required>
                    <option value="">Select UPI Account</option>
                    <option value="Shakil">Shakil</option>
                    <option value="Tanya">Tanya</option>
                    <option selected value="Current">Current</option>
                </select>
            </div>

            
            <input type="date" name="expense_date" value="<?= $date ?>">
            <button type="submit" name="add_expense">
                <i class="fas fa-plus"></i> Add Expense
            </button>
        </form>
    <?php endif; ?>

    <?php if ($report_mode === 'category' && $category_filter): ?>
        <div class="category-header">
            <?php 
            $category_name = 'All Categories';
            foreach ($categories as $cat) {
                if ($cat['id'] == $category_filter) {
                    $category_name = $cat['name'];
                    break;
                }
            }
            ?>
            <h2 class="category-title"><?= htmlspecialchars($category_name) ?> Expenses</h2>
            <a href="?mode=category" class="back-to-categories">
                <i class="fas fa-arrow-left"></i> Back to Categories
            </a>
        </div>
    <?php endif; ?>

    <?php if ($report_mode === 'category' && !$category_filter): ?>
        <div class="section-title">Expense Categories</div>
        <div class="category-selector">
            <?php foreach ($categories as $cat): ?>
                <a href="?mode=category&category_id=<?= $cat['id'] ?>&payment_mode=<?= $payment_filter ?>">
                    <div class="category-card">
                        <h3><?= htmlspecialchars($cat['name']) ?></h3>
                        <div class="value">₹ <?= number_format($categoryTotals[$cat['id']]['total'] ?? 0, 2) ?></div>
                        <div class="count"><?= $categoryTotals[$cat['id']]['count'] ?? 0 ?> expenses</div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="section-title">Expense Summary</div>
    <div class="summary-cards">
        <div class="summary-card">
            <h3>Total Expenses</h3>
            <div class="value">₹ <?= number_format($total, 2) ?></div>
        </div>
        <div class="summary-card cash">
            <h3>Cash Expenses</h3>
            <div class="value">₹ <?= number_format($cashTotal, 2) ?></div>
        </div>
        <div class="summary-card upi">
            <h3>Digital Expenses</h3>
            <div class="value">₹ <?= number_format($upiTotal, 2) ?></div>
        </div>
    </div>

    <div class="section-title">Expense Details</div>
    <?php if ($report_mode === 'weekly' || $report_mode === 'monthly'): ?>
        <?php 
        $groupedExpenses = [];
        foreach ($expenses as $exp) {
            $expDate = date('Y-m-d', strtotime($exp['expense_time']));
            $groupedExpenses[$expDate][] = $exp;
        }
        ?>
        
        <?php foreach ($groupedExpenses as $dateKey => $dailyExpenses): ?>
            <?php 
            $dayTotal = 0;
            foreach ($dailyExpenses as $exp) {
                $dayTotal += $exp['amount'];
            }
            ?>
            <div class="date-group-header">
                <span><?= date('F j, Y', strtotime($dateKey)) ?></span>
                <span><?= count($dailyExpenses) ?> expenses</span>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Expense</th>
                        <th>Person</th>
                        <th>Category</th>
                        <th>Amount</th>
                        <th>Payment Mode</th>
                        <?php if ($is_admin): ?>
                            <th>Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dailyExpenses as $exp): ?>
                        <?php
                        $canEdit = $is_admin;
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($exp['expense_name']) ?></td>
                            <td><?= htmlspecialchars($exp['person_name']) ?></td>
                            <td><?= htmlspecialchars($exp['category_name']) ?></td>
                            <td>₹ <?= number_format($exp['amount'], 2) ?></td>
                            <td class="mode-cell">
                                <?php if ($exp['payment_mode'] === 'Cash'): ?>
                                    <i class="fas fa-money-bill-wave mode-cash"></i>
                                    <span class="mode-cash">Cash</span>
                                <?php else: ?>
                                    <i class="fas fa-mobile-alt mode-upi"></i>
                                    <span class="mode-upi"><?= $exp['payment_mode'] ?></span>
                                <?php endif; ?>
                            </td>
                            <?php if ($is_admin): ?>
                                <td class="action-cell">
                                    <?php if ($canEdit): ?>
                                        <button class="action-btn edit-btn" onclick="openEditModal(
                                            <?= $exp['id'] ?>, 
                                            '<?= htmlspecialchars($exp['expense_name'], ENT_QUOTES) ?>',
                                            '<?= htmlspecialchars($exp['person_name'], ENT_QUOTES) ?>',
                                            <?= $exp['amount'] ?>,
                                            '<?= $exp['payment_mode'] ?>',
                                            <?= $exp['category_id'] ?>,
                                            <?= $exp['staff_id'] ?? 'null' ?>,
                                            '<?= $exp['upi_account'] ?? '' ?>'
                                        )">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="date-group-total">
                <span>Daily Total</span>
                <span>₹ <?= number_format($dayTotal, 2) ?></span>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Expense</th>
                    <th>Person</th>
                    <th>Category</th>
                    <th>Amount</th>
                    <th>Payment Mode</th>
                    <?php if ($is_admin): ?>
                        <th>Actions</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (count($expenses) > 0): ?>
                    <?php foreach ($expenses as $exp): ?>
                        <?php
                        $canEdit = $is_admin ;
                        ?>
                        <tr>
                            <td><?= date('M j, Y', strtotime($exp['expense_time'])) ?></td>
                            <td><?= htmlspecialchars($exp['expense_name']) ?></td>
                            <td><?= htmlspecialchars($exp['person_name']) ?></td>
                            <td><?= htmlspecialchars($exp['category_name']) ?></td>
                            <td>₹ <?= number_format($exp['amount'], 2) ?></td>
                            <td class="mode-cell">
                                <?php if ($exp['payment_mode'] === 'Cash'): ?>
                                    <i class="fas fa-money-bill-wave mode-cash"></i>
                                    <span class="mode-cash">Cash</span>
                                <?php else: ?>
                                    <i class="fas fa-mobile-alt mode-upi"></i>
                                    <span class="mode-upi"><?= $exp['payment_mode'] ?> (<?= $exp['upi_account'] ?>)</span>
                                <?php endif; ?>
                            </td>
                            <?php if ($is_admin): ?>
                                <td class="action-cell">
                                    <?php if ($canEdit): ?>
                                        <button class="action-btn edit-btn" onclick="openEditModal(
                                            <?= $exp['id'] ?>, 
                                            '<?= htmlspecialchars($exp['expense_name'], ENT_QUOTES) ?>',
                                            '<?= htmlspecialchars($exp['person_name'], ENT_QUOTES) ?>',
                                            <?= $exp['amount'] ?>,
                                            '<?= $exp['payment_mode'] ?>',
                                            <?= $exp['category_id'] ?>,
                                            <?= $exp['staff_id'] ?? 'null' ?>,
                                            '<?= $exp['upi_account'] ?? '' ?>'
                                        )">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="<?= $is_admin ? 7 : 6 ?>" style="text-align: center; padding: 30px;">
                            <?php if ($report_mode === 'category' && $category_filter): ?>
                                No expenses found for this category
                            <?php else: ?>
                                No expenses recorded
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
            <?php if (count($expenses) > 0): ?>
                <tfoot>
                    <tr class="total-row">
                        <td colspan="<?= $is_admin ? 4 : 3 ?>">Total</td>
                        <td>₹ <?= number_format($total, 2) ?></td>
                        <td colspan="<?= $is_admin ? 2 : 1 ?>">
                            <span style="color: #27ae60;">Cash: ₹ <?= number_format($cashTotal, 2) ?></span> | 
                            <span style="color: #9b59b6;">Digital: ₹ <?= number_format($upiTotal, 2) ?></span>
                        </td>
                    </tr>
                </tfoot>
            <?php endif; ?>
        </table>
    <?php endif; ?>

    <div style="margin: 20px; text-align: center;">
        <a href="/btsapp/dashboard.php" class="btn">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>
    
    <footer>
        <p>
            Report Mode: <strong><?= ucfirst($report_mode) ?></strong> | 
            Date Range: <strong>
            <?php if ($report_mode === 'daily'): ?>
                <?= date('F j, Y', strtotime($date)) ?>
            <?php elseif ($report_mode === 'weekly'): ?>
                <?= date('F j', strtotime($startOfWeek)) ?> - <?= date('F j, Y', strtotime($endOfWeek)) ?>
            <?php elseif ($report_mode === 'monthly'): ?>
                <?= date('F Y', strtotime($date)) ?>
            <?php elseif ($report_mode === 'category'): ?>
                <?= $category_filter ? 'Category: ' . htmlspecialchars($expenses[0]['category_name'] ?? '') : 'All Categories' ?>
            <?php else: ?>
                All Time
            <?php endif; ?>
            </strong> | 
            Generated on <?= date('F j, Y, g:i a') ?>
        </p>
    </footer>
</div>

<!-- Edit Expense Modal -->
<div class="modal" id="editModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Edit Expense</h2>
            <span class="close-modal" onclick="closeModal()">&times;</span>
        </div>
        <form method="post" id="editExpenseForm">
            <input type="hidden" name="expense_id" id="editExpenseId">
            <input type="hidden" name="update_expense" value="1">
            
            <div class="form-group">
                <label for="editExpenseName">Expense Name</label>
                <input type="text" id="editExpenseName" name="expense_name" required>
            </div>
            
            <div class="form-group" id="editPersonTextGroup">
                <label for="editPersonName">Person Name</label>
                <input type="text" id="editPersonName" name="person_name" required>
            </div>
            
            <div class="form-group" id="editStaffSelectGroup" style="display: none;">
                <label for="editStaffId">Staff Member</label>
                <select id="editStaffId" name="staff_id">
                    <option value="">Select Staff Member</option>
                    <?php foreach ($staff as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['full_name']) . " " . htmlspecialchars($s['position']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="editAmount">Amount (₹)</label>
                <input type="number" step="0.01" id="editAmount" name="amount" required>
            </div>

            <div class="form-group">
                <label for="editPaymentMode">Payment Mode</label>
                <select id="editPaymentMode" name="payment_mode" required onchange="toggleEditUpiAccount(this)">
                    <option value="">Select Payment Mode</option>
                    <option value="Cash">Cash</option>
                    <option value="UPI">UPI</option>
                </select>
            </div>
            
            <div class="form-group" id="editUpiAccountGroup" style="display: none;">
                <label for="editUpiAccount">UPI Account</label>
                <select id="editUpiAccount" name="upi_account" required>
                    <option value="">Select UPI Account</option>
                    <option value="Shakil">Shakil</option>
                    <option value="Tanya">Tanya</option>
                    <option value="Current">Current</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="editCategory">Category</label>
                <select id="editCategory" name="category_id" required onchange="toggleEditStaffSelect()">
                    <option value="">Select Category</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn-save">Save Changes</button>
            </div>
        </form>
    </div>
</div>


<div class="modal" id="missingDatesModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Dates Without Expenses</h2>
            <span class="close-modal" onclick="closeMissingModal()">&times;</span>
        </div>
        <div class="modal-body">
            <div class="date-range-controls">
                <div class="form-group">
                    <label for="startDate">Start Date</label>
                    <input type="date" id="startDate" class="filter-select" value="<?= date('Y-m-01') ?>">
                </div>
                <div class="form-group">
                    <label for="endDate">End Date</label>
                    <input type="date" id="endDate" class="filter-select" value="<?= date('Y-m-t') ?>">
                </div>
                <button class="btn-submit" onclick="loadMissingDates()">
                    <i class="fas fa-search"></i> Find Missing Dates
                </button>
            </div>
            <div id="missingDatesList" style="margin-top: 20px; max-height: 400px; overflow-y: auto;"></div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>

const staffCategoryId = <?= $staffCategoryId ? $staffCategoryId : 'null' ?>;

const staffMembers = [
    <?php foreach ($staff as $s): ?>
        { 
            id: <?= $s['id'] ?>, 
            full_name: "<?= addslashes($s['full_name']) ?>", 
            position: "<?= addslashes($s['position']) ?>" 
        },
    <?php endforeach; ?>
];

function downloadPDF() {
    const element = document.getElementById("printArea");
    const opt = {
        margin: [10, 5, 10, 5],
        filename: 'expense-report-<?= $report_mode ?>-<?= $date ?>.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { 
            scale: 2,
            useCORS: true,
            logging: false
        },
        jsPDF: { 
            unit: 'mm', 
            format: 'a4', 
            orientation: 'portrait',
            compress: true
        },
        pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
    };
    
    const btn = document.querySelector('.btn-pdf');
    const originalHtml = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating PDF...';
    btn.disabled = true;
    
    setTimeout(() => {
        html2pdf().set(opt).from(element).save().then(() => {
            btn.innerHTML = originalHtml;
            btn.disabled = false;
        });
    }, 500);
}

function changeDate() {
    const date = document.getElementById('reportDate').value;
    const category_id = document.getElementById('categoryFilter').value;
    const payment_mode = document.getElementById('paymentFilter').value;
    let url = `?mode=daily&date=${date}`;
    if (category_id) url += `&category_id=${category_id}`;
    if (payment_mode !== 'all') url += `&payment_mode=${payment_mode}`;
    window.location.href = url;
}

function changeWeek() {
    const week = document.getElementById('reportWeek').value;
    const year = week.substring(0, 4);
    const weekNum = week.substring(6);
    const date = new Date(year, 0, 1 + (weekNum - 1) * 7);
    const category_id = document.getElementById('categoryFilter').value;
    const payment_mode = document.getElementById('paymentFilter').value;
    let url = `?mode=weekly&date=${date.toISOString().split('T')[0]}`;
    if (category_id) url += `&category_id=${category_id}`;
    if (payment_mode !== 'all') url += `&payment_mode=${payment_mode}`;
    window.location.href = url;
}

function changeMonth() {
    const month = document.getElementById('reportMonth').value;
    const category_id = document.getElementById('categoryFilter').value;
    const payment_mode = document.getElementById('paymentFilter').value;
    let url = `?mode=monthly&date=${month}-01`;
    if (category_id) url += `&category_id=${category_id}`;
    if (payment_mode !== 'all') url += `&payment_mode=${payment_mode}`;
    window.location.href = url;
}

function applyFilters() {
    const category_id = document.getElementById('categoryFilter').value;
    const payment_mode = document.getElementById('paymentFilter').value;
    const params = new URLSearchParams(window.location.search);
    
    if (category_id) {
        params.set('category_id', category_id);
    } else {
        params.delete('category_id');
    }
    
    if (payment_mode !== 'all') {
        params.set('payment_mode', payment_mode);
    } else {
        params.delete('payment_mode');
    }
    
    window.location.href = `${window.location.pathname}?${params.toString()}`;
}

function removeCategoryFilter() {
    const payment_mode = document.getElementById('paymentFilter').value;
    const params = new URLSearchParams(window.location.search);
    params.delete('category_id');
    
    if (payment_mode !== 'all') {
        params.set('payment_mode', payment_mode);
    }
    
    window.location.href = `${window.location.pathname}?${params.toString()}`;
}

function removePaymentFilter() {
    const category_id = document.getElementById('categoryFilter').value;
    const params = new URLSearchParams(window.location.search);
    params.delete('payment_mode');
    
    if (category_id) {
        params.set('category_id', category_id);
    }
    
    window.location.href = `${window.location.pathname}?${params.toString()}`;
}

function clearFilters() {
    const params = new URLSearchParams(window.location.search);
    params.delete('category_id');
    params.delete('payment_mode');
    window.location.href = `${window.location.pathname}?${params.toString()}`;
}

function toggleStaffSelect() {
    const categoryId = document.getElementById('categorySelect').value;
    const staffSelect = document.getElementById('staffSelectGroup').querySelector('select[name="staff_id"]');
    
    if (staffCategoryId && categoryId == staffCategoryId) {
        document.getElementById('staffSelectGroup').style.display = 'block';
        staffSelect.required = true;
    } else {
        document.getElementById('staffSelectGroup').style.display = 'none';
        staffSelect.required = false;
    }
}

function toggleEditStaffSelect() {
    const categoryId = document.getElementById('editCategory').value;
    const staffSelect = document.getElementById('editStaffId');
    
    if (staffCategoryId && categoryId == staffCategoryId) {
        document.getElementById('editStaffSelectGroup').style.display = 'block';
        staffSelect.required = true;
    } else {
        document.getElementById('editStaffSelectGroup').style.display = 'none';
        staffSelect.required = false;
    }
}
// Modal functions
function openEditModal(id, name, person, amount, mode, category, staff_id, upi_account) {
    // Set basic fields
    document.getElementById('editExpenseId').value = id;
    document.getElementById('editExpenseName').value = name;
    document.getElementById('editPersonName').value = person;
    document.getElementById('editAmount').value = amount;
    document.getElementById('editPaymentMode').value = mode;
    document.getElementById('editCategory').value = category;
    
    // Handle UPI account
    const upiAccountGroup = document.getElementById('editUpiAccountGroup');
    const upiAccountSelect = document.getElementById('editUpiAccount');
    
    if (mode === 'UPI') {
        upiAccountGroup.style.display = 'block';
        upiAccountSelect.required = true;
        upiAccountSelect.value = upi_account || 'Tanya'; // Default to Tanya if not specified
    } else {
        upiAccountGroup.style.display = 'none';
        upiAccountSelect.required = false;
    }
    
    // Handle staff selection
    const staffSelectGroup = document.getElementById('editStaffSelectGroup');
    const staffSelect = document.getElementById('editStaffId');
    
    if (staffCategoryId && category == staffCategoryId) {
        staffSelectGroup.style.display = 'block';
        staffSelect.required = true;
        staffSelect.value = staff_id || '';
    } else {
        staffSelectGroup.style.display = 'none';
        staffSelect.required = false;
    }
    
    // Show modal
    document.getElementById('editModal').style.display = 'flex';
}

function toggleEditUpiAccount(select) {
    const upiAccountGroup = document.getElementById('editUpiAccountGroup');
    const upiAccountSelect = document.getElementById('editUpiAccount');
    
    if (select.value === 'UPI') {
        upiAccountGroup.style.display = 'block';
        upiAccountSelect.required = true;
    } else {
        upiAccountGroup.style.display = 'none';
        upiAccountSelect.required = false;
    }
}

function toggleEditStaffSelect() {
    const categoryId = document.getElementById('editCategory').value;
    const staffSelectGroup = document.getElementById('editStaffSelectGroup');
    const staffSelect = document.getElementById('editStaffId');
    
    if (staffCategoryId && categoryId == staffCategoryId) {
        staffSelectGroup.style.display = 'block';
        staffSelect.required = true;
    } else {
        staffSelectGroup.style.display = 'none';
        staffSelect.required = false;
    }
}

function closeModal() {
    document.getElementById('editModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('editModal');
    if (event.target === modal) {
        closeModal();
    }
}

// Event listeners for filters
document.getElementById('categoryFilter').addEventListener('change', applyFilters);
document.getElementById('paymentFilter').addEventListener('change', applyFilters);
document.getElementById('reportDate')?.addEventListener('change', changeDate);
document.getElementById('reportWeek')?.addEventListener('change', changeWeek);
document.getElementById('reportMonth')?.addEventListener('change', changeMonth);

// Initialize staff select on page load
document.addEventListener('DOMContentLoaded', function() {
    toggleStaffSelect();    
});

function openMissingDatesModal() {
    document.getElementById('missingDatesModal').style.display = 'flex';
    loadMissingDates();
}

function closeMissingModal() {
    document.getElementById('missingDatesModal').style.display = 'none';
}

function loadMissingDates() {
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;
    const listContainer = document.getElementById('missingDatesList');
    
    listContainer.innerHTML = '<p><i class="fas fa-spinner fa-spin"></i> Loading missing dates...</p>';
    
    const formData = new FormData();
    formData.append('action', 'get_missing_dates');
    formData.append('startDate', startDate);
    formData.append('endDate', endDate);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            if (data.dates.length > 0) {
                let html = '<ul style="list-style-type: none; padding: 0;">';
                data.dates.forEach(item => {
                    html += `<li style="padding: 10px; border-bottom: 1px solid #eee;">
                                <i class="fas fa-calendar-day"></i> ${item.formatted}
                             </li>`;
                });
                html += '</ul>';
                listContainer.innerHTML = html;
            } else {
                listContainer.innerHTML = '<p class="success-message"><i class="fas fa-check-circle"></i> All dates have expenses recorded!</p>';
            }
        } else {
            listContainer.innerHTML = '<p class="error-message"><i class="fas fa-exclamation-triangle"></i> Error loading data</p>';
        }
    })
    .catch(error => {
        listContainer.innerHTML = '<p class="error-message"><i class="fas fa-exclamation-triangle"></i> Network error</p>';
    });
}

// Session timeout handling
let sessionTimeout;
function resetSessionTimer() {
    clearTimeout(sessionTimeout);
    // Set timeout for 30 minutes (1800000 ms)
    sessionTimeout = setTimeout(() => {
        window.location.href = '/btsapp/logout.php?timeout=1';
    }, 1800000); // 30 minutes
}

const staffSelect = document.querySelector('select[name="staff_id"]');
if (staffSelect) {
    staffSelect.required = false; // Start with not required
}

const editStaffSelect = document.getElementById('editStaffId');
if (editStaffSelect) {
    editStaffSelect.required = false; // Start with not required
}

// Reset timer on any user activity
document.addEventListener('mousemove', resetSessionTimer);
document.addEventListener('keypress', resetSessionTimer);
resetSessionTimer();

function toggleUpiAccount(select) {
    const upiAccountGroup = document.getElementById('upiAccountGroup');
    if (select.value === 'UPI') {
        upiAccountGroup.style.display = 'block';
        upiAccountGroup.querySelector('select').required = true;
    } else {
        upiAccountGroup.style.display = 'none';
        upiAccountGroup.querySelector('select').required = false;
    }
}

function toggleEditUpiAccount(select) {
    const upiAccountGroup = document.getElementById('editUpiAccountGroup');
    if (select.value === 'UPI') {
        upiAccountGroup.style.display = 'block';
        upiAccountGroup.querySelector('select').required = true;
    } else {
        upiAccountGroup.style.display = 'none';
        upiAccountGroup.querySelector('select').required = false;
    }
}
</script>
</body>
</html>