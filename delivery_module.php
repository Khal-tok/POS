<?php
session_start();
include 'db.php';

// Authorization Check
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] != 'rider') {
    header("location: login.php");
    exit;
}

$rider_id = $_SESSION['user_id'] ?? $_SESSION['id'];
$message = "";

// --- 1. HANDLE "ACCEPT ORDER" ACTION ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accept_order'])) {
    $order_id_to_accept = intval($_POST['order_id']);
    
    // Assign the rider to the order
    $sql_accept = "UPDATE online_orders SET rider_id = ? WHERE id = ? AND rider_id IS NULL";
    if ($stmt = mysqli_prepare($conn, $sql_accept)) {
        mysqli_stmt_bind_param($stmt, "ii", $rider_id, $order_id_to_accept);
        if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) > 0) {
            $message = "Order #$order_id_to_accept accepted successfully!";
        } else {
            $message = "Could not accept order. It may have been taken by another rider.";
        }
        mysqli_stmt_close($stmt);
    }
}

// --- 2. FETCH "AVAILABLE" ORDERS (No Rider Assigned) ---
// FIX: Removed 'u.address'. Only fetching username and contact from users table.
// Address is fetched from online_orders (o.*)
$sql_available = "SELECT o.*, u.username AS customer_name, u.contact_number 
                  FROM online_orders o 
                  LEFT JOIN users u ON o.user_id = u.id 
                  WHERE o.rider_id IS NULL 
                  AND (o.status = 'Out for Delivery' OR o.status = 'Ready for Delivery')
                  ORDER BY o.order_date DESC";
$res_available = mysqli_query($conn, $sql_available);

// --- 3. FETCH "MY ACTIVE" ORDERS (Assigned to Me) ---
// FIX: Removed 'u.address' here too.
$sql_active = "SELECT o.*, u.username AS customer_name, u.contact_number 
               FROM online_orders o 
               LEFT JOIN users u ON o.user_id = u.id 
               WHERE o.rider_id = ? 
               AND o.status = 'Out for Delivery'
               ORDER BY o.order_date ASC";
$stmt_active = mysqli_prepare($conn, $sql_active);
mysqli_stmt_bind_param($stmt_active, "i", $rider_id);
mysqli_stmt_execute($stmt_active);
$res_active = mysqli_stmt_get_result($stmt_active);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rider Dashboard</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #f4f7f6; padding: 20px; color: #333; }
        .container { max-width: 1100px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        h2 { color: #1a237e; border-bottom: 2px solid #eee; padding-bottom: 10px; margin-top: 0; }
        h3 { color: #2e7d32; margin-top: 30px; border-left: 5px solid #2e7d32; padding-left: 10px; }
        .nav-links { margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #eee; }
        .nav-links a { text-decoration: none; color: #1565c0; font-weight: bold; margin-right: 15px; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 15px; background: white; }
        th { background: #e3f2fd; color: #1565c0; padding: 12px; text-align: left; }
        td { padding: 12px; border-bottom: 1px solid #f1f1f1; color: #444; vertical-align: middle; }
        
        .btn-accept { background: #ff9800; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .btn-accept:hover { background: #e65100; }
        
        .btn-scan { background: #28a745; color: white; text-decoration: none; padding: 8px 15px; border-radius: 4px; font-weight: bold; display: inline-block; }
        .btn-scan:hover { background: #218838; }

        .alert { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #bee5eb; }

        @media (max-width: 768px) {
            table, thead, tbody, th, td, tr { display: block; }
            thead tr { position: absolute; top: -9999px; left: -9999px; }
            tr { margin-bottom: 15px; border: 1px solid #ddd; padding: 10px; border-radius: 5px; }
            td { border: none; position: relative; padding-left: 50%; }
            td:before { position: absolute; left: 0; width: 45%; font-weight: bold; content: attr(data-label); }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="nav-links">
        <span style="font-weight:bold; color:#333;">Welcome, Rider!</span> | 
        <a href="delivery_history.php">View History</a> | 
        <a href="logout.php" style="color:red;">Logout</a>
    </div>

    <?php if ($message): ?>
        <div class="alert"><?php echo $message; ?></div>
    <?php endif; ?>

    <h3>🔔 Available for Pickup</h3>
    <?php if (mysqli_num_rows($res_available) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Customer</th>
                    <th>Address</th>
                    <th>Total</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = mysqli_fetch_assoc($res_available)): 
                    // Address logic: Check delivery_address first, then address, then placeholder
                    $addr = !empty($row['delivery_address']) ? $row['delivery_address'] : ($row['address'] ?? 'Tanza Area');
                    $name = !empty($row['customer_name']) ? $row['customer_name'] : ($row['username'] ?? 'Guest');
                ?>
                <tr>
                    <td data-label="Order ID">#<?php echo $row['id']; ?></td>
                    <td data-label="Customer"><?php echo htmlspecialchars($name); ?></td>
                    <td data-label="Address"><?php echo htmlspecialchars($addr); ?></td>
                    <td data-label="Total">₱<?php echo number_format($row['total_amount'], 2); ?></td>
                    <td data-label="Action">
                        <form method="POST">
                            <input type="hidden" name="order_id" value="<?php echo $row['id']; ?>">
                            <button type="submit" name="accept_order" class="btn-accept">✋ Accept Order</button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p style="color:#777; padding:10px;">No pending orders available right now.</p>
    <?php endif; ?>

    <h3 style="color:#1565c0; border-color:#1565c0;">🛵 My Active Deliveries</h3>
    <?php if (mysqli_num_rows($res_active) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Customer</th>
                    <th>Address</th>
                    <th>Total</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = mysqli_fetch_assoc($res_active)): 
                    $addr = !empty($row['delivery_address']) ? $row['delivery_address'] : ($row['address'] ?? 'Tanza Area');
                    $name = !empty($row['customer_name']) ? $row['customer_name'] : ($row['username'] ?? 'Guest');
                ?>
                <tr>
                    <td data-label="Order ID">#<?php echo $row['id']; ?></td>
                    <td data-label="Customer"><?php echo htmlspecialchars($name); ?></td>
                    <td data-label="Address"><?php echo htmlspecialchars($addr); ?></td>
                    <td data-label="Total">₱<?php echo number_format($row['total_amount'], 2); ?></td>
                    <td data-label="Action">
                        <a href="delivery_scan_module.php?order_id=<?php echo $row['id']; ?>" class="btn-scan">📷 SCAN QR</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p style="color:#777; padding:10px;">You have no active deliveries. Accept one from above!</p>
    <?php endif; ?>

</div>

</body>
</html>