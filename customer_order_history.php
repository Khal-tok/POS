<?php
session_start();
include 'db.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['role'] != 'customer') {
    header("location: customer_login.php");
    exit;
}

$username = $_SESSION['username'];
// Securely fetch user ID
$stmt_user = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ?");
mysqli_stmt_bind_param($stmt_user, "s", $username);
mysqli_stmt_execute($stmt_user);
$result_user = mysqli_stmt_get_result($stmt_user);
$user_row = mysqli_fetch_assoc($result_user);
$user_id = $user_row['id'];
mysqli_stmt_close($stmt_user);

$sql_orders = "SELECT * FROM online_orders WHERE user_id = ? ORDER BY order_date DESC";
$stmt_orders = mysqli_prepare($conn, $sql_orders);
mysqli_stmt_bind_param($stmt_orders, "i", $user_id);
mysqli_stmt_execute($stmt_orders);
$result_orders = mysqli_stmt_get_result($stmt_orders);
mysqli_stmt_close($stmt_orders);
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Order History</title>
    <link rel="stylesheet" href="customer_order.css">
</head>
<body>
<div class="container">
    <h2>📜 Your Order History</h2>
    
    <div class="nav-links">
        <a href="customer_dashboard.php">← Back to Menu</a> | 
        <a href="logout_customer.php">Logout</a>
    </div>

    <?php if (isset($_SESSION['message_success'])): ?>
        <p class="message-success"><?php echo $_SESSION['message_success']; unset($_SESSION['message_success']); ?></p>
    <?php endif; ?>

    <?php if (mysqli_num_rows($result_orders) == 0): ?>
        <div style="text-align:center; padding:40px;">
            <p>You haven't placed any orders yet.</p>
            <a href="customer_dashboard.php" class="btn">Start Shopping Now</a>
        </div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Date</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Details</th>
                    <th>QR Code</th> 
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while($order = mysqli_fetch_assoc($result_orders)): 
                    $status_slug = strtolower(str_replace(' ', '-', $order['status']));
                ?>
                <tr>
                    <td><strong>#<?php echo $order['id']; ?></strong></td>
                    <td><?php echo date('M d, Y h:i A', strtotime($order['order_date'])); ?></td>
                    <td>₱ <?php echo number_format($order['total_amount'], 2); ?></td>
                    <td>
                        <span class="status-badge status-<?php echo $status_slug; ?>">
                            <?php echo htmlspecialchars($order['status']); ?>
                        </span>
                    </td>
                    <td>
                        <a href="customer_order_details.php?order_id=<?php echo $order['id']; ?>" class="action-link link-view">View</a>
                    </td>
                    <td>
                        <?php if ($order['status'] != 'Canceled'): ?>
                            <a href="generate_qr.php?order_id=<?php echo $order['id']; ?>" class="action-link link-qr" target="_blank">Get QR</a>
                        <?php else: ?>
                            <span style="color:#aaa;">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($order['status'] == 'Pending'): ?>
                            <a href="cancel_order.php?order_id=<?php echo $order['id']; ?>" class="action-link link-cancel" onclick="return confirm('Are you sure you want to cancel this order?');">Cancel</a>
                        <?php else: ?>
                            <span style="color:#aaa;">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
</body>
</html>