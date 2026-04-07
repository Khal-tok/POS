<?php
session_start();
include 'db.php';

// Authorization Check
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] != 'rider') {
    header("location: login.php");
    exit;
}

// Get the correct Rider ID
$rider_id = $_SESSION['user_id'] ?? $_SESSION['id'];

// --- THE FIX ---
// We use a PREPARED STATEMENT because of the '?'
$sql = "SELECT o.*, u.username AS customer_name, u.contact_number 
        FROM online_orders o 
        LEFT JOIN users u ON o.user_id = u.id 
        WHERE o.rider_id = ? 
        AND (o.delivery_status = 'Delivered' OR o.status = 'Completed')
        ORDER BY o.order_date DESC";

$stmt = mysqli_prepare($conn, $sql);

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $rider_id); // Bind the ID safely
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    // If the query fails, show the error so we can fix it
    die("SQL Error: " . mysqli_error($conn));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery History</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #f4f7f6; padding: 40px; }
        .container { max-width: 1100px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        h2 { color: #1a237e; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .back-link { text-decoration: none; color: #1565c0; font-weight: bold; margin-bottom: 20px; display: inline-block; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th { background: #e3f2fd; color: #1565c0; padding: 12px; text-align: left; }
        td { padding: 12px; border-bottom: 1px solid #f1f1f1; color: #444; }
        
        .badge { padding: 5px 10px; border-radius: 15px; font-size: 12px; font-weight: bold; }
        .badge-success { background: #d4edda; color: #155724; }
    </style>
</head>
<body>

<div class="container">
    <a href="delivery_module.php" class="back-link">← Back to Dashboard</a>
    <h2>My Delivery History</h2>

    <table>
        <thead>
            <tr>
                <th>Order ID</th>
                <th>Date</th>
                <th>Customer</th>
                <th>Address</th>
                <th>Total</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if (mysqli_num_rows($result) > 0): ?>
                <?php while($row = mysqli_fetch_assoc($result)): ?>
                <tr>
                    <td><?php echo $row['id']; ?></td>
                    <td>
                        <?php 
                        // Safe date formatting
                        echo isset($row['order_date']) ? date("M d, Y h:i A", strtotime($row['order_date'])) : 'N/A'; 
                        ?>
                    </td>
                    <td>
                        <?php 
                        // Fallback name logic if customer_name is missing from query
                        echo htmlspecialchars($row['customer_name'] ?? $row['username'] ?? 'Guest'); 
                        ?>
                    </td>
                    <td>
                        <?php 
                        // Fallback address logic
                        echo htmlspecialchars($row['delivery_address'] ?? $row['address'] ?? 'N/A'); 
                        ?>
                    </td>
                    <td>₱<?php echo number_format($row['total_amount'], 2); ?></td>
                    <td><span class="badge badge-success"><?php echo $row['status']; ?></span></td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="6" style="text-align:center; padding: 20px;">No completed deliveries found in history.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>