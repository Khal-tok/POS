<?php
session_start();
include 'db.php'; 
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] != 'barista') {
    header("location: login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['clear_cart'])) {
    
    if (isset($_SESSION['cart'])) {
        unset($_SESSION['cart']);
        $_SESSION['cart'] = []; 
        $_SESSION['message_success'] = "Cart cleared successfully.";
    } else {
        $_SESSION['message_error'] = "Cart was already empty.";
    }

    header("location: barista_dashboard.php");
    exit;
}

header("location: barista_dashboard.php");
exit;
?>