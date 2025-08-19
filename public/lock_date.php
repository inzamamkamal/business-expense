<?php
include '../includes/session.php';
require '../config/db.php';
require '../includes/functions.php';

$user_id = $_SESSION['user_id'];
$date = $_POST['date'] ?? date('Y-m-d');

// Prevent double lock
if (isDateLocked($pdo, $user_id, $date)) {
    header("Location: report.php?date=" . $date);
    exit;
}

// Fetch startBalance from last lock
$lockStmt = $pdo->prepare("SELECT closing_amount FROM locks WHERE locked_date < ? ORDER BY locked_date DESC LIMIT 1");
$lockStmt->execute([$date]);
$startBalance = $lockStmt->fetchColumn();
$startBalance = $startBalance !== false ? floatval($startBalance) : 0;

// Get income
$incomeStmt = $pdo->prepare("SELECT SUM(upi_amount) AS upi, SUM(cash_amount) AS cash FROM income WHERE DATE(income_time) = ?");
$incomeStmt->execute([$date]);
$income = $incomeStmt->fetch();
$upi = $income['upi'] ?: 0;
$cash = $income['cash'] ?: 0;

// Get expenses
$expenseStmt = $pdo->prepare("SELECT SUM(amount) FROM expenses WHERE DATE(expense_time) = ?");
$expenseStmt->execute([$date]);
$expenseTotal = $expenseStmt->fetchColumn() ?: 0;

// Calculate closing balance
$closingAmount = $startBalance + $upi + $cash - $expenseTotal;

// Store lock record
$insert = $pdo->prepare("INSERT INTO locks (user_id, locked_date, closing_amount) VALUES (?, ?, ?)");
$insert->execute([$user_id, $date, $closingAmount]);

header("Location: add_income.php");
exit;