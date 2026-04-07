<?php
session_start();
include 'db.php';
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['loggedin']) || empty($_SESSION['online_cart'])) {
    header("location: customer_dashboard.php");
    exit;
}

$username = $_SESSION['username'];
$stmt_u = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ?");
mysqli_stmt_bind_param($stmt_u, "s", $username);
mysqli_stmt_execute($stmt_u);
$user_id = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_u))['id'];

$address = mysqli_real_escape_string($conn, $_POST['delivery_address']);
$contact = mysqli_real_escape_string($conn, $_POST['contact_number']);
$notes = mysqli_real_escape_string($conn, $_POST['customer_notes']);
$total = floatval($_POST['total_amount']);

mysqli_query($conn, "UPDATE users SET contact_number = '$contact' WHERE id = $user_id");

$sql = "INSERT INTO online_orders (user_id, total_amount, delivery_address, customer_notes, payment_method, status) 
        VALUES ($user_id, $total, '$address', '$notes', 'Cash on Delivery', 'Pending')";

if(mysqli_query($conn, $sql)) {
    $order_id = mysqli_insert_id($conn);
    
    $sql_items = "INSERT INTO online_order_items (order_id, product_id, quantity, price, size, temp, ice_level, addons_summary) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt_items = mysqli_prepare($conn, $sql_items);

    foreach($_SESSION['online_cart'] as $item) {
        $pid = $item['id'] ?? $item['product_id'] ?? 0;
        $qty = $item['qty'] ?? $item['quantity'] ?? 1;
        $price = $item['price'] ?? 0;
        $size = $item['size'] ?? 'Standard';
        $temp = $item['temp'] ?? 'N/A';
        $ice = $item['ice'] ?? 'N/A';
        $addons = isset($item['addons']) ? implode(", ", $item['addons']) : "";

        mysqli_stmt_bind_param($stmt_items, "iiidssss", $order_id, $pid, $qty, $price, $size, $temp, $ice, $addons);
        mysqli_stmt_execute($stmt_items);

        mysqli_query($conn, "UPDATE products SET stock = stock - $qty WHERE id = $pid");
    }
    
    unset($_SESSION['online_cart']);
    header("location: customer_order_history.php?success=1");
    exit;
} else {
    die("Fatal Database Error: " . mysqli_error($conn));
}