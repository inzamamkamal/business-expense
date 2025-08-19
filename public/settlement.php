<?php
include '../includes/session.php';
require '../config/db.php';
require '../includes/functions.php';


if (!isset($_SESSION['user_id']) || !isset($_SESSION['username']) || !isset($_SESSION['role']) || 
    !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    session_destroy();
    header('Location: ../index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$is_admin = $_SESSION['role'] === 'super_admin';
$currentDate = date('Y-m-d');
$date = isset($_GET['date']) ? htmlspecialchars($_GET['date']) : $currentDate;
$report_mode = isset($_GET['mode']) ? htmlspecialchars($_GET['mode']) : 'daily';
$payment_filter = isset($_GET['payment_mode']) ? htmlspecialchars($_GET['payment_mode']) : 'all';

// Initialize success message
$success_message = '';
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Handle settlement submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_settlement'])) {
    $giver_name = htmlspecialchars($_POST['giver_name']);
    $receiver_name = htmlspecialchars($_POST['receiver_name']);
    $amount = floatval($_POST['amount']);
    $mode = htmlspecialchars($_POST['payment_mode']);
    $description = htmlspecialchars($_POST['description']);
    $settlement_date = isset($_POST['settlement_date']) ? htmlspecialchars($_POST['settlement_date']) : $date;

    // Insert into settlement table
    $stmt = $pdo->prepare("INSERT INTO settlement (giver_name, receiver_name, amount, payment_mode, description, settlement_date) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$giver_name, $receiver_name, $amount, $mode, $description, $settlement_date]);
        
    $_SESSION['success_message'] = "Settlement added successfully!";
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

// Get settlements based on report mode
$settlements = [];
$settlementTotal = 0;
$settlementCashTotal = 0;
$settlementUpiTotal = 0;

$baseQuery = "SELECT * FROM settlement WHERE 1=1";
$params = [];
$query = $baseQuery;

// Apply date range based on report mode
switch ($report_mode) {
    case 'daily':
        $query .= " AND DATE(settlement_date) = ?";
        $params[] = $date;
        break;
    
    case 'weekly':
        $startOfWeek = date('Y-m-d', strtotime('monday this week', strtotime($date)));
        $endOfWeek = date('Y-m-d', strtotime('sunday this week', strtotime($date)));
        $query .= " AND settlement_date BETWEEN ? AND ?";
        $params[] = $startOfWeek;
        $params[] = $endOfWeek;
        break;
    
    case 'monthly':
        $startOfMonth = date('Y-m-01', strtotime($date));
        $endOfMonth = date('Y-m-t', strtotime($date));
        $query .= " AND settlement_date BETWEEN ? AND ?";
        $params[] = $startOfMonth;
        $params[] = $endOfMonth;
        break;
    
    case 'full':
        // No date filter
        break;
}

// Apply payment mode filter
if ($payment_filter !== 'all') {
    $query .= " AND payment_mode = ?";
    $params[] = $payment_filter;
}

$query .= " ORDER BY settlement_date DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$settlements = $stmt->fetchAll();

foreach ($settlements as $settlement) {
    $settlementTotal += $settlement['amount'];
    
    if ($settlement['payment_mode'] === 'CASH') {
        $settlementCashTotal += $settlement['amount'];
    } elseif ($settlement['payment_mode'] === 'UPI') {
        $settlementUpiTotal += $settlement['amount'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Settlement Tracker</title>
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
        
        .btn-back {
            background: #95a5a6;
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
        
        .form-inline input, .form-inline select, .form-inline textarea {
            padding: 10px 15px;
            border: 1px solid #d1e7ff;
            border-radius: 6px;
            font-size: 1rem;
            flex: 1;
            min-width: 150px;
        }
        
        .form-inline textarea {
            min-height: 50px;
            resize: vertical;
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
        
        .summary-card.cheque {
            border-top-color: #e67e22;
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
        
        .summary-card.cheque .value {
            color: #e67e22;
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
        
        .mode-cheque {
            color: #e67e22;
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
        
        .section-title {
            margin: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #3498db;
            color: #2c3e50;
            font-size: 1.5rem;
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
        
        .filter-tag.cheque {
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
            .action-cell, .btn, footer, .filter-panel {
                display: none !important;
            }
            
            header {
                background: #2c3e50;
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
                padding: 15px;
                text-align: center;
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
    </style>
</head>
<body>
<div class="container" id="printArea">
    <header>
        <div class="header-top">
            <div class="logo">
                <i class="fas fa-hand-holding-usd"></i>
                <h1>Settlement Tracker</h1>
            </div>
            <div class="report-actions">
                <button class="btn btn-print" onclick="window.print()">
                    <i class="fas fa-print"></i> Print
                </button>
                <button class="btn btn-pdf" onclick="downloadPDF()">
                    <i class="fas fa-file-pdf"></i> PDF
                </button>
                <a href="/btsapp/dashboard.php" class="btn btn-back">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
        
        <div class="report-controls">
            <div class="mode-selector">
                <a href="?mode=daily&date=<?= $date ?>&payment_mode=<?= $payment_filter ?>">
                    <button class="mode-btn <?= $report_mode === 'daily' ? 'active' : '' ?>">Daily</button>
                </a>
                <a href="?mode=weekly&date=<?= $date ?>&payment_mode=<?= $payment_filter ?>">
                    <button class="mode-btn <?= $report_mode === 'weekly' ? 'active' : '' ?>">Weekly</button>
                </a>
                <a href="?mode=monthly&date=<?= $date ?>&payment_mode=<?= $payment_filter ?>">
                    <button class="mode-btn <?= $report_mode === 'monthly' ? 'active' : '' ?>">Monthly</button>
                </a>
                <a href="?mode=full&payment_mode=<?= $payment_filter ?>">
                    <button class="mode-btn <?= $report_mode === 'full' ? 'active' : '' ?>">Full Report</button>
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

    <!-- Filter Panel -->
    <div class="filter-panel">
        <div class="filter-header">
            <h2><i class="fas fa-filter"></i> Filter Settlements</h2>
        </div>
        
        <div class="filter-controls">
            <div class="filter-group">
                <label for="reportMode"><i class="fas fa-calendar"></i> Date Range</label>
                <?php if ($report_mode !== 'full'): ?>
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
                    <input type="text" value="All Time" class="filter-select" disabled>
                <?php endif; ?>
            </div>
            
            <div class="filter-group">
                <label for="paymentFilter"><i class="fas fa-money-bill-wave"></i> Payment Mode</label>
                <select class="filter-select" id="paymentFilter">
                    <option value="all" <?= $payment_filter === 'all' ? 'selected' : '' ?>>All Payment Modes</option>
                    <option value="CASH" <?= $payment_filter === 'CASH' ? 'selected' : '' ?>>Cash</option>
                    <option value="UPI" <?= $payment_filter === 'UPI' ? 'selected' : '' ?>>UPI</option>
                    <option value="CHEQUE" <?= $payment_filter === 'CHEQUE' ? 'selected' : '' ?>>Cheque</option>
                </select>
            </div>
        </div>
        
        <div class="filter-status">
            <?php if ($payment_filter !== 'all'): ?>
                <div class="filter-tag <?= strtolower($payment_filter) ?>">
                    Payment: <?= $payment_filter ?>
                    <i class="fas fa-times" onclick="removePaymentFilter()"></i>
                </div>
            <?php endif; ?>
            
            <?php if ($report_mode !== 'full'): ?>
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
            
            <?php if ($payment_filter !== 'all'): ?>
                <button class="clear-filters" onclick="clearFilters()">
                    <i class="fas fa-times-circle"></i> Clear All Filters
                </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($report_mode === 'daily' && $date === $currentDate): ?>
        <div class="section-title">Add New Settlement</div>
        <form class="form-inline" method="post" id="addSettlementForm">
            <input type="text" name="giver_name" placeholder="Giver Name" required>
            <input type="text" name="receiver_name" placeholder="Receiver Name" required>
            <input type="number" step="0.01" name="amount" placeholder="Amount (₹)" required>
            <select name="payment_mode" required>
                <option value="">Payment Mode</option>
                <option value="CASH">Cash</option>
                <option value="UPI">UPI</option>
                <option value="CHEQUE">Cheque</option>
            </select>
            <textarea name="description" placeholder="Description (Optional)"></textarea>
            <input type="date" name="settlement_date" value="<?= $date ?>">
            <button type="submit" name="add_settlement">
                <i class="fas fa-plus"></i> Add Settlement
            </button>
        </form>
    <?php endif; ?>

    <div class="section-title">Settlement Summary</div>
    <div class="summary-cards">
        <div class="summary-card">
            <h3>Total Settlements</h3>
            <div class="value">₹ <?= number_format($settlementTotal, 2) ?></div>
        </div>
        <div class="summary-card cash">
            <h3>Cash Settlements</h3>
            <div class="value">₹ <?= number_format($settlementCashTotal, 2) ?></div>
        </div>
        <div class="summary-card upi">
            <h3>UPI Settlements</h3>
            <div class="value">₹ <?= number_format($settlementUpiTotal, 2) ?></div>
        </div>
        <div class="summary-card cheque">
            <h3>Cheque Settlements</h3>
            <div class="value">₹ <?= number_format($settlementTotal - $settlementCashTotal - $settlementUpiTotal, 2) ?></div>
        </div>
    </div>

    <div class="section-title">Settlement Details</div>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Giver</th>
                <th>Receiver</th>
                <th>Amount</th>
                <th>Payment Mode</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($settlements) > 0): ?>
                <?php foreach ($settlements as $settlement): ?>
                    <tr>
                        <td><?= date('M j, Y', strtotime($settlement['settlement_date'])) ?></td>
                        <td><?= htmlspecialchars($settlement['giver_name']) ?></td>
                        <td><?= htmlspecialchars($settlement['receiver_name']) ?></td>
                        <td>₹ <?= number_format($settlement['amount'], 2) ?></td>
                        <td class="mode-cell">
                            <?php if ($settlement['payment_mode'] === 'CASH'): ?>
                                <i class="fas fa-money-bill-wave mode-cash"></i>
                                <span class="mode-cash">Cash</span>
                            <?php elseif ($settlement['payment_mode'] === 'UPI'): ?>
                                <i class="fas fa-mobile-alt mode-upi"></i>
                                <span class="mode-upi">UPI</span>
                            <?php else: ?>
                                <i class="fas fa-money-check-alt mode-cheque"></i>
                                <span class="mode-cheque">Cheque</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($settlement['description']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" style="text-align: center; padding: 30px;">
                        No settlements recorded
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
        <?php if (count($settlements) > 0): ?>
            <tfoot>
                <tr class="total-row">
                    <td colspan="3">Total</td>
                    <td>₹ <?= number_format($settlementTotal, 2) ?></td>
                    <td colspan="2">
                        <span style="color: #27ae60;">Cash: ₹ <?= number_format($settlementCashTotal, 2) ?></span> | 
                        <span style="color: #9b59b6;">UPI: ₹ <?= number_format($settlementUpiTotal, 2) ?></span> | 
                        <span style="color: #e67e22;">Cheque: ₹ <?= number_format($settlementTotal - $settlementCashTotal - $settlementUpiTotal, 2) ?></span>
                    </td>
                </tr>
            </tfoot>
        <?php endif; ?>
    </table>
    
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
            <?php else: ?>
                All Time
            <?php endif; ?>
            </strong> | 
            Generated on <?= date('F j, Y, g:i a') ?>
        </p>
    </footer>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
function downloadPDF() {
    const element = document.getElementById("printArea");
    const opt = {
        margin: [10, 5, 10, 5],
        filename: 'settlement-report-<?= $report_mode ?>-<?= $date ?>.pdf',
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
    const payment_mode = document.getElementById('paymentFilter').value;
    let url = `?mode=daily&date=${date}`;
    if (payment_mode !== 'all') url += `&payment_mode=${payment_mode}`;
    window.location.href = url;
}

function changeWeek() {
    const week = document.getElementById('reportWeek').value;
    const year = week.substring(0, 4);
    const weekNum = week.substring(6);
    const date = new Date(year, 0, 1 + (weekNum - 1) * 7);
    const payment_mode = document.getElementById('paymentFilter').value;
    let url = `?mode=weekly&date=${date.toISOString().split('T')[0]}`;
    if (payment_mode !== 'all') url += `&payment_mode=${payment_mode}`;
    window.location.href = url;
}

function changeMonth() {
    const month = document.getElementById('reportMonth').value;
    const payment_mode = document.getElementById('paymentFilter').value;
    let url = `?mode=monthly&date=${month}-01`;
    if (payment_mode !== 'all') url += `&payment_mode=${payment_mode}`;
    window.location.href = url;
}

function applyFilters() {
    const payment_mode = document.getElementById('paymentFilter').value;
    const params = new URLSearchParams(window.location.search);
    
    if (payment_mode !== 'all') {
        params.set('payment_mode', payment_mode);
    } else {
        params.delete('payment_mode');
    }
    
    window.location.href = `${window.location.pathname}?${params.toString()}`;
}

function removePaymentFilter() {
    const params = new URLSearchParams(window.location.search);
    params.delete('payment_mode');
    window.location.href = `${window.location.pathname}?${params.toString()}`;
}

function clearFilters() {
    const params = new URLSearchParams(window.location.search);
    params.delete('payment_mode');
    window.location.href = `${window.location.pathname}?${params.toString()}`;
}

// Event listeners for filters
document.getElementById('paymentFilter').addEventListener('change', applyFilters);
document.getElementById('reportDate')?.addEventListener('change', changeDate);
document.getElementById('reportWeek')?.addEventListener('change', changeWeek);
document.getElementById('reportMonth')?.addEventListener('change', changeMonth);

// Session timeout handling
let sessionTimeout;
function resetSessionTimer() {
    clearTimeout(sessionTimeout);
    // Set timeout for 30 minutes (1800000 ms)
    sessionTimeout = setTimeout(() => {
        window.location.href = '/btsapp/logout.php?timeout=1';
    }, 1800000); // 30 minutes
}

// Reset timer on any user activity
document.addEventListener('mousemove', resetSessionTimer);
document.addEventListener('keypress', resetSessionTimer);
resetSessionTimer();
</script>
</body>
</html>