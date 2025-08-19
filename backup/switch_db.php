<?php
session_start();

if (isset($_POST['selected_db'])) {
    $_SESSION['selected_db'] = $_POST['selected_db'];
    $_SESSION['db_mode'] = 'custom'; // prevent auto override
}

// Redirect back to dashboard
header("Location: dashboard.php");
exit();
