<?php
session_start();
include 'db.php';

if (!isset($_SESSION['loggedin'])) { header("location: customer_login.php"); exit; }

$user_id = 0;
// Fetch user ID safely
$stmt_u = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ?");
mysqli_stmt_bind_param($stmt_u, "s", $_SESSION['username']);
mysqli_stmt_execute($stmt_u);
$res_u = mysqli_stmt_get_result($stmt_u);
if($r = mysqli_fetch_assoc($res_u)) { $user_id = $r['id']; }

$address = mysqli_real_escape_string($conn, $_POST['delivery_address']);
$contact = mysqli_real_escape_string($conn, $_POST['contact_number']);
$notes = mysqli_real_escape_string($conn, $_POST['customer_notes']);
$total = $_POST['total_amount'];

// Update Contact
mysqli_query($conn, "UPDATE users SET contact_number = '$contact' WHERE id = $user_id");

// Insert Order
$sql = "INSERT INTO online_orders (user_id, total_amount, delivery_address, customer_notes, payment_method, status) VALUES ($user_id, $total, '$address', '$notes', 'Cash on Delivery', 'Pending')";

if(mysqli_query($conn, $sql)) {
    $order_id = mysqli_insert_id($conn);
    
    $stmt = mysqli_prepare($conn, "INSERT INTO online_order_items (order_id, product_id, quantity, price, size, temp, ice_level, addons_summary) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    
    foreach($_SESSION['online_cart'] as $item) {
        // FIX: Map the session keys ('id', 'qty') to DB expectations
        $pid = isset($item['id']) ? $item['id'] : (isset($item['product_id']) ? $item['product_id'] : 0);
        $qty = isset($item['qty']) ? $item['qty'] : (isset($item['quantity']) ? $item['quantity'] : 1);
        
        $addons_str = isset($item['addons']) ? implode(", ", $item['addons']) : "";
        
        mysqli_stmt_bind_param($stmt, "iiidssss", $order_id, $pid, $qty, $item['price'], $item['size'], $item['temp'], $item['ice'], $addons_str);
        mysqli_stmt_execute($stmt);
    }
    
    $_SESSION['online_cart'] = []; 
    echo "<script>alert('Order Placed Successfully!'); window.location.href='customer_order_history.php';</script>";
} else {
    echo "Error: " . mysqli_error($conn);
}
?>