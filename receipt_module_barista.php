<?php
session_start();
include 'db.php'; 

if (!isset($_SESSION['loggedin']) || ($_SESSION['role'] != 'barista' && $_SESSION['role'] != 'admin')) {
    die("Unauthorized access.");
}

$order_id = $_GET['id'] ?? 0;
$order_type = $_GET['type'] ?? '';

if (!$order_id || empty($order_type)) {
    die("Error: Missing transaction ID or order type.");
}

$order_details = null;
$item_details = [];
$customer_info = '';
$is_online = ($order_type === 'online');

if ($is_online) {
    // ONLINE ORDER LOGIC
    $sql = "SELECT o.total_amount, o.payment_method, o.status, u.username, 
                   DATE_FORMAT(o.created_at, '%Y-%m-%d %H:%i:%s') AS transaction_date
            FROM online_orders o 
            JOIN users u ON o.user_id = u.id 
            WHERE o.id = ? LIMIT 1";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $order_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $order_details = $row;
        $order_details['amount_paid'] = $row['total_amount']; 
        $order_details['change'] = 0.00; 
        $order_details['order_type'] = 'ONLINE';
        $customer_info = "Customer: " . htmlspecialchars($row['username']);

        $sql_items = "SELECT p.name AS product_name, oi.quantity, oi.price, oi.size, oi.temp, oi.ice_level 
                      FROM online_order_items oi
                      JOIN products p ON oi.product_id = p.id
                      WHERE oi.order_id = ?";
        $stmt_items = mysqli_prepare($conn, $sql_items);
        mysqli_stmt_bind_param($stmt_items, "i", $order_id);
        mysqli_stmt_execute($stmt_items);
        $item_details = mysqli_fetch_all(mysqli_stmt_get_result($stmt_items), MYSQLI_ASSOC);
        mysqli_stmt_close($stmt_items);
    }
    mysqli_stmt_close($stmt);

} elseif ($order_type === 'pos') {
    // POS ORDER LOGIC
    $sql = "SELECT t.total_amount, t.amount_paid, t.order_time AS transaction_date, u.username
            FROM transactions t
            JOIN users u ON t.user_id = u.id
            WHERE t.id = ? LIMIT 1";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $order_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $order_details = $row;
        $order_details['change'] = $row['amount_paid'] - $row['total_amount'];
        $order_details['payment_method'] = 'POS Cash';
        $order_details['order_type'] = 'POS';
        $customer_info = "Barista: " . htmlspecialchars($row['username']);

        $sql_items = "SELECT p.name AS product_name, oi.quantity, oi.price, oi.size, oi.temp, oi.ice_level 
                      FROM order_items oi
                      JOIN products p ON oi.product_id = p.id
                      WHERE oi.transaction_id = ?";
        $stmt_items = mysqli_prepare($conn, $sql_items);
        mysqli_stmt_bind_param($stmt_items, "i", $order_id);
        mysqli_stmt_execute($stmt_items);
        $item_details = mysqli_fetch_all(mysqli_stmt_get_result($stmt_items), MYSQLI_ASSOC);
        mysqli_stmt_close($stmt_items);
    }
    mysqli_stmt_close($stmt);
} else {
    die("Error: Receipt type not supported."); 
}

if (!$order_details) {
    die("Error: Order not found.");
}

// --- VAT CALCULATION ---
$vat_rate = 0.12; 
$total_amount = $order_details['total_amount'];
$vatable_sales = $total_amount / (1 + $vat_rate);
$vat_amount = $total_amount - $vatable_sales;
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo $order_details['order_type']; ?> Receipt #<?php echo $order_id; ?></title>
    <style>
        body { font-family: 'Courier New', Courier, monospace; font-size: 12px; margin: 0; padding: 20px; background: #f9f9f9; }
        .receipt { width: 300px; margin: 0 auto; background: white; padding: 15px; border: 1px solid #ccc; box-shadow: 2px 2px 5px rgba(0,0,0,0.1); }
        .center { text-align: center; }
        .line { border-top: 1px dashed #000; margin: 10px 0; }
        .item-row { display: flex; justify-content: space-between; margin-bottom: 2px; }
        .item-name { width: 60%; }
        .total-row { font-weight: bold; font-size: 14px; margin-top: 10px; border-top: 1px solid #000; padding-top: 5px;}
        .detail { font-size: 10px; color: #555; }
        .small-header { font-size: 10px; margin-top: 15px; }
        
        @media print {
            body { background: white; padding: 0; }
            .receipt { box-shadow: none; border: none; width: 100%; margin: 0; padding: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="receipt">
        <div class="center">
            <h3>HOSSANA CAFE RECEIPT</h3>
            <p>Order Type: <strong><?php echo $order_details['order_type']; ?></strong></p>
            <p>TIN: 000-000-000-000</p>
        </div>

        <div class="line"></div>
        
        <p class="small-header">Order Info:</p>
        <p class="detail">ID: #<?php echo $order_id; ?></p>
        <p class="detail">Date: <?php echo htmlspecialchars($order_details['transaction_date']); ?></p>
        <p class="detail"><?php echo $customer_info; ?></p>
        
        <div class="line"></div>

        <?php foreach ($item_details as $item): 
            $line_total = $item['price'] * $item['quantity'];
        ?>
            <div class="item-row">
                <span class="item-name"><?php echo htmlspecialchars($item['product_name']); ?> (x<?php echo $item['quantity']; ?>)</span>
                <span>₱<?php echo number_format($line_total, 2); ?></span>
            </div>
            <p class="detail">— <?php echo htmlspecialchars($item['size']); ?> | <?php echo htmlspecialchars($item['temp']); ?> <?php if ($item['ice_level'] !== 'N/A') echo "| Ice: " . htmlspecialchars($item['ice_level']); ?></p>
        <?php endforeach; ?>

        <div class="line"></div>

        <div class="item-row detail">
            <span>Vatable Sales:</span>
            <span>₱<?php echo number_format($vatable_sales, 2); ?></span>
        </div>
        <div class="item-row detail">
            <span>VAT (12%):</span>
            <span>₱<?php echo number_format($vat_amount, 2); ?></span>
        </div>

        <div class="item-row total-row">
            <span>TOTAL AMOUNT:</span>
            <span>₱<?php echo number_format($order_details['total_amount'], 2); ?></span>
        </div>
        
        <p class="small-header">Payment:</p>
        <div class="item-row">
            <span>Method:</span>
            <span><?php echo htmlspecialchars($order_details['payment_method']); ?></span>
        </div>
        <div class="item-row">
            <span>Received:</span>
            <span>₱<?php echo number_format($order_details['amount_paid'] ?? $order_details['total_amount'], 2); ?></span>
        </div>
        <div class="item-row">
            <span>Change:</span>
            <span>₱<?php echo number_format($order_details['change'], 2); ?></span>
        </div>

        <div class="line"></div>

        <div class="center">
            <p style="font-weight: bold;">THANK YOU!</p>
            <p class="detail">This serves as your official receipt.</p>
        </div>
    </div>
    <div class="no-print center">
        <button onclick="window.close()">Close Window</button>
    </div>
</body>
</html>