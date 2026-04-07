<?php
session_start();
include 'db.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['role'] != 'customer') {
    header("location: customer_login.php");
    exit;
}

$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
$username = $_SESSION['username'];

$sql = "SELECT o.id, o.status, u.id as user_id 
        FROM online_orders o 
        JOIN users u ON o.user_id = u.id 
        WHERE o.id = ? AND u.username = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "is", $order_id, $username);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$order = mysqli_fetch_assoc($result);

if (!$order) { die("Order not found."); }

// Generate QR Link
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$path = dirname($_SERVER['PHP_SELF']); 
$scan_url = "$protocol://$host$path/delivery_process.php?order_id=" . $order_id;
$qr_image = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($scan_url);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Order #<?php echo $order_id; ?> QR Code</title>
    <link rel="stylesheet" href="customer_order.css">
</head>
<body>
<div class="container" style="text-align:center;">
    <h2>📱 Digital Order Ticket</h2>
    
    <div class="qr-container">
        <h3 style="margin-top:0; border:none;">Order #<?php echo $order_id; ?></h3>
        <p style="color:#555; font-size:0.9em;">Show this to the Rider or Barista</p>
        
        <img src="<?php echo $qr_image; ?>" alt="QR Code">
        
        <div style="margin-top:20px;">
            <span class="status-badge status-<?php echo strtolower($order['status']); ?>">
                <?php echo htmlspecialchars($order['status']); ?>
            </span>
        </div>
    </div>

    <p><a href="customer_order_history.php">← Back to History</a></p>
</div>
</body>
</html>