<?php
session_start();
include 'db.php';

// 1. Authorization: Only allow logged-in users
if (!isset($_SESSION['loggedin']) || !isset($_SESSION['user_id'])) {
    header("location: login.php");
    exit;
}

$order_id = intval($_GET['order_id'] ?? 0);
$user_id = $_SESSION['user_id'];

if ($order_id === 0) { echo "Invalid Order ID."; exit; }

// 2. Fetch Order Details (Online Orders)
// Ensure the customer can only view their own order
$sql = "SELECT o.*, u.username AS customer_name 
        FROM online_orders o 
        JOIN users u ON o.user_id = u.id 
        WHERE o.id = ? AND o.user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$order = $result->fetch_assoc();

if (!$order) {
    echo "Order not found or access denied.";
    exit;
}

// 3. Get Items
$sql_items = "SELECT p.name AS product_name, ooi.quantity, ooi.price 
              FROM online_order_items ooi
              JOIN products p ON ooi.product_id = p.id
              WHERE ooi.order_id = ?";
              
$stmt_items = $conn->prepare($sql_items);
$stmt_items->bind_param("i", $order_id);
$stmt_items->execute();
$result_items = $stmt_items->get_result();

// Set Variables
$total_amount = $order['total_amount'];
$order_date = date("Y-m-d H:i:s", strtotime($order['order_date']));
$customer_name = $order['customer_name'];

// --- TAX CALCULATION (12% VAT) ---
$vat_rate = 0.12;
$vatable_sales = $total_amount / (1 + $vat_rate);
$vat_amount = $total_amount - $vatable_sales;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Receipt #<?php echo $order_id; ?></title>
    <style>
        /* THERMAL RECEIPT DESIGN */
        body { font-family: 'Courier New', Courier, monospace; background-color: #eee; padding: 20px; display: flex; justify-content: center; }
        .receipt-container {
            width: 300px;
            background: #fff;
            padding: 20px;
            border: 1px solid #ddd;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .header { text-align: center; margin-bottom: 10px; }
        .header h3 { margin: 0; font-size: 1.2rem; text-transform: uppercase; color: #333; }
        .header p { margin: 2px 0; font-size: 0.8rem; color: #555; }
        
        .divider { border-bottom: 1px dashed #000; margin: 10px 0; }
        
        .info-row { display: flex; justify-content: space-between; font-size: 0.8rem; margin-bottom: 4px; }
        
        table { width: 100%; border-collapse: collapse; font-size: 0.8rem; margin: 10px 0; }
        th { text-align: left; border-bottom: 1px dashed #000; padding-bottom: 5px; }
        td { padding: 5px 0; vertical-align: top; }
        .text-right { text-align: right; }
        
        .totals { margin-top: 10px; border-top: 1px dashed #000; padding-top: 5px; }
        .totals-row { display: flex; justify-content: space-between; font-size: 0.8rem; margin-bottom: 3px; }
        .grand-total { font-weight: bold; font-size: 1rem; margin-top: 5px; border-top: 1px double #000; padding-top: 5px; }
        
        .footer { text-align: center; font-size: 0.7rem; margin-top: 20px; color: #555; }
        
        .btn-print { 
            display: block; width: 100%; padding: 10px; margin-top: 15px; 
            background: #333; color: white; text-align: center; text-decoration: none; font-size: 0.9rem; border-radius: 4px; 
        }
        .btn-print:hover { background: #555; }

        @media print {
            body { background: none; padding: 0; }
            .receipt-container { box-shadow: none; border: none; width: 100%; }
            .btn-print { display: none; }
        }
    </style>
</head>
<body>

<div class="receipt-container">
    <div class="header">
        <h3>Hossana Cafe</h3>
        <p>Online Order Receipt</p>
        <p>Tin: 000-000-000-000</p>
    </div>

    <div class="divider"></div>

    <div class="info-row"><span>Order ID:</span> <span>#<?php echo $order_id; ?></span></div>
    <div class="info-row"><span>Customer:</span> <span><?php echo htmlspecialchars($customer_name); ?></span></div>
    <div class="info-row"><span>Date:</span> <span><?php echo $order_date; ?></span></div>
    <div class="info-row"><span>Address:</span> <span><?php echo htmlspecialchars($order['delivery_address'] ?? 'N/A'); ?></span></div>

    <table>
        <thead>
            <tr><th>Item</th><th class="text-right">Qty</th><th class="text-right">Total</th></tr>
        </thead>
        <tbody>
            <?php while ($item = $result_items->fetch_assoc()): 
                $subtotal = $item['price'] * $item['quantity'];
            ?>
            <tr>
                <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                <td class="text-right"><?php echo $item['quantity']; ?></td>
                <td class="text-right">₱ <?php echo number_format($subtotal, 2); ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <div class="totals">
        <div class="totals-row" style="color:#555;">
            <span>VATable Sales</span>
            <span><?php echo number_format($vatable_sales, 2); ?></span>
        </div>
        <div class="totals-row" style="color:#555;">
            <span>VAT (12%)</span>
            <span><?php echo number_format($vat_amount, 2); ?></span>
        </div>
        
        <div class="totals-row grand-total">
            <span>TOTAL DUE</span>
            <span>₱ <?php echo number_format($total_amount, 2); ?></span>
        </div>
    </div>

    <div class="footer">
        <p>THIS IS YOUR OFFICIAL RECEIPT</p>
        <p>Thank you for ordering online!</p>
    </div>

    <a href="javascript:window.print()" class="btn-print">Print Receipt</a>
</div>

</body>
</html>