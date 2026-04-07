<?php
session_start();
include 'db.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['role'] != 'barista') {
    header("location: login.php");
    exit;
}

$columns_query = mysqli_query($conn, "SHOW COLUMNS FROM online_orders LIKE 'order_time'");
$date_column = (mysqli_num_rows($columns_query) > 0) ? 'order_time' : 'order_date';

$sql_orders = "SELECT o.*, u.username, u.contact_number 
               FROM online_orders o 
               JOIN users u ON o.user_id = u.id 
               ORDER BY o.$date_column DESC"; 
$result_orders = mysqli_query($conn, $sql_orders);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Order History | Barista POS</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --p-brown: #5D4037; --d-brown: #3E2723; --bg: #f4f7f6; --white: #ffffff; }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Poppins', sans-serif; }
        body { background: var(--bg); font-size: 15px; color: #333; padding: 25px; }
        .container { max-width: 1150px; margin: 0 auto; }
        .top-nav { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .btn-back { text-decoration: none; color: var(--p-brown); font-weight: 600; font-size: 0.9rem; }
        .card { background: var(--white); border-radius: 16px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); overflow: hidden; }
        
        table { width: 100%; border-collapse: collapse; }
        thead th { background: #fafafa; padding: 15px 25px; text-align: left; font-size: 0.75rem; text-transform: uppercase; color: #999; border-bottom: 1px solid #eee; }
        tbody td { padding: 18px 25px; border-bottom: 1px solid #f8f8f8; vertical-align: middle; }
        
        .status-badge { padding: 6px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; }
        .status-Pending { background: #FFF3E0; color: #E65100; }
        .status-Prepared { background: #E8F5E9; color: #2E7D32; }
        .status-Completed { background: #E3F2FD; color: #1976D2; }
        
        .btn-action { text-decoration: none; padding: 10px 18px; border-radius: 10px; font-size: 0.85rem; font-weight: 600; display: inline-block; border: none; cursor: pointer; }
        .btn-view { background: #F5F5F5; color: var(--p-brown); margin-right: 5px; }
        .btn-toggle { background: var(--p-brown); color: white; }

        .item-details-row { background: #fdfcfb; display: none; }
        .receipt-container { padding: 25px 40px; display: flex; gap: 30px; align-items: flex-start; }
        
        .receipt-card { background: white; border: 1px solid #ddd; border-radius: 4px; padding: 25px; width: 380px; position: relative; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        .receipt-card::before { content: ""; position: absolute; top: -10px; left: 0; width: 100%; height: 10px; background: linear-gradient(-45deg, transparent 5px, white 5px), linear-gradient(45deg, transparent 5px, white 5px); background-size: 10px 10px; }
        .receipt-card::after { content: ""; position: absolute; bottom: -10px; left: 0; width: 100%; height: 10px; background: linear-gradient(-135deg, transparent 5px, white 5px), linear-gradient(135deg, transparent 5px, white 5px); background-size: 10px 10px; }
        
        .receipt-title { font-size: 0.85rem; font-weight: 700; color: #333; border-bottom: 1px dashed #ccc; padding-bottom: 10px; margin-bottom: 15px; text-align: center; }
        .receipt-line { display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 0.9rem; }
        .receipt-total-section { margin-top: 12px; padding-top: 12px; border-top: 1px solid #333; }
        
        .btn-print { background: #2E7D32; color: white; padding: 14px 24px; border-radius: 8px; text-decoration: none; font-weight: 700; display: inline-flex; align-items: center; gap: 10px; transition: 0.3s; }
        .btn-print:hover { background: #1B5E20; transform: translateY(-2px); }
    </style>
</head>
<body>

<div class="container">
    <div class="top-nav">
        <a href="barista_dashboard.php" class="btn-back">← Back to Dashboard</a>
        <h3 style="color: var(--p-brown); font-weight: 700;">Online Order Log</h3>
    </div>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>ORDER ID</th>
                    <th>CUSTOMER</th>
                    <th>AMOUNT</th>
                    <th>STATUS</th>
                    <th>ACTION</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = mysqli_fetch_assoc($result_orders)): 
                    $order_id = $row['id'];
                    $status_class = "status-" . str_replace(' ', '', $row['status']);
                ?>
                <tr>
                    <td style="font-weight: 700; color: var(--p-brown);">#<?php echo $order_id; ?></td>
                    <td>
                        <div style="font-weight: 600;"><?php echo htmlspecialchars($row['username']); ?></div>
                        <div style="font-size: 0.75rem; color: #999;"><?php echo date('M d, Y | h:i A', strtotime($row['order_time'] ?? $row['order_date'])); ?></div>
                    </td>
                    <td style="font-weight: 700;">₱ <?php echo number_format($row['total_amount'], 2); ?></td>
                    <td><span class="status-badge <?php echo $status_class; ?>"><?php echo $row['status']; ?></span></td>
                    <td>
                        <a href="barista_order_details.php?order_id=<?php echo $order_id; ?>" class="btn-action btn-view">Update</a>
                        <button onclick="toggleItems(<?php echo $order_id; ?>)" class="btn-action btn-toggle">Summary ▼</button>
                    </td>
                </tr>
                <tr id="items-<?php echo $order_id; ?>" class="item-details-row">
                    <td colspan="5">
                        <div class="receipt-container">
                            <div class="receipt-card">
                                <div class="receipt-title">TAX INVOICE (VAT INCLUSIVE)</div>
                                <?php
                                $total_paid = $row['total_amount'];
                                // Back-calculating Tax Inclusive:
                                // Price = Subtotal + (Subtotal * 0.12) -> Price = Subtotal * 1.12
                                $subtotal = $total_paid / 1.12;
                                $tax_amount = $total_paid - $subtotal;

                                $item_query = "SELECT oi.*, p.name FROM online_order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = $order_id";
                                $item_result = mysqli_query($conn, $item_query);
                                while($item = mysqli_fetch_assoc($item_result)):
                                ?>
                                <div class="receipt-line">
                                    <span><?php echo $item['quantity']; ?>x <?php echo htmlspecialchars($item['name']); ?></span>
                                    <span>₱ <?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                                </div>
                                <?php endwhile; ?>

                                <div class="receipt-total-section">
                                    <div class="receipt-line" style="color: #777;">
                                        <span>Subtotal (Net of VAT)</span>
                                        <span>₱ <?php echo number_format($subtotal, 2); ?></span>
                                    </div>
                                    <div class="receipt-line" style="color: #777;">
                                        <span>VAT (12%)</span>
                                        <span>₱ <?php echo number_format($tax_amount, 2); ?></span>
                                    </div>
                                    <div class="receipt-line" style="margin-top: 10px; font-size: 1.1rem; font-weight: 800; color: #000;">
                                        <span>GRAND TOTAL</span>
                                        <span>₱ <?php echo number_format($total_paid, 2); ?></span>
                                    </div>
                                </div>
                            </div>

                            <?php if ($row['status'] == 'Completed'): ?>
                            <div style="max-width: 300px;">
                                <h4 style="margin-bottom: 10px; color: var(--p-brown);">Order Completed</h4>
                                <p style="font-size: 0.8rem; color: #666; margin-bottom: 20px;">Generate the final thermal receipt for documentation or customer copy.</p>
                                <a href="print_receipt.php?order_id=<?php echo $order_id; ?>" target="_blank" class="btn-print">
                                    🖨️ Print Barista Receipt
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    function toggleItems(orderId) {
        var element = document.getElementById('items-' + orderId);
        if (!element.style.display || element.style.display === "none") {
            element.style.display = "table-row";
        } else {
            element.style.display = "none";
        }
    }
</script>
</body>
</html>