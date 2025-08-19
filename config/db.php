<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = 'localhost';
$user = 'mablmoov_admin';
$pass = 'segMASZc8z3g_d5';
$charset = 'utf8mb4';
$db = 'mablmoov_bts2app_new'; // Default DB, can be overridden by session

// Use session-based DB selection
// if (isset($_SESSION['selected_db']) && $_SESSION['db_mode'] === 'custom') {
//     $db = $_SESSION['selected_db'];
// } else {
//     $mode = $_SESSION['db_mode'] ?? 'previous';

//     if ($mode === 'current') {
//         $month = date('n');
//         $year  = date('Y');
//     } else {
//         $month = date('n', strtotime('-1 month'));
//         $year  = date('Y', strtotime('-1 month'));
//     }

//     $db = "btsapp_{$month}_{$year}";
//     $_SESSION['selected_db'] = $db;
// }

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    $pdo->exec("SET time_zone = '+05:30'");
} catch (\PDOException $e) {
    die("DB connection failed: " . $e->getMessage());
}
?>
