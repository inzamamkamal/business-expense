<?php
session_start();
date_default_timezone_set('Asia/Kolkata');
if (!isset($_SESSION['user_id']) && isset($_SESSION['username']) && $_SESSION['role'] !== '') { 
    header("Location: index.php");
    exit();
}
