<?php
session_start();
include 'db.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['role'] != 'barista') {
    header("location: login.php");
    exit;
}

$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
$message = "";

if ($order_id == 0) { die("Invalid Order ID."); }

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_status'])) {
    $new_status = $_POST['status'];
    $prepared_by_id = $_SESSION['user_id'] ?? 0; 

    // We save the Barista's ID for ANY status update that isn't Canceled
    // This ensures that even if they go straight to 'Completed', they get credit for the sale.
    if ($new_status != 'Canceled') {
        $sql_update = "UPDATE online_orders SET status = ?, prepared_by = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql_update);
        mysqli_stmt_bind_param($stmt, "sii", $new_status, $prepared_by_id, $order_id);
    } else {
        $sql_update = "UPDATE online_orders SET status = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql_update);
        mysqli_stmt_bind_param($stmt, "si", $new_status, $order_id);
    }

    if (mysqli_stmt_execute($stmt)) {
        $message = "Order status updated successfully!";
    }
}

$query_order = "SELECT o.*, u.username, u.contact_number 
                FROM online_orders o 
                JOIN users u ON o.user_id = u.id 
                WHERE o.id = $order_id";
$result_order = mysqli_query($conn, $query_order);
$order = mysqli_fetch_assoc($result_order);

if (!$order) { die("Order not found."); }

$query_items = "SELECT oi.*, p.name FROM online_order_items oi 
                JOIN products p ON oi.product_id = p.id 
                WHERE oi.order_id = $order_id";
$result_items = mysqli_query($conn, $query_items);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fulfillment #<?php echo $order_id; ?> | Barista</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --p-brown: #5D4037; --d-brown: #3E2723; --bg: #f4f7f6; --white: #ffffff; }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Poppins', sans-serif; }
        body { background: var(--bg); font-size: 15px; color: #333; padding: 25px; }
        
        .top-bar { max-width: 1000px; margin: 0 auto 25px; display: flex; justify-content: space-between; align-items: center; }
        .back-link { text-decoration: none; color: var(--p-brown); font-weight: 600; font-size: 0.9rem; transition: 0.3s; }

        .dashboard-grid { max-width: 1000px; margin: 0 auto; display: grid; grid-template-columns: 1fr 340px; gap: 25px; }
        .card { background: var(--white); border-radius: 16px; padding: 25px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); margin-bottom: 25px; }
        .card-header { font-size: 1.1rem; font-weight: 700; color: var(--p-brown); border-bottom: 1.5px solid #f0f0f0; padding-bottom: 15px; margin-bottom: 20px; }

        .item-row { display: flex; justify-content: space-between; align-items: flex-start; padding: 12px 0; border-bottom: 1px solid #f8f8f8; }
        .qty-badge { background: #EFEBE9; color: var(--p-brown); padding: 4px 10px; border-radius: 6px; font-weight: 700; margin-right: 12px; }
        .item-info { flex: 1; }
        .item-name { font-weight: 600; color: var(--d-brown); display: block; }
        .item-meta { font-size: 0.8rem; color: #888; font-style: italic; }

        .info-group { margin-bottom: 20px; }
        .info-group label { display: block; font-size: 0.75rem; color: #999; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; }
        .info-group p { font-weight: 500; font-size: 0.95rem; }

        .status-pill { padding: 6px 14px; border-radius: 20px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; background: #FFF3E0; color: #E65100; }
        .status-prepared { background: #E8F5E9; color: #2E7D32; }

        .action-select { width: 100%; padding: 12px; border: 1.5px solid #eee; border-radius: 10px; font-size: 0.9rem; outline: none; margin-bottom: 15px; background: #fafafa; }
        .btn-update { width: 100%; background: var(--p-brown); color: white; border: none; padding: 14px; border-radius: 10px; font-weight: 700; cursor: pointer; transition: 0.3s; }
        .btn-update:hover { background: var(--d-brown); }

        .toast { background: #2E7D32; color: white; padding: 15px; border-radius: 10px; margin-bottom: 20px; text-align: center; }
        .notes-box { background: #fdf6f2; border-left: 4px solid var(--p-brown); padding: 15px; border-radius: 8px; font-style: italic; color: #6d4c41; }
    </style>
</head>
<body>

<div class="top-bar">
    <a href="barista_dashboard.php" class="back-link">← Return to Dashboard</a>
    <div class="status-pill <?php echo ($order['status'] == 'Prepared') ? 'status-prepared' : ''; ?>">
        <?php echo $order['status']; ?>
    </div>
</div>

<?php if($message): ?>
    <div class="toast"><?php echo $message; ?></div>
<?php endif; ?>

<div class="dashboard-grid">
    <div class="left-col">
        <div class="card">
            <div class="card-header">☕ Items to Prepare</div>
            <?php while($item = mysqli_fetch_assoc($result_items)): ?>
            <div class="item-row">
                <span class="qty-badge"><?php echo $item['quantity']; ?>x</span>
                <div class="item-info">
                    <span class="item-name"><?php echo htmlspecialchars($item['name']); ?></span>
                    <span class="item-meta">
                        Size: <?php echo htmlspecialchars($item['size'] ?? 'Standard'); ?> 
                        <?php if(!empty($item['temp'])) echo " | Temp: ".htmlspecialchars($item['temp']); ?>
                    </span>
                </div>
                <div style="font-weight: 700; color: var(--p-brown);">
                    ₱ <?php echo number_format($item['price'] * $item['quantity'], 2); ?>
                </div>
            </div>
            <?php endwhile; ?>
            <div style="margin-top: 25px; text-align: right; font-size: 1.3rem; font-weight: 700; color: var(--d-brown);">
                Total: ₱ <?php echo number_format($order['total_amount'], 2); ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header">📍 Customer & Logistics</div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="info-group">
                    <label>Customer Name</label>
                    <p><?php echo htmlspecialchars($order['username']); ?></p>
                </div>
                <div class="info-group">
                    <label>Contact Number</label>
                    <p><?php echo htmlspecialchars($order['contact_number']); ?></p>
                </div>
            </div>
            <div class="info-group">
                <label>Delivery Address</label>
                <p><?php echo htmlspecialchars($order['delivery_address'] ?? 'No address provided'); ?></p>
            </div>
            <div class="info-group">
                <label>Customer Request / Notes</label>
                <div class="notes-box">
                    "<?php echo htmlspecialchars($order['customer_notes'] ?? 'No special requests.'); ?>"
                </div>
            </div>
        </div>
    </div>

    <div class="right-col">
        <div class="card" style="position: sticky; top: 25px;">
            <div class="card-header">⚙️ Update Status</div>
            <form method="POST">
                <select name="status" class="action-select">
                    <option value="Pending" <?php if($order['status'] == 'Pending') echo 'selected'; ?>>Pending</option>
                    <option value="Prepared" <?php if($order['status'] == 'Prepared') echo 'selected'; ?>>Prepared (Ready)</option>
                    <option value="Out for Delivery" <?php if($order['status'] == 'Out for Delivery') echo 'selected'; ?>>Out for Delivery</option>
                    <option value="Completed" <?php if($order['status'] == 'Completed') echo 'selected'; ?>>Completed</option>
                    <option value="Canceled" <?php if($order['status'] == 'Canceled') echo 'selected'; ?>>Canceled</option>
                </select>
                <button type="submit" name="update_status" class="btn-update">Save Changes</button>
            </form>

            <div style="margin-top: 30px; border-top: 1px solid #eee; padding-top: 20px;">
                <div class="info-group">
                    <label>Payment Method</label>
                    <p style="color: var(--p-brown); font-weight: 700;"><?php echo htmlspecialchars($order['payment_method'] ?? 'COD'); ?></p>
                </div>
                <div class="info-group">
                    <label>Order Stamp</label>
                    <p style="font-size: 0.85rem;"><?php echo isset($order['order_time']) ? date('M d, Y | h:i A', strtotime($order['order_time'])) : 'N/A'; ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>