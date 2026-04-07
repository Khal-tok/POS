<?php
session_start();
include 'db.php'; 

if (!isset($_SESSION['loggedin']) || $_SESSION['role'] != 'customer') {
    header("location: customer_login.php");
    exit;
}

if (!isset($_SESSION['online_cart'])) { $_SESSION['online_cart'] = []; }

// FIX: Define user_id here for consistency, preventing the "Undefined array key user_id" warning
$user_id = $_SESSION['user_id'] ?? 0;

// 1. POST/AJAX Handler for Cart Actions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // --- AJAX: Add to Cart ---
    if (isset($_POST['action']) && $_POST['action'] == 'add_to_cart') {
        $item = [
            'id' => $_POST['id'],
            'name' => $_POST['name'],
            'price' => floatval($_POST['price']),
            'qty' => 1, 
            'size' => $_POST['size'],
            'temp' => $_POST['temp'],
            'ice' => $_POST['ice']
        ];
        $cart_id = uniqid();
        $_SESSION['online_cart'][$cart_id] = $item;
        
        // Return cart HTML and total to update modal without full reload
        echo json_encode(['count' => count($_SESSION['online_cart']), 'html' => renderCartHtml(), 'total' => calculateTotal()]);
        exit;
    }
    
    // --- NON-AJAX: Remove from Cart ---
    if (isset($_POST['remove_cart_item'])) {
        unset($_SESSION['online_cart'][$_POST['cart_key']]);
        header("location: customer_dashboard.php");
        exit;
    }
    // Note: Clear Cart logic removed as per user request.
}

// Helper Functions to calculate and render cart data
function calculateTotal() {
    $total = 0;
    foreach($_SESSION['online_cart'] as $item) { 
        $total += $item['price'] * $item['qty']; 
    }
    return $total;
}

function renderCartHtml() {
    ob_start(); // Start output buffering
    
    if(empty($_SESSION['online_cart'])): ?>
        <p style="text-align: center; color: #777; padding: 20px;">Your cart is empty. Start adding delicious items!</p>
    <?php else: 
        $cartModalGTotal = calculateTotal();
    ?>
        <div id="cartList">
            <?php foreach($_SESSION['online_cart'] as $key => $item): 
                $itemTotal = $item['price'] * $item['qty'];
                $ice_text = ($item['temp'] === 'Cold' && $item['ice'] != 'N/A') ? " | Ice: " . htmlspecialchars($item['ice']) : "";
            ?>
            <div class="cart-item-card">
                <div class="item-details">
                    <b><?=htmlspecialchars($item['name'])?> (x<?=$item['qty']?>)</b>
                    <small><?=htmlspecialchars($item['size'])?> | <?=htmlspecialchars($item['temp'])?> <?=$ice_text?></small>
                </div>
                <div class="item-price-actions">
                    <strong>₱<?=number_format($itemTotal, 2)?></strong>
                    <form method="post" style="margin:0;">
                        <input type="hidden" name="cart_key" value="<?=$key?>">
                        <button type="submit" name="remove_cart_item" class="btn-remove">Remove</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="cart-total-bar"><span>Grand Total</span><span>₱<?=number_format($cartModalGTotal, 2)?></span></div>
        
    <?php endif;

    return ob_get_clean(); // Return buffered HTML
}


// 2. Fetch Products
$products = [];
$res = mysqli_query($conn, "SELECT * FROM products");
while($row = mysqli_fetch_assoc($res)) {
    $variants = [];
    $v_res = mysqli_query($conn, "SELECT * FROM product_variants WHERE product_id = " . $row['id']);
    while($v = mysqli_fetch_assoc($v_res)) { $variants[] = $v; }
    $row['variants'] = $variants;
    $row['has_sizes'] = count($variants) > 0 ? 1 : 0; 
    $products[] = $row;
}

// 3. Cart Total Calculation for Navbar
$gTotal = calculateTotal();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hossana Cafe</title>
    <style>
        :root { --primary: #8B4513; --accent: #2E7D32; --bg: #F9F9F9; --dark-text: #333; }
        body { font-family: 'Segoe UI', sans-serif; background: var(--bg); margin: 0; padding-bottom: 80px; color: var(--dark-text); }
        
        .navbar { background: white; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05); position: sticky; top: 0; z-index: 100; }
        .brand { font-size: 1.5em; font-weight: bold; color: var(--primary); }
        .nav-links a { color: #555; text-decoration: none; margin-left: 20px; font-weight: 600; }
        .cart-btn { background: var(--primary); color: white; padding: 8px 20px; border-radius: 20px; cursor: pointer; font-weight: bold; }
        
        .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
        .menu-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 20px; }
        .product-card { background: white; border-radius: 15px; padding: 15px; text-align: center; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .product-img { width: 120px; height: 120px; object-fit: cover; border-radius: 50%; margin-bottom: 10px; }
        .btn-view { background: var(--primary); color: white; border: none; padding: 8px 20px; border-radius: 20px; cursor: pointer; width: 100%; font-weight: bold; margin-top: 10px; }

        /* Modal Styles */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal-box { background: white; width: 95%; max-width: 450px; padding: 25px; border-radius: 15px; max-height: 90vh; overflow-y: auto; position: relative; }
        .close-btn { position: absolute; top: 15px; right: 20px; font-size: 24px; cursor: pointer; color: #777; z-index: 1001; }
        
        .pill-selector { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 15px; }
        .pill-selector input { display: none; }
        .pill-selector label { padding: 8px 15px; background: #eee; border-radius: 20px; cursor: pointer; font-size: 0.9em; border: 1px solid #ddd; }
        .pill-selector input:checked + label { background: var(--primary); color: white; border-color: var(--primary); }
        
        /* === IMPROVED CART DESIGN === */
        
        /* New Header Style for Button Alignment */
        .cart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .cart-header h2 {
            margin: 0;
            padding: 0;
            border-bottom: none;
            flex-grow: 1;
        }

        .btn-proceed-header {
            background: var(--accent); 
            color: white; 
            padding: 8px 15px; 
            border-radius: 20px; 
            font-size: 0.9em; 
            font-weight: bold; 
            text-decoration: none;
            margin-left: 10px;
        }
        
        .cart-item-card { 
            background: #fff8f5; 
            border: 1px solid #e0e0e0;
            border-radius: 8px; 
            padding: 12px; 
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .item-details { flex-grow: 1; }
        .item-details b { font-size: 1.1em; color: var(--dark-text); }
        .item-details small { display: block; color: #777; font-size: 0.85em; margin-top: 2px; }

        .item-price-actions { text-align: right; display: flex; flex-direction: column; align-items: flex-end; }
        .item-price-actions strong { font-size: 1.1em; color: var(--primary); margin-bottom: 5px; }

        .btn-remove { 
            background: #dc3545; 
            color: white; 
            border: none; 
            border-radius: 4px; 
            padding: 4px 8px; 
            cursor: pointer; 
            font-size: 0.8em; 
            margin-top: 5px;
            text-decoration: none;
            display: inline-block;
        }
        
        .cart-total-bar { 
            display: flex; 
            justify-content: space-between; 
            border-top: 2px solid var(--primary); 
            padding-top: 15px; 
            margin-top: 15px; 
            font-size: 1.4em; 
            font-weight: bold; 
            color: var(--primary); 
        }

        .btn-action { 
            background: var(--primary); 
            color: white; 
            width: 100%; 
            padding: 12px; 
            border: none; 
            border-radius: 8px; 
            font-size: 1.1em; 
            font-weight: bold; 
            cursor: pointer; 
            margin-top: 15px; 
            text-align: center; 
            text-decoration: none; 
        }
        .btn-checkout-link { 
            background: var(--accent); 

            display: inline-block; 
            margin-top: 0 !important; 
        } 

    </style>
</head>
<body>

<div class="navbar">
    <div class="brand">Hossana Cafe</div>
    <div class="nav-links">
        <a href="customer_order_history.php">My Orders</a>
        <a href="update_customer_account.php">Account</a>
        <a href="logout_customer.php">Logout</a>
    </div>
    <div class="cart-btn" onclick="openCart()">
        🛒 <span id="cart-count"><?php echo count($_SESSION['online_cart']); ?></span>
    </div>
</div>

<div class="container">
    <h2>Menu</h2>
    <div class="menu-grid">
        <?php foreach($products as $p): ?>
            <div class="product-card">
                <img src="product_images/<?php echo $p['image_path']; ?>" class="product-img">
                <h3><?php echo $p['name']; ?></h3>
                <p style="color:#8B4513; font-weight:bold;">Starts at ₱<?php echo number_format($p['price'], 2); ?></p>
                <button class="btn-view" onclick='openCustomize(<?php echo json_encode($p); ?>)'>View Options</button>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="modal-overlay" id="customizeModal">
    <div class="modal-box">
        <span class="close-btn" onclick="closeModal('customizeModal')">&times;</span>
        <h2 id="modalTitle" style="margin-top:0;">Customize</h2>
        <div style="text-align:center; margin-bottom:15px;"><img id="modalImg" src="" style="width:100px; border-radius:10px;"></div>
        
        <div id="tempGroup">
            <strong>Mood:</strong><br>
            <div class="pill-selector">
                <input type="radio" name="temp" id="tempHot" value="Hot" onchange="updatePrices()"><label for="tempHot">🔥 Hot</label>
                <input type="radio" name="temp" id="tempCold" value="Cold" onchange="updatePrices()"><label for="tempCold">❄️ Cold</label>
            </div>
        </div>

        <div id="sizeGroup">
            <strong>Size:</strong><br>
            <div class="pill-selector" id="sizeContainer"></div>
        </div>

        <div id="iceGroup" style="display:none;">
            <strong>Ice Level:</strong><br>
            <div class="pill-selector">
                <input type="radio" name="ice" id="iceNormal" value="Normal" checked><label for="iceNormal">Normal</label>
                <input type="radio" name="ice" id="iceLess" value="Less"><label for="iceLess">Less</label>
            </div>
        </div>

        <div class="cart-total-bar">
            <span>Total</span><span id="finalPrice">₱0.00</span>
        </div>
        <button class="btn-action" onclick="addToCart()">Add to Cart</button>
    </div>
</div>

<div class="modal-overlay" id="cartModal">
    <div class="modal-box">
        <span class="close-btn" onclick="closeModal('cartModal')">&times;</span>

        <div class="cart-header">
            <h2>Your Cart</h2>
            <?php if (!empty($_SESSION['online_cart'])): ?>
            <a href="checkout.php" class="btn-proceed-header">
             Proceed to Checkout
            </a>
            <?php endif; ?>
        </div>
        
        <div id="dynamicCartContent">
            <?php echo renderCartHtml(); ?>
        </div>
        
    </div>
</div>

<script>
    let currentProduct = null;
    let selectedPrice = 0;

    function openCustomize(product) {
        currentProduct = product;
        document.getElementById('modalTitle').innerText = product.name;
        document.getElementById('modalImg').src = "product_images/" + product.image_path;
        
        const tempGroup = document.getElementById('tempGroup');
        
        if (product.has_sizes == 1) {
            tempGroup.style.display = 'block';
            
            const hasHot = product.variants.some(v => v.temp === 'Hot');
            const hasCold = product.variants.some(v => v.temp === 'Cold');
            
            if (hasHot) {
                document.getElementById('tempHot').checked = true;
                renderSizes('Hot');
            } else if (hasCold) {
                document.getElementById('tempCold').checked = true;
                renderSizes('Cold');
            }

        } else {
            tempGroup.style.display = 'none';
            document.getElementById('sizeContainer').innerHTML = '<span style="color:#666;">Standard</span>';
            document.getElementById('iceGroup').style.display = 'none';
            selectedPrice = parseFloat(product.price);
            calcTotal();
        }
        document.getElementById('customizeModal').style.display = 'flex';
    }

    function renderSizes(temp) {
        const container = document.getElementById('sizeContainer');
        container.innerHTML = '';
        let found = false;
        
        document.getElementById('iceGroup').style.display = (temp === 'Cold') ? 'block' : 'none';

        currentProduct.variants.forEach((v, index) => {
            if (v.temp === temp) {
                found = true;
                const id = `size_${index}`;
                const checked = (!container.innerHTML) ? 'checked' : ''; 
                const html = `<input type="radio" name="size" id="${id}" value="${v.size}" data-price="${v.price}" ${checked} onchange="calcTotal()"><label for="${id}">${v.size} - ₱${parseFloat(v.price).toFixed(2)}</label>`;
                container.innerHTML += html;
            }
        });
        
        if (!found) container.innerHTML = '<span style="color:red;">Not available</span>';
        
        calcTotal();
    }

    function updatePrices() {
        const temp = document.querySelector('input[name="temp"]:checked').value;
        renderSizes(temp);
    }

    function calcTotal() {
        let total = 0;
        const sizeInput = document.querySelector('input[name="size"]:checked');
        
        if (sizeInput) { 
            total = parseFloat(sizeInput.dataset.price); 
        } 
        else if (currentProduct.has_sizes == 0) { 
            total = parseFloat(currentProduct.price); 
        }
        
        document.getElementById('finalPrice').innerText = "₱" + total.toFixed(2);
        selectedPrice = total;
    }

    function addToCart() {
        const tempRadio = document.querySelector('input[name="temp"]:checked');
        const sizeRadio = document.querySelector('input[name="size"]:checked');
        const iceRadio = document.querySelector('input[name="ice"]:checked');
        
        if (currentProduct.has_sizes == 1 && !sizeRadio) {
            alert("Please select a valid size option.");
            return;
        }

        const temp = tempRadio ? tempRadio.value : 'N/A';
        const size = sizeRadio ? sizeRadio.value : 'Standard';
        const ice = (temp === 'Cold' && iceRadio) ? iceRadio.value : 'N/A';

        const data = new FormData();
        data.append('action', 'add_to_cart');
        data.append('id', currentProduct.id);
        data.append('name', currentProduct.name);
        data.append('price', selectedPrice);
        data.append('qty', 1);
        data.append('size', size);
        data.append('temp', temp);
        data.append('ice', ice);

        fetch('customer_dashboard.php', { method: 'POST', body: data })
        .then(res => res.json())
        .then(response => {
            document.getElementById('cart-count').innerText = response.count;
            
            // CRITICAL FIX: Update the dynamic cart content 
            document.getElementById('dynamicCartContent').innerHTML = response.html; 
            
            closeModal('customizeModal');
            alert('Added to Cart!');
        })
        .catch(error => {
            console.error('Error adding to cart:', error);
            alert('Failed to add item to cart. Please check console.');
        });
    }

    function openCart() { 
        document.getElementById('cartModal').style.display = 'flex'; 
    }
    function closeModal(id) { document.getElementById(id).style.display = 'none'; }
</script>
</body>
</html>