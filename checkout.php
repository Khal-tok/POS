<?php
session_start();
include 'db.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['role'] != 'customer') {
    header("location: customer_login.php");
    exit;
}

if (empty($_SESSION['online_cart'])) {
    header("location: customer_dashboard.php");
    exit;
}

$subtotal = 0;
foreach ($_SESSION['online_cart'] as $item) {
    $qty = isset($item['qty']) ? $item['qty'] : (isset($item['quantity']) ? $item['quantity'] : 1);
    $subtotal += $item['price'] * $qty;
}

$delivery_fee = 35.00;
$vat_rate = 0.12;
$vat_amount = $subtotal * $vat_rate;
$grand_total = $subtotal + $delivery_fee;

$username = $_SESSION['username'];
$stmt = mysqli_prepare($conn, "SELECT contact_number FROM users WHERE username = ?");
mysqli_stmt_bind_param($stmt, "s", $username);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($res);
$contact = $user['contact_number'] ?? '';
?>

<!DOCTYPE html>
<html>
<head>
    <title>Checkout | Hossana Cafe</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #F8F4EF; color: #4E342E; margin: 0; padding: 20px; }
        .checkout-container { max-width: 1000px; margin: 0 auto; display: grid; grid-template-columns: 1fr 380px; gap: 30px; }
        .card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom: 20px; }
        h2 { border-bottom: 2px solid #D7CCC8; padding-bottom: 10px; margin-top: 0; color: #5D4037; font-size: 1.2rem; }
        
        .item-row { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #F5F5F5; }
        .item-name { font-weight: 600; color: #3E2723; display: block; }
        .item-qty { font-size: 0.85rem; color: #795548; }

        .summary-line { display: flex; justify-content: space-between; margin: 12px 0; font-size: 1rem; }
        .total-line { display: flex; justify-content: space-between; margin-top: 15px; padding-top: 15px; border-top: 2px solid #5D4037; font-size: 1.4rem; font-weight: 800; color: #2E7D32; }

        .input-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: 600; font-size: 0.9rem; color: #5D4037; }
        input, textarea, select { width: 100%; padding: 12px; border: 1.5px solid #E0E0E0; border-radius: 8px; box-sizing: border-box; font-size: 1rem; font-family: inherit; }
        
        .btn-cod { width: 100%; background-color: #5D4037; color: white; border: none; padding: 16px; border-radius: 8px; font-weight: 700; cursor: pointer; font-size: 1.1rem; transition: 0.3s; display: none; }
        .btn-cod:hover { background-color: #3E2723; }
    </style>
</head>
<body>

<div class="checkout-container">
    <div class="left-col">
        <div class="card">
            <h2>🛒 Review Ordered Goods</h2>
            <?php foreach ($_SESSION['online_cart'] as $item): 
                $qty = isset($item['qty']) ? $item['qty'] : (isset($item['quantity']) ? $item['quantity'] : 1);
            ?>
                <div class="item-row">
                    <div>
                        <span class="item-name"><?php echo htmlspecialchars($item['name']); ?></span>
                        <span class="item-qty">Quantity: <?php echo $qty; ?></span>
                    </div>
                    <div style="font-weight:700;">₱<?php echo number_format($item['price'] * $qty, 2); ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="card">
            <h2>🚚 Delivery Details</h2>
            <div class="input-group">
                <label>Complete Delivery Address</label>
                <textarea id="delivery_address" rows="3" placeholder="House No., Street, Brgy, City..."></textarea>
            </div>
            <div class="input-group">
                <label>Contact Number</label>
                <input type="text" id="contact_number" value="<?php echo htmlspecialchars($contact); ?>">
            </div>
            <div class="input-group">
                <label>Order Notes</label>
                <textarea id="customer_notes" rows="2" placeholder="Instructions for the rider..."></textarea>
            </div>
        </div>
    </div>

    <div class="right-col">
        <div class="card" style="position: sticky; top: 20px;">
            <h2>💰 Payment Summary</h2>
            <div class="summary-line"><span>Subtotal</span><span>₱<?php echo number_format($subtotal, 2); ?></span></div>
            <div class="summary-line"><span>VAT (12% Incl.)</span><span>₱<?php echo number_format($vat_amount, 2); ?></span></div>
            <div class="summary-line"><span>Delivery Fee</span><span>₱<?php echo number_format($delivery_fee, 2); ?></span></div>
            <div class="total-line"><span>GRAND TOTAL</span><span>₱<?php echo number_format($grand_total, 2); ?></span></div>
            
            <div class="input-group" style="margin-top:20px;">
                <label>Choose Payment Method</label>
                <select id="payment_method" onchange="togglePayment()">
                    <option value="paypal">PayPal / Card (Live)</option>
                    <option value="cod">Cash on Delivery (COD)</option>
                </select>
            </div>

            <div id="paypal-button-container"></div>
            <button id="cod-button" class="btn-cod" onclick="submitOrder('COD')">PLACE ORDER (COD)</button>
        </div>
    </div>
</div>

<script src="https://www.paypal.com/sdk/js?client-id=ATpm3wd-5s-4FZw790royME4J4Tvnr9MT2Fh0QTKvvlFuNsFsiJtlpFH24LVKeoYUjPNUDADy6LL3qgA&currency=PHP"></script>

<script>
    function togglePayment() {
        const method = document.getElementById('payment_method').value;
        document.getElementById('paypal-button-container').style.display = (method === 'paypal') ? 'block' : 'none';
        document.getElementById('cod-button').style.display = (method === 'cod') ? 'block' : 'none';
    }

    function submitOrder(method, orderID = null) {
        const address = document.getElementById('delivery_address').value;
        const contact = document.getElementById('contact_number').value;
        const notes = document.getElementById('customer_notes').value;

        if(!address || !contact) {
            alert("Please provide your delivery address and contact number.");
            return;
        }

        fetch('paypal_process.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                orderID: orderID,
                payment_method: method,
                address: address,
                contact: contact,
                notes: notes,
                amount: <?php echo $grand_total; ?>
            })
        })
        .then(res => res.json())
        .then(data => {
            if(data.success) { window.location.href = "customer_order_history.php"; }
            else { alert('Error: ' + data.message); }
        })
        .catch(err => console.error("Error:", err));
    }

    paypal.Buttons({
        createOrder: function(data, actions) {
            if(!document.getElementById('delivery_address').value) {
                alert("Please enter delivery address first.");
                return false;
            }
            return actions.order.create({
                purchase_units: [{
                    amount: { value: '<?php echo $grand_total; ?>' }
                }]
            });
        },
        onApprove: function(data, actions) {
            return actions.order.capture().then(function(details) {
                submitOrder('PayPal', details.id);
            });
        }
    }).render('#paypal-button-container');
</script>
</body>
</html>