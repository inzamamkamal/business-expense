<?php
include '../includes/session.php';
require '../config/db.php';
require '../includes/functions.php';


if (!isset($_SESSION['username']) || !isset($_SESSION['role']) || 
    !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    session_destroy();
    header('Location: ../index.php');
    exit();
}


$date = filter_input(INPUT_GET, 'date', FILTER_SANITIZE_STRING) ?? date('Y-m-d');
$report_mode = filter_input(INPUT_GET, 'mode', FILTER_SANITIZE_STRING) ?? 'daily';


$date = $_GET['date'] ?? date('Y-m-d');
// $isLocked = isDateLocked($pdo, $date);
$report_mode = $_GET['mode'] ?? 'daily'; // daily, weekly, monthly, full

// Get last locked date and balance
// $lockStmt = $pdo->prepare("SELECT locked_date, closing_amount FROM locks WHERE locked_date < ? ORDER BY locked_date DESC LIMIT 1");
// $lockStmt->execute([$date]);
// $lastLock = $lockStmt->fetch();

$startBalance = 0;
// $lastLockDate = null;
// if ($lastLock) {
//     $lastLockDate = $lastLock['locked_date'];
//     $startBalance = floatval($lastLock['closing_amount']);
// }

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SESSION['role'] === 'super_admin') {
    if (isset($_POST['upi_amount']) && isset($_POST['cash_amount']) && isset($_POST['upi_account'])) {
        $upi = floatval($_POST['upi_amount'] ?? 0);
        $cash = floatval($_POST['cash_amount'] ?? 0);
        $upiAccount = $_POST['upi_account']; 
        $incomeDate = date('Y-m-d', strtotime($date)) . ' 12:00:00';
        $user_id = $_SESSION['user_id'] ?? 0;
        $stmt = $pdo->prepare("INSERT INTO income (user_id, upi_amount, cash_amount, upi_account, income_time) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $upi, $cash, $upiAccount, $incomeDate]);
    } 
    elseif (isset($_POST['transfer_amount']) && $_SESSION['role'] === 'super_admin') {
        // Transfer submission
        $from = $_POST['from_account'];
        $to = $_POST['to_account'];
        $amount = floatval($_POST['transfer_amount']);
        $time = date('Y-m-d H:i:s');
        $transferUpiAccount = $_POST['transfer_upi_account'];

        $stmt = $pdo->prepare("INSERT INTO transfers (from_account, to_account, amount, transfer_time, upi_account) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$from, $to, $amount, $time, $transferUpiAccount]);
    }
    
    header("Location: add_income.php?date=$date&mode=$report_mode"); 
    exit;
}

// Get data based on report mode
$expenses = [];
$transfers = [];
$settlements = [];
$summaryData = [];
$totalIncome = 0;
$cashIncome = 0;
$upiIncome = 0;
$totalExpense = 0;
$cashExpense = 0;
$upiExpense = 0;
$cumulativeBalance = 0;
$upiAccountDetails = []; // To store UPI account breakdown
$upiAccountExpenses = []; // To store expenses per UPI account
$upiAccountSettlements = []; // To store settlements per UPI account
$upiAccountBalances = []; // To store balances per UPI account

// Settlement variables
$settlementCashOut = 0;
$settlementUpiOut = 0;

// Date range variables
$startDate = $date;
$endDate = $date;
$dateRangeText = date('F j, Y', strtotime($date));
$bookings = [];

// Transfer variables
$cashToUpi = 0;

switch ($report_mode) {
    case 'daily':
        // Daily income with UPI account breakdown
        $income = $pdo->prepare("SELECT 
                SUM(upi_amount) as upi, 
                SUM(cash_amount) as cash,
                upi_account
            FROM income 
            WHERE DATE(income_time) = ?
            GROUP BY upi_account");
        $income->execute([$date]);

        $upiIncome = 0;
        $cashIncome = 0;
        $upiAccountDetails = [];
        while ($incomeRow = $income->fetch()) {
            $account = $incomeRow['upi_account'] ?: 'Unknown';
            $amount = $incomeRow['upi'] ?: 0;
            
            if (!isset($upiAccountDetails[$account])) {
                $upiAccountDetails[$account] = 0;
            }
            $upiAccountDetails[$account] += $amount;
            $upiIncome += $amount;
            $cashIncome += $incomeRow['cash'] ?: 0;
        }

        // Daily expenses
        $expense = $pdo->prepare("SELECT 
                SUM(amount) as total,
                SUM(CASE WHEN payment_mode = 'Cash' THEN amount ELSE 0 END) as cash_total,
                SUM(CASE WHEN payment_mode = 'UPI' THEN amount ELSE 0 END) as upi_total
            FROM expenses WHERE DATE(expense_time) = ?");
        $expense->execute([$date]);
        $expenseData = $expense->fetch();
        $cashExpense = $expenseData['cash_total'] ?: 0;
        $upiExpense = $expenseData['upi_total'] ?: 0;
        $totalExpense = $cashExpense + $upiExpense;

        // Daily expenses per UPI account
        $expensePerAccount = $pdo->prepare("SELECT 
                upi_account,
                SUM(amount) as total
            FROM expenses 
            WHERE DATE(expense_time) = ? 
            AND payment_mode = 'UPI'
            GROUP BY upi_account");
        $expensePerAccount->execute([$date]);
        
        $upiAccountExpenses = [];
        while ($row = $expensePerAccount->fetch()) {
            $account = $row['upi_account'] ?: 'Unknown';
            $upiAccountExpenses[$account] = $row['total'] ?: 0;
        }

        // Daily settlements per UPI account
        $settlementPerAccount = $pdo->prepare("SELECT 
                giver_upi_account,
                SUM(amount) as paid
            FROM settlement 
            WHERE DATE(settlement_date) = ?
            AND payment_mode = 'UPI'
            GROUP BY giver_upi_account");
        $settlementPerAccount->execute([$date]);
        
        $upiAccountSettlements = [];
        while ($row = $settlementPerAccount->fetch()) {
            $account = $row['giver_upi_account'] ?: 'Unknown';
            $upiAccountSettlements[$account] = $row['paid'] ?: 0;
        }

        // Calculate UPI account balances
        foreach ($upiAccountDetails as $account => $income) {
            $expense = $upiAccountExpenses[$account] ?? 0;
            $paid = $upiAccountSettlements[$account] ?? 0;
            $upiAccountBalances[$account] = $income - $expense - $paid;
        }

        // Daily transfers
        $dailyTransfer = $pdo->prepare("
            SELECT 
                upi_account,
                SUM(CASE 
                    WHEN from_account = 'Cash' AND to_account = 'UPI' 
                    THEN amount ELSE 0 
                END) as upi_amount
            FROM transfers 
            WHERE DATE(transfer_time) = ?
            GROUP BY upi_account
        ");
        $dailyTransfer->execute([$date]);
        $transferData = $dailyTransfer->fetchAll();
        $cashToUpi = 0;
        foreach ($transferData as $row) {
            $cashToUpi += $row['upi_amount'] ?: 0;
        }

        $totalIncome = $upiIncome + $cashIncome;

        // Daily settlements
       $settlementQuery = $pdo->prepare("SELECT 
        SUM(CASE WHEN payment_mode = 'Cash' THEN amount ELSE 0 END) as cash_out,
        SUM(CASE WHEN payment_mode = 'UPI' THEN amount ELSE 0 END) as upi_out
    FROM settlement WHERE DATE(settlement_date) = ?");
        $settlementQuery->execute([$date]);
        $settlementData = $settlementQuery->fetch();
        $settlementCashOut = $settlementData['cash_out'] ?: 0;
        $settlementUpiOut = $settlementData['upi_out'] ?: 0;

        // Daily balance
        $cashBalance = $cashIncome - $cashExpense - $cashToUpi - $settlementCashOut;
        $upiBalance = $upiIncome - $upiExpense - $settlementUpiOut;

        // Fetch expense details
        $stmt = $pdo->prepare("SELECT expense_name, person_name, amount, payment_mode, upi_account, expense_time FROM expenses WHERE DATE(expense_time) = ? ORDER BY expense_time ASC");
        $stmt->execute([$date]);
        $expenses = $stmt->fetchAll();
        
        // Fetch transfer details
        $transferStmt = $pdo->prepare("SELECT * FROM transfers WHERE DATE(transfer_time) = ? ORDER BY transfer_time ASC");
        $transferStmt->execute([$date]);
        $transfers = $transferStmt->fetchAll();
        
        // Fetch settlement details
        $settlementStmt = $pdo->prepare("SELECT giver_name, receiver_name, amount, payment_mode, giver_upi_account, description, settlement_date FROM settlement WHERE DATE(settlement_date) = ? ORDER BY created_at ASC");
        $settlementStmt->execute([$date]);
        $settlements = $settlementStmt->fetchAll();
        break;
    
    case 'weekly':
        // Get start and end of week
        $startOfWeek = date('Y-m-d', strtotime('monday this week', strtotime($date)));
        $endOfWeek = date('Y-m-d', strtotime('sunday this week', strtotime($date)));
        $startDate = $startOfWeek;
        $endDate = $endOfWeek;
        $dateRangeText = date('M j', strtotime($startOfWeek)) . ' to ' . date('M j, Y', strtotime($endOfWeek));
        
        // Weekly income with UPI account breakdown
        $income = $pdo->prepare("SELECT 
                SUM(upi_amount) as upi, 
                SUM(cash_amount) as cash,
                upi_account
            FROM income 
            WHERE DATE(income_time) BETWEEN ? AND ?
            GROUP BY upi_account");
        $income->execute([$startOfWeek, $endOfWeek]);
        
        $upiIncome = 0;
        $cashIncome = 0;
        $upiAccountDetails = [];
        while ($incomeRow = $income->fetch()) {
            $account = $incomeRow['upi_account'] ?: 'Unknown';
            $amount = $incomeRow['upi'] ?: 0;
            
            if (!isset($upiAccountDetails[$account])) {
                $upiAccountDetails[$account] = 0;
            }
            $upiAccountDetails[$account] += $amount;
            $upiIncome += $amount;
            $cashIncome += $incomeRow['cash'] ?: 0;
        }

        // Weekly expenses
        $expense = $pdo->prepare("SELECT 
                SUM(amount) as total,
                SUM(CASE WHEN payment_mode = 'Cash' THEN amount ELSE 0 END) as cash_total,
                SUM(CASE WHEN payment_mode = 'UPI' THEN amount ELSE 0 END) as upi_total
            FROM expenses 
            WHERE DATE(expense_time) BETWEEN ? AND ?");
        $expense->execute([$startOfWeek, $endOfWeek]);
        $expenseData = $expense->fetch();
        $cashExpense = $expenseData['cash_total'] ?: 0;
        $upiExpense = $expenseData['upi_total'] ?: 0;
        $totalExpense = $cashExpense + $upiExpense;

        // Weekly expenses per UPI account
        $expensePerAccount = $pdo->prepare("SELECT 
                upi_account,
                SUM(amount) as total
            FROM expenses 
            WHERE DATE(expense_time) BETWEEN ? AND ?
            AND payment_mode = 'UPI'
            GROUP BY upi_account");
        $expensePerAccount->execute([$startOfWeek, $endOfWeek]);
        
        $upiAccountExpenses = [];
        while ($row = $expensePerAccount->fetch()) {
            $account = $row['upi_account'] ?: 'Unknown';
            $upiAccountExpenses[$account] = $row['total'] ?: 0;
        }

        // Weekly settlements per UPI account
        $settlementPerAccount = $pdo->prepare("SELECT 
                giver_upi_account,
                SUM(amount) as paid
            FROM settlement 
            WHERE DATE(settlement_date) BETWEEN ? AND ?
            AND payment_mode = 'UPI'
            GROUP BY giver_upi_account");
        $settlementPerAccount->execute([$startOfWeek, $endOfWeek]);
        
        $upiAccountSettlements = [];
        while ($row = $settlementPerAccount->fetch()) {
            $account = $row['giver_upi_account'] ?: 'Unknown';
            $upiAccountSettlements[$account] = $row['paid'] ?: 0;
        }

        // Calculate UPI account balances
        foreach ($upiAccountDetails as $account => $income) {
            $expense = $upiAccountExpenses[$account] ?? 0;
            $paid = $upiAccountSettlements[$account] ?? 0;
            $upiAccountBalances[$account] = $income - $expense - $paid;
        }

        // Weekly transfers
        $transferQuery = $pdo->prepare("SELECT 
                SUM(CASE WHEN from_account = 'Cash' AND to_account = 'UPI' THEN amount ELSE 0 END) as cash_to_upi
            FROM transfers 
            WHERE DATE(transfer_time) BETWEEN ? AND ?");
        $transferQuery->execute([$startOfWeek, $endOfWeek]);
        $transferData = $transferQuery->fetch();
        $cashToUpi = $transferData['cash_to_upi'] ?: 0;

        $totalIncome = $upiIncome + $cashIncome;

        // Weekly settlements
        $settlementQuery = $pdo->prepare("SELECT 
        SUM(CASE WHEN payment_mode = 'Cash' THEN amount ELSE 0 END) as cash_out,
        SUM(CASE WHEN payment_mode = 'UPI' THEN amount ELSE 0 END) as upi_out
    FROM settlement WHERE DATE(settlement_date) BETWEEN ? AND ?");
        $settlementQuery->execute([$startOfWeek, $endOfWeek]);
        $settlementData = $settlementQuery->fetch();
        $settlementCashOut = $settlementData['cash_out'] ?: 0;
        $settlementUpiOut = $settlementData['upi_out'] ?: 0;

        // Weekly balance
        $cashBalance = $cashIncome - $cashExpense - $cashToUpi - $settlementCashOut;
        $upiBalance = $upiIncome - $upiExpense - $settlementUpiOut;

        // Fetch expense details for the period
        $stmt = $pdo->prepare("SELECT expense_name, person_name, amount, payment_mode, upi_account, expense_time FROM expenses 
            WHERE DATE(expense_time) BETWEEN ? AND ? ORDER BY expense_time ASC");
        $stmt->execute([$startOfWeek, $endOfWeek]);
        $expenses = $stmt->fetchAll();
        
        // Fetch transfer details for the period
        $transferStmt = $pdo->prepare("SELECT * FROM transfers 
            WHERE DATE(transfer_time) BETWEEN ? AND ? ORDER BY transfer_time ASC");
        $transferStmt->execute([$startOfWeek, $endOfWeek]);
        $transfers = $transferStmt->fetchAll();
        
        // Fetch settlement details for the period
        $settlementStmt = $pdo->prepare("SELECT giver_name, receiver_name, amount, payment_mode, giver_upi_account, description, settlement_date FROM settlement 
            WHERE DATE(settlement_date) BETWEEN ? AND ? ORDER BY created_at ASC");
        $settlementStmt->execute([$startOfWeek, $endOfWeek]);
        $settlements = $settlementStmt->fetchAll();
        break;
    
    case 'monthly':
        // Get start and end of month
        $startOfMonth = date('Y-m-01', strtotime($date));
        $endOfMonth = date('Y-m-t', strtotime($date));
        $startDate = $startOfMonth;
        $endDate = $endOfMonth;
        $dateRangeText = date('F Y', strtotime($date));
        
        // Monthly income with UPI account breakdown
        $income = $pdo->prepare("SELECT 
                SUM(upi_amount) as upi, 
                SUM(cash_amount) as cash,
                upi_account
            FROM income 
            WHERE DATE(income_time) BETWEEN ? AND ?
            GROUP BY upi_account");
        $income->execute([$startOfMonth, $endOfMonth]);
        
        $upiIncome = 0;
        $cashIncome = 0;
        $upiAccountDetails = [];
        while ($incomeRow = $income->fetch()) {
            $account = $incomeRow['upi_account'] ?: 'Unknown';
            $amount = $incomeRow['upi'] ?: 0;
            
            if (!isset($upiAccountDetails[$account])) {
                $upiAccountDetails[$account] = 0;
            }
            $upiAccountDetails[$account] += $amount;
            $upiIncome += $amount;
            $cashIncome += $incomeRow['cash'] ?: 0;
        }

        // Monthly expenses
        $expense = $pdo->prepare("SELECT 
                SUM(amount) as total,
                SUM(CASE WHEN payment_mode = 'Cash' THEN amount ELSE 0 END) as cash_total,
                SUM(CASE WHEN payment_mode = 'UPI' THEN amount ELSE 0 END) as upi_total
            FROM expenses 
            WHERE DATE(expense_time) BETWEEN ? AND ?");
        $expense->execute([$startOfMonth, $endOfMonth]);
        $expenseData = $expense->fetch();
        $cashExpense = $expenseData['cash_total'] ?: 0;
        $upiExpense = $expenseData['upi_total'] ?: 0;
        $totalExpense = $cashExpense + $upiExpense;

        // Monthly expenses per UPI account
        $expensePerAccount = $pdo->prepare("SELECT 
                upi_account,
                SUM(amount) as total
            FROM expenses 
            WHERE DATE(expense_time) BETWEEN ? AND ?
            AND payment_mode = 'UPI'
            GROUP BY upi_account");
        $expensePerAccount->execute([$startOfMonth, $endOfMonth]);
        
        $upiAccountExpenses = [];
        while ($row = $expensePerAccount->fetch()) {
            $account = $row['upi_account'] ?: 'Unknown';
            $upiAccountExpenses[$account] = $row['total'] ?: 0;
        }

        // Monthly settlements per UPI account
        $settlementPerAccount = $pdo->prepare("SELECT 
                giver_upi_account,
                SUM(amount) as paid
            FROM settlement 
            WHERE DATE(settlement_date) BETWEEN ? AND ?
            AND payment_mode = 'UPI'
            GROUP BY giver_upi_account");
        $settlementPerAccount->execute([$startOfMonth, $endOfMonth]);
        
        $upiAccountSettlements = [];
        while ($row = $settlementPerAccount->fetch()) {
            $account = $row['giver_upi_account'] ?: 'Unknown';
            $upiAccountSettlements[$account] = $row['paid'] ?: 0;
        }

        // Calculate UPI account balances
        foreach ($upiAccountDetails as $account => $income) {
            $expense = $upiAccountExpenses[$account] ?? 0;
            $paid = $upiAccountSettlements[$account] ?? 0;
            $upiAccountBalances[$account] = $income - $expense - $paid;
        }

        // Monthly transfers
        $transferQuery = $pdo->prepare("SELECT 
                SUM(CASE WHEN from_account = 'Cash' AND to_account = 'UPI' THEN amount ELSE 0 END) as cash_to_upi FROM transfers 
            WHERE DATE(transfer_time) BETWEEN ? AND ?");
        $transferQuery->execute([$startOfMonth, $endOfMonth]);
        $transferData = $transferQuery->fetch();
        $cashToUpi = $transferData['cash_to_upi'] ?: 0;

        $totalIncome = $upiIncome + $cashIncome;

        // Monthly settlements
        $settlementQuery = $pdo->prepare("SELECT 
        SUM(CASE WHEN payment_mode = 'Cash' THEN amount ELSE 0 END) as cash_out,
        SUM(CASE WHEN payment_mode = 'UPI' THEN amount ELSE 0 END) as upi_out
    FROM settlement WHERE DATE(settlement_date) BETWEEN ? AND ?");
        $settlementQuery->execute([$startOfMonth, $endOfMonth]);
        $settlementData = $settlementQuery->fetch();
        $settlementCashOut = $settlementData['cash_out'] ?: 0;
        $settlementUpiOut = $settlementData['upi_out'] ?: 0;

        // Monthly balance
        $cashBalance = $cashIncome - $cashExpense - $cashToUpi - $settlementCashOut;
        $upiBalance = $upiIncome - $upiExpense - $settlementUpiOut;

        // Fetch expense details for the period
        $stmt = $pdo->prepare("SELECT expense_name, person_name, amount, payment_mode, upi_account, expense_time FROM expenses 
            WHERE DATE(expense_time) BETWEEN ? AND ? ORDER BY expense_time ASC");
        $stmt->execute([$startOfMonth, $endOfMonth]);
        $expenses = $stmt->fetchAll();
        
        // Fetch transfer details for the period
        $transferStmt = $pdo->prepare("SELECT * FROM transfers 
            WHERE DATE(transfer_time) BETWEEN ? AND ? ORDER BY transfer_time ASC");
        $transferStmt->execute([$startOfMonth, $endOfMonth]);
        $transfers = $transferStmt->fetchAll();
        
        // Fetch settlement details for the period
        $settlementStmt = $pdo->prepare("SELECT giver_name, receiver_name, amount, payment_mode, giver_upi_account, description, settlement_date FROM settlement 
            WHERE DATE(settlement_date) BETWEEN ? AND ? ORDER BY created_at ASC");
        $settlementStmt->execute([$startOfMonth, $endOfMonth]);
        $settlements = $settlementStmt->fetchAll();
        break;
    
    case 'full':
        $dateRangeText = "All Time";
        // Full period income with UPI account breakdown
        $income = $pdo->prepare("SELECT 
                SUM(upi_amount) as upi, 
                SUM(cash_amount) as cash,
                upi_account
            FROM income
            GROUP BY upi_account");
        $income->execute();
        
        $upiIncome = 0;
        $cashIncome = 0;
        $upiAccountDetails = [];
        while ($incomeRow = $income->fetch()) {
            $account = $incomeRow['upi_account'] ?: 'Unknown';
            $amount = $incomeRow['upi'] ?: 0;
            
            if (!isset($upiAccountDetails[$account])) {
                $upiAccountDetails[$account] = 0;
            }
            $upiAccountDetails[$account] += $amount;
            $upiIncome += $amount;
            $cashIncome += $incomeRow['cash'] ?: 0;
        }

        // Full period expenses
        $expense = $pdo->prepare("SELECT 
                SUM(amount) as total,
                SUM(CASE WHEN payment_mode = 'Cash' THEN amount ELSE 0 END) as cash_total,
                SUM(CASE WHEN payment_mode = 'UPI' THEN amount ELSE 0 END) as upi_total
            FROM expenses");
        $expense->execute();
        $expenseData = $expense->fetch();
        $cashExpense = $expenseData['cash_total'] ?: 0;
        $upiExpense = $expenseData['upi_total'] ?: 0;
        $totalExpense = $cashExpense + $upiExpense;

        // Full period expenses per UPI account
        $expensePerAccount = $pdo->prepare("SELECT 
                upi_account,
                SUM(amount) as total
            FROM expenses 
            WHERE payment_mode = 'UPI'
            GROUP BY upi_account");
        $expensePerAccount->execute();
        
        $upiAccountExpenses = [];
        while ($row = $expensePerAccount->fetch()) {
            $account = $row['upi_account'] ?: 'Unknown';
            $upiAccountExpenses[$account] = $row['total'] ?: 0;
        }

        // Full period settlements per UPI account
        $settlementPerAccount = $pdo->prepare("SELECT 
                giver_upi_account,
                SUM(amount) as paid
            FROM settlement 
            WHERE payment_mode = 'UPI'
            GROUP BY giver_upi_account");
        $settlementPerAccount->execute();
        
        $upiAccountSettlements = [];
        while ($row = $settlementPerAccount->fetch()) {
            $account = $row['giver_upi_account'] ?: 'Unknown';
            $upiAccountSettlements[$account] = $row['paid'] ?: 0;
        }

        // Calculate UPI account balances
        foreach ($upiAccountDetails as $account => $income) {
            $expense = $upiAccountExpenses[$account] ?? 0;
            $paid = $upiAccountSettlements[$account] ?? 0;
            $upiAccountBalances[$account] = $income - $expense - $paid;
        }

        // Full period transfers
        $transferQuery = $pdo->prepare("SELECT 
                SUM(CASE WHEN from_account = 'Cash' AND to_account = 'UPI' THEN amount ELSE 0 END) as cash_to_upi
            FROM transfers");
        $transferQuery->execute();
        $transferData = $transferQuery->fetch();
        $cashToUpi = $transferData['cash_to_upi'] ?: 0;
        $totalIncome = $upiIncome + $cashIncome;

        // Full period settlements
        $settlementQuery = $pdo->prepare("SELECT 
        SUM(CASE WHEN payment_mode = 'Cash' THEN amount ELSE 0 END) as cash_out,
        SUM(CASE WHEN payment_mode = 'UPI' THEN amount ELSE 0 END) as upi_out
    FROM settlement");
        $settlementQuery->execute();
        $settlementData = $settlementQuery->fetch();
        $settlementCashOut = $settlementData['cash_out'] ?: 0;
        $settlementUpiOut = $settlementData['upi_out'] ?: 0;

        // Full period balance
        $cashBalance = $cashIncome - $cashExpense - $cashToUpi - $settlementCashOut;
        $upiBalance = $upiIncome - $upiExpense - $settlementUpiOut;

        // Fetch all expense details
        $stmt = $pdo->prepare("SELECT expense_name, person_name, amount, payment_mode, upi_account, expense_time FROM expenses ORDER BY expense_time ASC");
        $stmt->execute();
        $expenses = $stmt->fetchAll();
        
        // Fetch all transfer details
        $transferStmt = $pdo->prepare("SELECT * FROM transfers ORDER BY transfer_time ASC");
        $transferStmt->execute();
        $transfers = $transferStmt->fetchAll();
        
        // Fetch all settlement details
        $settlementStmt = $pdo->prepare("SELECT giver_name, receiver_name, amount, payment_mode, giver_upi_account, description, settlement_date FROM settlement ORDER BY created_at ASC");
        $settlementStmt->execute();
        $settlements = $settlementStmt->fetchAll();
        break;
}

// Calculate net flows
$netCashFlow = ($cashIncome - $cashExpense) - $cashToUpi - $settlementCashOut;
$netUpiFlow = ($upiIncome - $upiExpense) - $settlementUpiOut;

// Calculate cumulative balance (all time)
$cumulativeIncome = $pdo->prepare("SELECT SUM(upi_amount) + SUM(cash_amount) as total FROM income");
$cumulativeIncome->execute();
$cumulativeIncomeTotal = $cumulativeIncome->fetchColumn()   ?: 0;

$cumulativeExpense = $pdo->prepare("SELECT SUM(amount) FROM expenses");
$cumulativeExpense->execute();
$cumulativeExpenseTotal = $cumulativeExpense->fetchColumn() ?: 0;

$cumulativeTransfer = $pdo->prepare("SELECT 
        SUM(CASE WHEN from_account = 'Cash' AND to_account = 'UPI' THEN amount ELSE 0 END) as cash_to_upi FROM transfers");
$cumulativeTransfer->execute();
$cumulativeTransferData = $cumulativeTransfer->fetch();
$cumulativeCashToUpi = $cumulativeTransferData['cash_to_upi'] ?: 0;

// Cumulative settlements
$cumulativeSettlement = $pdo->prepare("SELECT 
        SUM(CASE WHEN payment_mode = 'Cash' THEN amount ELSE 0 END) as cash_out,
        SUM(CASE WHEN payment_mode = 'UPI' THEN amount ELSE 0 END) as upi_out
    FROM settlement");
$cumulativeSettlement->execute();
$cumulativeSettlementData = $cumulativeSettlement->fetch();
$cumulativeSettlementCashOut = $cumulativeSettlementData['cash_out'] ?: 0;
$cumulativeSettlementUpiOut = $cumulativeSettlementData['upi_out'] ?: 0;

$cumulativeBalance = $cumulativeIncomeTotal - $cumulativeExpenseTotal - $cumulativeCashToUpi - $cumulativeSettlementCashOut - $cumulativeSettlementUpiOut;

$cumulativeIncomeTotal = $cumulativeIncomeTotal - $cashToUpi;
$buffetCount = 0;
$menuCount = 0;
$buffetFinalAmount = 0;
$buffetAdvanceAmount = 0;
$menuFinalAmount = 0;
$menuAdvanceAmount = 0;
$bookingAdvanceAmountUPI = 0;
$bookingAdvanceAmountCash = 0;
$bookingFinalAmountUPI = 0;
$bookingFinalAmountCash = 0;
$bookings = $pdo->prepare("SELECT * FROM bookings");
$bookings->execute();
$bookings = $bookings->fetchAll();
// echo "<pre>";
// print_r($bookings);
// die;
$buffetCount = 0;
$menuCount = 0;
$buffetAdvanceAmount = 0;
$buffetFinalAmount = 0;
$bookingAdvanceAmountCash = 0;
$bookingAdvanceAmountUPI = 0;
$bookingFinalAmountCash = 0;
$bookingFinalAmountUPI = 0;

foreach ($bookings as $booking) {
    // Booking count
    if ($booking['booking_type'] == 'Buffet') {
        $buffetCount++;

        $buffetAdvanceAmount += $booking['advance_paid'] ?? 0;
        $buffetFinalAmount += $booking['final_paid'] ?? 0;
    } elseif ($booking['booking_type'] == 'menu') {
        $menuCount++;
    }

    // Advance amount by method
    $advance = $booking['advance_paid'] ?? 0;
    $advanceMethod = $booking['advance_payment_method'] ?? 'upi';

    if ($advanceMethod === 'cash') {
        $bookingAdvanceAmountCash += $advance;
    } else {
        $bookingAdvanceAmountUPI += $advance;
    }

    // Final amount by method
    $final = $booking['final_paid'] ?? 0;
    $finalMethod = $booking['final_payment_method'] ?? 'upi';

    if ($finalMethod === 'cash') {
        $bookingFinalAmountCash += $final;
    } else {
        $bookingFinalAmountUPI += $final;
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Report System</title>
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
        }
        
        .date-controls input, .date-controls select {
            padding: 8px 12px;
            border: none;
            border-radius: 6px;
            background: rgba(255, 255, 255, 0.9);
            font-size: 14px;
        }
        
        .date-controls button {
            padding: 8px 16px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
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
        
        .form-section {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
            margin: 0 20px 20px;
        }
        .booking-summary-section {
    margin: 20px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.section-title {
    color: #2c3e50;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #3498db;
    font-size: 1.3rem;
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

.summary-card {
    background: white;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.card-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.card-header i {
    font-size: 1.5rem;
    color: #3498db;
}

.card-header h3 {
    margin: 0;
    color: #2c3e50;
    font-size: 1.2rem;
}

.stat-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
    padding: 8px 0;
}

.stat-label {
    color: #6c757d;
    font-weight: 500;
}

.stat-value {
    color: #2c3e50;
    font-weight: 600;
}

.total-row {
    margin-top: 15px;
    padding-top: 10px;
    border-top: 1px solid #eee;
    font-weight: bold;
}

.total-row .stat-value {
    color: #27ae60;
}

.buffet-card .card-header i {
    color: #e67e22;
}

.menu-card .card-header i {
    color: #9b59b6;
}

@media (max-width: 768px) {
    .summary-grid {
        grid-template-columns: 1fr;
    }
}
        
        .income-form {
            background: #eaf7ff;
            padding: 20px;
            border-radius: 8px;
        }
        
        .transfer-form {
            background: #f9f3e9;
            padding: 20px;
            border-radius: 8px;
        }
        
        .form-section h3 {
            margin-bottom: 10px;
            color: #2c3e50;
            border-bottom: 1px solid #3498db;
            padding-bottom: 8px;
        }
        
        .transfer-form h3 {
            border-bottom: 1px solid #e67e22;
        }
                
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .transfer-row, .settlement-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .form-section input, .form-section select, .form-section textarea {
            padding: 10px 15px;
            border: 1px solid #d1e7ff;
            border-radius: 6px;
            font-size: 1rem;
            width: 100%;
        }
        
        .form-section button {
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
        
        .transfer-form button {
            background: #e67e22;
        }
        
                
        .summary-section {
            margin: 0 20px 20px;
        }
        
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 15px;
        }
        
        .summary-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            border-left: 4px solid #3498db;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        }
        
        .summary-card.income {
            border-left-color: #3498db;
        }
        
        .summary-card.expense {
            border-left-color: #e74c3c;
        }
        
        .summary-card.transfer {
            border-left-color: #e67e22;
        }
        
        .summary-card.settlement {
            border-left-color: #9b59b6;
        }
        
        .summary-card.balance {
            border-left-color: #27ae60;
        }
        
        .summary-card h3 {
            font-size: 1.1rem;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .summary-card .value {
            font-size: 1.6rem;
            font-weight: 700;
            color: #2c3e50;
        }
        
        .sub-detail {
            font-size: 0.9rem;
            color: #666;
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 5px;
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
        
        .transfer-type {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .transfer-type.cash {
            color: #27ae60;
        }
        
        .transfer-type.bank {
            color: #3498db;
        }
        
        .transfer-icon {
            font-size: 1.2rem;
            margin: 0 5px;
        }
        
        .settlement-direction {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .settlement-in {
            color: #27ae60;
        }
        
        .settlement-out {
            color: #e74c3c;
        }
        
        .actions {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin: 20px;
            flex-wrap: wrap;
        }
        
        .btn-lock {
            background: #e74c3c;
            color: white;
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
            margin: 0 20px 15px;
            color: #2c3e50;
            border-bottom: 1px solid #3498db;
            padding-bottom: 10px;
        }
        
        .cumulative-section {
            margin: 0 20px 20px;
        }
        
        .cumulative-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 15px;
        }
        
        .date-range-display {
            background: #f1f5f9;
            padding: 10px 15px;
            border-radius: 6px;
            margin: 0 20px 15px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            justify-content: center;
        }
        
        .date-range-display i {
            color: #3498db;
            font-size: 1.2rem;
        }
        
        .period-title {
            text-align: center;
            margin: 20px 0;
            color: #2c3e50;
        }
        
        .no-transfers {
            text-align: center;
            padding: 30px;
        }
        
        .no-transfers i {
            font-size: 3rem;
            color: #bdc3c7;
            margin-bottom: 15px;
        }
        
        .no-transfers h3 {
            color: #7f8c8d;
            margin-bottom: 10px;
        }
        
        .no-transfers p {
            color: #95a5a6;
        }
        
        .account-balance-positive {
            color: #27ae60;
        }
        
        .account-balance-negative {
            color: #e74c3c;
        }
        
        /* Print-specific styles */
        @media print {
            body {
                background: white;
                padding: 0;
                font-size: 10pt;
            }
            
            .container {
                box-shadow: none;
                margin: 0;
                padding: 10px;
                width: 100%;
                max-width: 100%;
            }
            
            .btn, .form-section, .report-controls, .actions {
                display: none !important;
            }
            
            .summary-cards, .cumulative-cards {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .summary-card {
                page-break-inside: avoid;
            }
            
            table {
                page-break-inside: avoid;
                font-size: 9pt;
            }
            
            th, td {
                padding: 8px 10px;
            }
            
            .section-title {
                page-break-after: avoid;
            }
            
            .date-range-display {
                margin-top: 15px;
            }
            
            .period-title {
                margin: 10px 0;
            }
            
            .alert {
                display: none;
            }
        }
        
        @media (max-width: 768px) {
            .header-top {
                flex-direction: column;
                gap: 15px;
            }
            
            .report-actions {
                width: 100%;
                justify-content: center;
            }
            
            .date-controls {
                flex-wrap: wrap;
            }
            
            .form-row, .transfer-row, .settlement-row {
                grid-template-columns: 1fr;
            }
        }
        input[type="number"]::-webkit-outer-spin-button,
        input[type="number"]::-webkit-inner-spin-button {
          -webkit-appearance: none;
          margin: 0;
        }
        
        /* Hide arrows in number inputs for Firefox */
        input[type="number"] {
          -moz-appearance: textfield;
        }
    </style>
</head>
<body>
<div class="container" id="printArea">
    <header>
        <div class="header-top">
            <div class="logo">
                <i class="fas fa-file-invoice-dollar"></i>
                <h1>Financial Report System BTS</h1>
            </div>
            <div class="report-actions">
                <a class="btn btn-pdf" href="/btsapp/dashboard.php">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
            <div class="report-actions">
                <button class="btn btn-pdf" onclick="downloadPDF()">
                    <i class="fas fa-file-pdf"></i> Export PDF
                </button>
            </div>
        </div>
        
        <div class="report-controls">
            <div class="mode-selector">
                <a href="?mode=daily&date=<?= $date ?>">
                    <button class="mode-btn <?= $report_mode === 'daily' ? 'active' : '' ?>">Daily</button>
                </a>
                <a href="?mode=weekly&date=<?= $date ?>">
                    <button class="mode-btn <?= $report_mode === 'weekly' ? 'active' : '' ?>">Weekly</button>
                </a>
                <a href="?mode=monthly&date=<?= $date ?>">
                    <button class="mode-btn <?= $report_mode === 'monthly' ? 'active' : '' ?>">Monthly</button>
                </a>
                <a href="?mode=full">
                    <button class="mode-btn <?= $report_mode === 'full' ? 'active' : '' ?>">Full Report</button>
                </a>
            </div>
            
            <div class="date-controls">
                <input type="date" id="reportDate" value="<?= $date ?>">
                <button onclick="changeDate()">Load</button>
            </div>
        </div>
    </header>

    <div class="period-title">
        <h2><?= ucfirst($report_mode) ?> Financial Report</h2>
        <div class="date-range-display">
            <i class="fas fa-calendar-alt"></i>
            <span><?= $dateRangeText ?></span>
        </div>
    </div>

    <!-- <?php if ($isLocked): ?>
        <div class="alert">
            <i class="fas fa-lock"></i>
            <div>
                <strong>This date is locked.</strong> You cannot add income or transfers.
            </div>
        </div>
    <?php endif; ?> -->

    <?php if ($_SESSION['role'] === 'super_admin' && $report_mode === 'daily'): ?>
        <div class="form-section">
            <div class="income-form">
  <h3>Add Income</h3>
  <form method="post">
    <input type="hidden" name="date" value="<?= $date ?>">
    <div class="form-row">
      <div class="form-group">
        <label for="upi_amount">UPI Income</label>
        <input type="number" id="upi_amount" name="upi_amount" placeholder="e.g. 500" required>
      </div>

      <div class="form-group">
        <label for="cash_amount">Cash Income</label>
        <input type="number" id="cash_amount" name="cash_amount" placeholder="e.g. 1000" required>
      </div>

      <div class="form-group">
        <label for="upi_account">UPI Account</label>
        <select id="upi_account" name="upi_account" required>
          <option value="">Select</option>
          <option value="Shakil">Shakil</option>
          <option value="Tanya">Tanya</option>
          <option selected value="Current">Current</option>
        </select>
      </div>

      <div class="form-group" style="margin-top: 26px;">
        <button type="submit">
          <i class="fas fa-plus"></i> Add Income
        </button>
      </div>
    </div>
  </form>
</div>
            
            <div class="transfer-form">
                <h3>Transfer Funds Between Accounts</h3>
                <form method="post">
                    <div class="transfer-row">
                        <div>
                            <label>From Account</label>
                            <select name="from_account" required>
                                <option value="Cash">Cash</option>
                                <option value="UPI">UPI</option>
                            </select>
                        </div>
                        
                        <div>
                            <label>To Account</label>
                            <select name="to_account" required>
                                <option value="UPI">UPI</option>
                                <option value="Cash">Cash</option>
                            </select>
                        </div>

                        <div>
                            <label>Select Account </label>
                            <select name="transfer_upi_account">
                                <option value="Shakil">Shakil</option>
                                <option value="Tanya">Tanya</option>
                                <option selected value="Current">Current</option>
                            </select>
                        </div>
                        <div>
                            <label>Amount (₹)</label>
                            <input type="number"  name="transfer_amount" placeholder="Amount" required>
                        </div>
                    </div>
                    <button type="submit">
                        <i class="fas fa-exchange-alt"></i> Transfer Funds
                    </button>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <div class="summary-section">
        <h3 class="section-title">Financial Summary</h3>
        <div class="summary-cards">
            <!--<div class="summary-card balance">-->
            <!--    <h3>Starting Balance</h3>-->
            <!--    <div class="value">₹<?= number_format($startBalance, 2) ?></div>-->
            <!--</div>-->
            
            <div class="summary-card income">
                <h3>Total Sales</h3>
                <div class="value">₹<?= number_format($totalIncome - $cashToUpi, 2) ?></div>
                <div class="sub-detail">
                    <i class="fas fa-info-circle"></i> 
                    (UPI + Cash Sales)
                </div>
            </div>

            <div class="summary-card income">
                <h3>Total Cash Sales</h3>
                <div class="value">₹<?= number_format($cashIncome, 2) ?></div>
            </div>

            <div class="summary-card transfer">
                <h3>Cash to BANK Transfers</h3>
                <div class="value">₹<?= number_format($cashToUpi, 2) ?></div>
            </div>

            <div class="summary-card income">
                <h4>Cash Amount After Bank Transfer</h4>
                 
                <div class="value">₹<?= number_format($cashIncome - $cashToUpi, 2) ?></div>
                <div class="sub-detail">
                    <i class="fas fa-info-circle"></i> 
                    (Total Cash Sales - Cash Transfer to Bank)
                </div>
            </div>

            <div class="summary-card income">
                <h3>Total UPI Sales + Cash Transfer To Bank</h3>
                <div class="value">₹<?= number_format($upiIncome, 2) ?></div>
                <div class="sub-detail">

                    <?php foreach ($upiAccountDetails as $account => $amount): ?>
                        <i class="fas fa-user-circle"></i> <?= htmlspecialchars($account) ?>: 
                        ₹<?= number_format($amount, 2) ?><br>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="summary-card expense">
                <h3>Total UPI Expenses</h3>
                <div class="value">₹<?= number_format($upiExpense, 2) ?></div>
            </div>

            <div class="summary-card">
                <h3>Current UPI Balance</h3>
                <div class="value">₹<?= number_format($netUpiFlow, 2) ?></div>
                <div class="sub-detail">
                    <i class="fas fa-info-circle"></i> 
                    (Total UPI Sales - Total UPI Expenses - UPI Settlements + Cash to UPI Transfers )
                </div>
            </div>

            <div class="summary-card expense">
                <h3>Total Cash Expenses</h3>
                <div class="value">₹<?= number_format($cashExpense, 2) ?></div>
            </div>

            <div class="summary-card">
                <h3>Current Cash Balance</h3>
                <div class="value">₹<?= number_format($netCashFlow, 2) ?></div>
                <div class="sub-detail">
                    <i class="fas fa-info-circle"></i> 
                    (Total Cash Sales - Cash Expenses - Cash to UPI Transfers)
                </div>
            </div>
            
            <div class="summary-card settlement">
                <h3>Cash Settlements</h3>
                <div class="value">₹<?= number_format($settlementCashOut, 2) ?></div>
                <div class="sub-detail">
                    <i class="fas fa-arrow-down text-danger"></i> Paid: ₹<?= number_format($settlementCashOut, 2) ?>
                </div>
            </div>
            
            <div class="summary-card settlement">
                <h3>UPI Settlements</h3>
                <div class="value">₹<?= number_format( $settlementUpiOut, 2) ?></div>
                <div class="sub-detail">
                    <i class="fas fa-arrow-down text-danger"></i> Paid: ₹<?= number_format($settlementUpiOut, 2) ?>
                </div>
            </div>
            
            
            <div class="summary-card expense">
                <h3>Total Expenses</h3>
                <div class="value">₹<?= number_format($totalExpense, 2) ?></div>
                <div class="sub-detail">
                    <i class="fas fa-info-circle"></i> 
                    (UPI + Cash Expenses)
                </div>
            </div>
        </div>
    </div>

    <div class="booking-summary-section">
    <h3 class="section-title">Booking Summary</h3>
    
    <div class="summary-grid">
        <!-- Buffet Booking Card -->
        <div class="summary-card buffet-card">
            <div class="card-header">
                <i class="fas fa-utensils"></i>
                <h3>Buffet Bookings</h3>
            </div>
            <div class="card-body">
                <div class="stat-row">
                    <span class="stat-label">Total Bookings:</span>
                    <span class="stat-value"><?= $buffetCount ?></span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Advance Paid:</span>
                    <span class="stat-value">₹<?= number_format($buffetAdvanceAmount, 2) ?></span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Final Paid:</span>
                    <span class="stat-value">₹<?= number_format($buffetFinalAmount, 2) ?></span>
                </div>
                <div class="stat-row total-row">
                    <span class="stat-label">Total Revenue: Cash (<?= $bookingAdvanceAmountCash  + $bookingFinalAmountCash?>) | UPI (<?= $bookingAdvanceAmountUPI + $bookingFinalAmountUPI ?>)</span>
                    <span class="stat-value">₹<?= number_format($buffetAdvanceAmount + $buffetFinalAmount, 2)  ?></span>
                </div>
            </div>
        </div>

        <!-- Menu Booking Card -->
        <div class="summary-card menu-card">
            <div class="card-header">
                <i class="fas fa-list-alt"></i>
                <h3>Menu Bookings</h3>
            </div>
            <div class="card-body">
                <div class="stat-row">
                    <span class="stat-label">Total Bookings:</span>
                    <span class="stat-value"><?= $menuCount ?></span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Advance Paid:</span>
                    <span class="stat-value">₹<?= number_format($menuAdvanceAmount, 2) ?></span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Final Paid:</span>
                    <span class="stat-value">₹<?= number_format($menuFinalAmount, 2) ?></span>
                </div>
                <div class="stat-row total-row">
                    <span class="stat-label">Total Revenue:</span>
                    <span class="stat-value">₹<?= number_format($menuAdvanceAmount + $menuFinalAmount, 2) ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

    <div class="cumulative-section">
        <h3 class="section-title">Cumulative Report Till All Time</h3>
        <div class="cumulative-cards">
            <div class="summary-card">
                <h3>Total Sales</h3>
                <div class="value">₹<?= number_format($cumulativeIncomeTotal, 2) ?></div>
                <div class="sub-detail">
                    <i class="fas fa-info-circle"></i> 
                    (UPI + Cash Sales)
                </div>
            </div>
            <div class="summary-card">
                <h3>Total Expense</h3>
                <div class="value">₹<?= number_format($cumulativeExpenseTotal, 2) ?></div>
                <div class="sub-detail">
                    <i class="fas fa-info-circle"></i> 
                    (UPI + Cash Expenses)
                </div>
            </div>
            <div class="summary-card settlement">
                <h3>Net Settlements</h3>
                <div class="value">₹<?= number_format( ($cumulativeSettlementCashOut + $cumulativeSettlementUpiOut), 2) ?></div>
                <div class="sub-detail">
                  
                    <i class="fas fa-arrow-down text-danger"></i> Paid: ₹<?= number_format($cumulativeSettlementCashOut + $cumulativeSettlementUpiOut, 2) ?>
                </div>
            </div>
            <div class="summary-card balance">
                <h3>Cumulative Balance</h3>
                <div class="value">₹<?= number_format($cumulativeBalance, 2) ?></div>
                <div class="sub-detail">
                    <i class="fas fa-info-circle"></i> 
                    (UPI + Cash)
                </div>
            </div>
        </div>
    </div>

    <h3 class="section-title">Expense Details</h3>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Expense</th>
                <th>Person</th>
                <th>Amount</th>
                <th>Payment Mode</th>
                <th>UPI Account</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($expenses): ?>
                <?php foreach ($expenses as $i => $exp): ?>
                    <tr>
                        <td><?= date('M j, Y', strtotime($exp['expense_time'])) ?></td>
                        <td><?= htmlspecialchars($exp['expense_name']) ?></td>
                        <td><?= htmlspecialchars($exp['person_name']) ?></td>
                        <td>₹<?= number_format($exp['amount'], 2) ?></td>
                        <td class="mode-cell">
                            <?php if ($exp['payment_mode'] === 'Cash'): ?>
                                <i class="fas fa-money-bill-wave mode-cash"></i>
                                <span class="mode-cash">Cash</span>
                            <?php else: ?>
                                <i class="fas fa-mobile-alt mode-upi"></i>
                                <span class="mode-upi">UPI</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $exp['payment_mode'] === 'UPI' ? htmlspecialchars($exp['upi_account'] ?? 'N/A') : 'N/A' ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" style="text-align: center; padding: 30px;">
                        No expenses found
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <h3 class="section-title">Fund Transfer Details</h3>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Transfer Type</th>
                <th>Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($transfers): ?>
                <?php foreach ($transfers as $transfer): ?>
                    <tr>
                        <td><?= date('M j, Y', strtotime($transfer['transfer_time'])) ?></td>
                        <td>
                            <div class="transfer-type <?= $transfer['from_account'] === 'Cash' ? 'cash' : 'bank' ?>">
                                <span>
                                    <?php if ($transfer['from_account'] === 'Cash'): ?>
                                        <i class="fas fa-money-bill-wave transfer-icon"></i>
                                        Cash
                                    <?php else: ?>
                                        <i class="fas fa-university transfer-icon"></i>
                                        BANK 
                                    <?php endif; ?>
                                </span>
                                <span class="arrow-icon"><i class="fas fa-arrow-right"></i></span>
                                <span>
                                    <?php if ($transfer['to_account'] === 'Cash'): ?>
                                        <i class="fas fa-money-bill-wave transfer-icon"></i>
                                        Cash
                                    <?php else: ?>
                                        <i class="fas fa-university transfer-icon"></i>
                                        BANK (<?= htmlspecialchars($transfer['upi_account']) ?>)
                                    <?php endif; ?>
                                </span>
                            </div>
                        </td>
                        <td>₹<?= number_format($transfer['amount'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="3" class="no-transfers">
                        <i class="fas fa-exchange-alt"></i>
                        <h3>No Transfers Recorded</h3>
                        <p>You haven't made any transfers between accounts yet. All transfers will appear here once recorded.</p>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    
    <h3 class="section-title">Settlement Details</h3>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Transaction</th>
                <th>Amount</th>
                <th>Mode</th>
                <th>UPI Account</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($settlements): ?>
                <?php foreach ($settlements as $settlement): 
                    $isBusinessGiver = ($settlement['giver_name'] === 'BTS Business');
                    $isBusinessReceiver = ($settlement['receiver_name'] === 'BTS Business');
                ?>
                    <tr>
                        <td><?= date('M j, Y', strtotime($settlement['settlement_date'])) ?></td>
                        <td>
                            <div class="settlement-direction">
                                <?php if ($isBusinessGiver): ?>
                                    <span class="settlement-out">
                                        <i class="fas fa-arrow-up"></i> Paid to <?= htmlspecialchars($settlement['receiver_name']) ?>
                                    </span>
                                <?php elseif ($isBusinessReceiver): ?>
                                    <span class="settlement-in">
                                        <i class="fas fa-arrow-down"></i> Received from <?= htmlspecialchars($settlement['giver_name']) ?>
                                    </span>
                                <?php else: ?>
                                    <?= htmlspecialchars($settlement['giver_name']) ?> 
                                    <i class="fas fa-arrow-right"></i> 
                                    <?= htmlspecialchars($settlement['receiver_name']) ?>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>₹<?= number_format($settlement['amount'], 2) ?></td>
                        <td class="mode-cell">
                            <?php if ($settlement['payment_mode'] === 'CASH'): ?>
                                <i class="fas fa-money-bill-wave mode-cash"></i>
                                <span class="mode-cash">Cash</span>
                            <?php elseif ($settlement['payment_mode'] === 'UPI'): ?>
                                <i class="fas fa-mobile-alt mode-upi"></i>
                                <span class="mode-upi">UPI</span>
                            <?php else: ?>
                                <i class="fas fa-file-invoice"></i>
                                <span>Cheque</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($settlement['payment_mode'] === 'UPI'): ?>
                                <?= htmlspecialchars($settlement['giver_upi_account'] ?? 'N/A') ?>
                            <?php else: ?>
                                N/A
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($settlement['description'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" class="no-transfers">
                        <i class="fas fa-handshake"></i>
                        <h3>No Settlements Recorded</h3>
                        <p>You haven't recorded any settlements yet. All settlements will appear here once recorded.</p>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    
    <h3 class="section-title">UPI Account Details</h3>
    <table>
        <thead>
            <tr>
                <th>Account</th>
                <th>Income</th>
                <th>Expenses</th>
                <th>Settlements Paid</th>
                <th>Balance</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $totalIncomeUPI = 0;
            $totalExpenseUPI = 0;
            $totalSettlementsUPI = 0;
            $totalBalanceUPI = 0;
            
            foreach ($upiAccountDetails as $account => $income): 
                $expense = $upiAccountExpenses[$account] ?? 0;
                $paid = $upiAccountSettlements[$account] ?? 0;
                $balance = $income - $expense - $paid;

                $totalIncomeUPI += $income;
                $totalExpenseUPI += $expense;
                $totalSettlementsUPI += $paid;
                $totalBalanceUPI += $balance;
            ?>
                <tr>
                    <td><?= htmlspecialchars($account) ?></td>
                    <td>₹<?= number_format($income, 2) ?></td>
                    <td>₹<?= number_format($expense, 2) ?></td>
                    <td>₹<?= number_format($paid, 2) ?></td>
                    <td class="<?= $balance >= 0 ? 'account-balance-positive' : 'account-balance-negative' ?>">
                        ₹<?= number_format($balance, 2) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <tr style="font-weight: bold;">
                <td>Total</td>
                <td>₹<?= number_format($totalIncomeUPI, 2) ?></td>
                <td>₹<?= number_format($totalExpenseUPI, 2) ?></td>
                <td>₹<?= number_format($totalSettlementsUPI, 2) ?></td>
                <td class="<?= $totalBalanceUPI >= 0 ? 'account-balance-positive' : 'account-balance-negative' ?>">
                    ₹<?= number_format($totalBalanceUPI, 2) ?>
                </td>
            </tr>
        </tbody>
    </table>
    
    <footer>
        <p>
            Report Mode: <strong><?= ucfirst($report_mode) ?></strong> | 
            Date Range: <strong><?= $dateRangeText ?></strong> | 
            Generated on <?= date('l, F j, Y, g:i a') ?>
        </p>
    </footer>
</div>

<script>
function changeDate() {
    const date = document.getElementById('reportDate').value;
    window.location.href = `?date=${date}&mode=<?= $report_mode ?>`;
}
</script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
    function downloadPDF() {
        const element = document.getElementById("printArea");
        const reportType = "<?= $report_mode ?>";
        const dateRange = "<?= $dateRangeText ?>";
        const filename = `Financial-Report-${reportType}-${dateRange.replace(/ /g, '-')}.pdf`;
        
        // Show loading indicator
        const btn = document.querySelector('.btn-pdf');
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating PDF...';
        btn.disabled = true;
        
        // A4 PDF configuration
        const opt = {
            margin: [10, 5, 10, 5], // Top, Right, Bottom, Left (in mm)
            filename: filename,
            image: { 
                type: 'jpeg', 
                quality: 0.98 
            },
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
            pagebreak: { 
                mode: ['avoid-all', 'css', 'legacy'],
            }
        };
        
        // Generate PDF
        setTimeout(() => {
            html2pdf().set(opt).from(element).save().then(() => {
                btn.innerHTML = originalHtml;
                btn.disabled = false;
            });
        }, 500);
    }
</script>
</body>
</html>