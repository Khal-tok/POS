<?php
session_start();
include 'db.php';

// 1. UNIVERSAL LOGIN CHECK
if (!isset($_SESSION['loggedin']) && !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$order_id = intval($_GET['order_id'] ?? 0);

if ($order_id === 0) {
    echo "<script>window.history.back();</script>";
    exit();
}

// 2. FETCH ORDER
$stmt = $conn->prepare("SELECT * FROM online_orders WHERE id = ?");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $order = $result->fetch_assoc();
} else {
    echo "Order not found.";
    exit();
}

// 3. FETCH ITEMS
$stmt_items = $conn->prepare("SELECT ooi.quantity, ooi.price, p.name 
                              FROM online_order_items ooi 
                              JOIN products p ON ooi.product_id = p.id 
                              WHERE ooi.order_id = ?");
$stmt_items->bind_param("i", $order_id);
$stmt_items->execute();
$items_result = $stmt_items->get_result();

// --- TAX CALCULATION ---
$grand_total = $order['total_amount'];
$vat_rate = 0.12;
$vatable_sales = $grand_total / (1 + $vat_rate);
$vat_amount = $grand_total - $vatable_sales;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #<?php echo $order['id']; ?></title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background-color: #f9f9f9; margin: 0; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h2 { color: #4e342e; border-bottom: 2px solid #4e342e; padding-bottom: 10px; margin-top: 0; }
        .order-meta p { margin: 5px 0; color: #555; }
        
        /* Status Badge */
        .status-badge { 
            padding: 5px 10px; border-radius: 4px; color: white; font-weight: bold; font-size: 0.9em;
            background-color: #f0ad4e; 
        }
        .status-delivered { background-color: #28a745; } 
        .status-out-for-delivery { background-color: #17a2b8; } 
        .status-canceled { background-color: #dc3545; }

        /* Table Styling */
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background-color: #4e342e; color: white; padding: 12px; text-align: left; }
        td { padding: 12px; border-bottom: 1px solid #ddd; color: #333; }
        
        .tax-row td { text-align: right; color: #777; font-size: 0.9em; border-bottom: none; }
        .total-row td { font-weight: bold; font-size: 1.2em; border-top: 2px solid #4e342e; text-align: right; color: #4e342e; }
        
        /* Buttons */
        .btn-back { display: inline-block; text-decoration: none; color: #4e342e; font-weight: bold; padding: 10px 0; }
        
        .btn-print {
            float: right;
            background-color: #4e342e;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-print:hover { background-color: #3e2b26; }

        @media print {
            body { background-color: white; padding: 0; }
            .container { box-shadow: none; border: none; padding: 0; margin: 0; width: 100%; max-width: 100%; }
            .btn-back, .btn-print { display: none !important; }
            h2 { border-bottom: 2px solid #000; color: #000; }
            th { background-color: #ddd !important; color: #000 !important; }
            .status-badge { border: 1px solid #000; color: #000; background: none; padding: 2px 5px; }
        }
        @media (max-width: 600px) {
            .container { padding: 15px; }
            .btn-print { float: none; display: block; width: 100%; text-align: center; margin-bottom: 15px; }
            table, thead, tbody, th, td, tr { display: block; }
            thead tr { position: absolute; top: -9999px; left: -9999px; }
            tr { margin-bottom: 15px; border: 1px solid #ddd; border-radius: 8px; }
            td { border-bottom: 1px solid #eee; position: relative; padding-left: 50%; text-align: right; }
            td::before { position: absolute; left: 10px; width: 45%; text-align: left; font-weight: bold; content: attr(data-label); }
            
            .tax-row td, .total-row td { text-align: right; padding-left: 10px; }
            .tax-row td::before, .total-row td::before { content: ''; } 
        }
    </style>
</head>
<body>

<div class="container">
    <div style="margin-bottom: 20px;">
        <a href="javascript:history.back()" class="btn-back">&larr; Back to History</a>
        
        <a href="javascript:window.print()" class="btn-print">
            <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                <path d="M2.5 8a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1z"/>
                <path d="M5 1a2 2 0 0 0-2 2v2H2a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h1v1a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v-1h1a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-1V3a2 2 0 0 0-2-2H5zM4 3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2H4V3zm1 5a2 2 0 0 0-2 2v1H2a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1v-1a2 2 0 0 0-2-2H5zm7 2v3a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1z"/>
            </svg>
            View Receipt
        </a>
    </div>
    
    <h2>Receipt #<?php echo $order['id']; ?></h2>
    
    <div class="order-meta">
        <p><strong>Date:</strong> <?php echo date("M d, Y h:i A", strtotime($order['order_date'])); ?></p>
        <p><strong>Status:</strong> <span class="status-badge status-<?php echo strtolower($order['status']); ?>"><?php echo strtoupper($order['status']); ?></span></p>
        <p><strong>Address:</strong> <?php echo htmlspecialchars($order['delivery_address'] ?? 'N/A'); ?></p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Item</th>
                <th>Qty</th>
                <th>Price</th>
                <th>Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            while ($item = $items_result->fetch_assoc()): 
                $sub_total = $item['price'] * $item['quantity'];
            ?>
            <tr>
                <td data-label="Item"><?php echo htmlspecialchars($item['name']); ?></td>
                <td data-label="Qty"><?php echo $item['quantity']; ?></td>
                <td data-label="Price">₱<?php echo number_format($item['price'], 2); ?></td>
                <td data-label="Subtotal">₱<?php echo number_format($sub_total, 2); ?></td>
            </tr>
            <?php endwhile; ?>

            <tr class="tax-row">
                <td colspan="3">VATable Sales:</td>
                <td>₱<?php echo number_format($vatable_sales, 2); ?></td>
            </tr>
            <tr class="tax-row">
                <td colspan="3">VAT (12%):</td>
                <td>₱<?php echo number_format($vat_amount, 2); ?></td>
            </tr>

            <tr class="total-row">
                <td colspan="3">Grand Total:</td>
                <td>₱<?php echo number_format($grand_total, 2); ?></td>
            </tr>
        </tbody>
    </table>
</div>

</body>
</html>