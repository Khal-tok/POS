<?php
session_start();
include 'db.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['role'] != 'rider') {
    header("location: login.php");
    exit;
}

$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
$error = ""; $success = "";
$delivery_fee = 35.00;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['confirm_delivery'])) {
    $order_id_post = intval($_POST['order_id']);
    $p_type = $_POST['payment_type'] ?? 'Cash';
    
    if ($order_id_post > 0) {
        // AUTOMATION LOGIC:
        // We set status to COMPLETED and PAID.
        // If prepared_by is still 0/NULL, we can't automate the name, 
        // BUT we ensure the status is 'COMPLETED' so it shows up in "ALL ORDERS".
        $sql_update = "UPDATE online_orders 
                       SET status = 'COMPLETED', 
                           delivery_status = 'Delivered', 
                           payment_status = 'Paid',
                           payment_type = ?
                       WHERE id = ?";
        
        if ($stmt = mysqli_prepare($conn, $sql_update)) {
            mysqli_stmt_bind_param($stmt, "si", $p_type, $order_id_post);
            if (mysqli_stmt_execute($stmt)) {
                $success = "Order #$order_id_post is now COMPLETED and PAID.";
                $order_id = 0;
            } else { $error = "Error: " . mysqli_error($conn); }
            mysqli_stmt_close($stmt);
        }
    }
}

$order_data = null; $order_items = []; $subtotal = 0;

if ($order_id > 0) {
    $sql_order = "SELECT o.*, u.username, u.contact_number FROM online_orders o 
                  LEFT JOIN users u ON o.user_id = u.id WHERE o.id = ?";
    if ($stmt = mysqli_prepare($conn, $sql_order)) {
        mysqli_stmt_bind_param($stmt, "i", $order_id);
        mysqli_stmt_execute($stmt);
        $order_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
    }

    if ($order_data) {
        $sql_items = "SELECT p.name, ooi.quantity, ooi.price FROM online_order_items ooi 
                      JOIN products p ON ooi.product_id = p.id WHERE ooi.order_id = ?";
        if ($stmt = mysqli_prepare($conn, $sql_items)) {
            mysqli_stmt_bind_param($stmt, "i", $order_id);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            while($row = mysqli_fetch_assoc($res)) {
                $order_items[] = $row;
                $subtotal += ($row['price'] * $row['quantity']);
            }
            mysqli_stmt_close($stmt);
        }
    }
}

$vat = $subtotal * 0.12;
$final_total = $subtotal + $delivery_fee;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><title>Order Receipt</title>
    <script src="https://www.paypal.com/sdk/js?client-id=AQN5gGSaHTsoo8SBoi2smVBP145Gh5iTMgKH2DUEJsu9Wv-lFlmXEGtrW2-p1xAs89rkzM38vuAVn5Tw&currency=PHP&disable-funding=credit,card"></script>
    <style>
        body { font-family: 'Courier New', monospace; background: #eee; padding: 20px; display: flex; justify-content: center; }
        .receipt { background: white; width: 100%; max-width: 400px; padding: 25px; border-radius: 4px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .row { display: flex; justify-content: space-between; margin: 5px 0; font-size: 0.9rem; }
        .line { border-bottom: 1px dashed #000; margin: 10px 0; }
        .btn { width: 100%; padding: 12px; background: #333; color: #fff; border: none; cursor: pointer; font-weight: bold; margin-top: 15px; border-radius: 4px; }
    </style>
</head>
<body>
<div class="receipt">
    <?php if ($success): ?>
        <div style="background:#d4edda; color:#155724; padding:15px; text-align:center;"><?php echo $success; ?></div>
        <a href="delivery_module.php" class="btn" style="display:block; text-align:center; text-decoration:none;">Finish</a>
    <?php elseif ($order_data): ?>
        <h2 style="text-align:center;">HOSSANA CAFE</h2>
        <p>Order: #<?php echo $order_id; ?></p>
        <p>Customer: <?php echo htmlspecialchars($order_data['username'] ?? 'Guest'); ?></p>
        <div class="line"></div>
        <?php foreach($order_items as $item): ?>
            <div class="row"><span><?php echo $item['quantity']; ?>x <?php echo htmlspecialchars($item['name']); ?></span><span>₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?></span></div>
        <?php endforeach; ?>
        <div class="line"></div>
        <div class="row"><span>Subtotal</span><span>₱<?php echo number_format($subtotal, 2); ?></span></div>
        <div class="row"><span>VAT (12%)</span><span>₱<?php echo number_format($vat, 2); ?></span></div>
        <div class="row"><span>Delivery Fee</span><span>₱<?php echo number_format($delivery_fee, 2); ?></span></div>
        <div class="row" style="font-weight:bold; font-size:1.2rem; color:#2E7D32;"><span>TOTAL</span><span>₱<?php echo number_format($final_total, 2); ?></span></div>
        <div class="line"></div>
        <form id="payForm" method="POST">
            <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
            <input type="hidden" name="confirm_delivery" value="1">
            <select name="payment_type" id="p_type" onchange="toggle()" required style="width:100%; padding:10px; margin-bottom:10px;">
                <option value="">-- Select Payment --</option><option value="Cash">Cash</option><option value="Online">PayPal</option>
            </select>
            <button type="submit" id="c_btn" class="btn" style="display:none;">CONFIRM CASH PAYMENT</button>
        </form>
        <div id="paypal-btn" style="display:none; margin-top:10px;"></div>
    <?php endif; ?>
</div>
<script>
    function toggle() {
        let v = document.getElementById('p_type').value;
        document.getElementById('c_btn').style.display = (v === 'Cash') ? 'block' : 'none';
        document.getElementById('paypal-btn').style.display = (v === 'Online') ? 'block' : 'none';
    }
    paypal.Buttons({
        createOrder: (data, actions) => { return actions.order.create({ purchase_units: [{ amount: { value: '<?php echo $final_total; ?>' } }] }); },
        onApprove: (data, actions) => { return actions.order.capture().then(() => { document.getElementById('payForm').submit(); }); }
    }).render('#paypal-btn');
</script>
</body></html>