<?php
session_start();
include 'db.php'; 

// 1. Authorization
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] != 'barista') {
    header("location: login.php");
    exit;
}

// 2. Initialize Cart
if (!isset($_SESSION['cart'])) { $_SESSION['cart'] = []; }

// 3. Handle POST Actions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // --- ADD TO CART ---
    if (isset($_POST['add_to_cart'])) {
        $product_id = $_POST['product_id'];
        $name = $_POST['product_name'];
        $qty = max(1, intval($_POST['quantity']));

        $price = 0; $size = "Standard"; $temp = "N/A"; $ice = "N/A";

        if (isset($_POST['size_option']) && !empty($_POST['size_option'])) {
            $parts = explode('|', $_POST['size_option']);
            $size = $parts[0];
            $price = floatval($parts[1]);
            
            $temp = isset($_POST['temp_selection']) ? $_POST['temp_selection'] : "N/A";
            if ($temp == 'Cold' && isset($_POST['ice_level'])) {
                $ice = $_POST['ice_level'];
            }
        } else {
            $price = floatval($_POST['price']);
        }

        if ($temp == "Hot") { $ice = "N/A"; }
        if ($size == "Standard") { $temp = "N/A"; $ice = "N/A"; }

        $cart_key = $product_id . '_' . $size . '_' . $temp . '_' . $ice;

        if (isset($_SESSION['cart'][$cart_key])) {
            $_SESSION['cart'][$cart_key]['quantity'] += $qty;
        } else {
            $_SESSION['cart'][$cart_key] = [
                'product_id' => $product_id,
                'name' => $name,
                'price' => $price,
                'size' => $size,
                'temp' => $temp,
                'ice' => $ice,
                'quantity' => $qty
            ];
        }
        header("location: barista_dashboard.php"); exit;
    }

    // --- PROCESS PAYMENT ---
    if (isset($_POST['process_payment'])) {
        $total = filter_var($_POST['total_amount'], FILTER_VALIDATE_FLOAT);
        $paid = filter_var($_POST['amount_received'], FILTER_VALIDATE_FLOAT);
        
        if ($total > 0 && $paid >= $total) {
            $change = $paid - $total;
            $uid = $_SESSION['user_id']; 

            $stmt_txn = mysqli_prepare($conn, "INSERT INTO transactions (user_id, total_amount, amount_paid) VALUES (?, ?, ?)");
            mysqli_stmt_bind_param($stmt_txn, "idd", $uid, $total, $paid);
            mysqli_stmt_execute($stmt_txn);
            $txn_id = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt_txn);

            $stmt_items = mysqli_prepare($conn, "INSERT INTO order_items (transaction_id, product_id, quantity, price, size, temp, ice_level) VALUES (?, ?, ?, ?, ?, ?, ?)");
            foreach($_SESSION['cart'] as $item) {
                mysqli_stmt_bind_param($stmt_items, "iiidsss", $txn_id, $item['product_id'], $item['quantity'], $item['price'], $item['size'], $item['temp'], $item['ice']);
                mysqli_stmt_execute($stmt_items);
            }
            mysqli_stmt_close($stmt_items);
            
            $_SESSION['last_transaction_id'] = $txn_id;
            
            $_SESSION['cart'] = [];
            $_SESSION['message_success'] = "Payment Complete! Change: ₱" . number_format($change, 2);
            header("location: barista_dashboard.php"); exit;
        } else {
            $_SESSION['message_error'] = "Invalid Payment Amount.";
        }
    }

    if (isset($_POST['remove_from_cart'])) {
        unset($_SESSION['cart'][$_POST['cart_key']]);
        header("location: barista_dashboard.php"); exit;
    }
    if (isset($_POST['clear_cart'])) {
        $_SESSION['cart'] = [];
        header("location: barista_dashboard.php"); exit;
    }
}

$products_by_tab = []; 
$snacks_list = [];
$sql_all = "SELECT * FROM products ORDER BY name ASC";
$res_all = mysqli_query($conn, $sql_all);

while($row = mysqli_fetch_assoc($res_all)) {
    $cat = strtolower($row['category']);
    $sub = $row['subcategory'];
    if (in_array($cat, ['snack', 'pastry', 'food', 'dessert'])) {
        $snacks_list[] = $row;
    } else {
        $tab = $sub ?: ucfirst($cat);
        if(empty($tab)) $tab = "Other";
        $products_by_tab[$tab][] = $row;
    }
}
ksort($products_by_tab);

$total = 0;
foreach ($_SESSION['cart'] as $item) { $total += $item['price'] * $item['quantity']; }

// TAX CALCULATIONS
$vat_rate = 0.12; 
$vatable_sales = $total / (1 + $vat_rate);
$vat_amount = $total - $vatable_sales;
?>

<!DOCTYPE html>
<html>
<head>
    <title>Barista POS</title>
    <link rel="stylesheet" href="barista_dashboard.css">
    
    <style>
        /* --- 1. CLICKABLE BADGE STYLE --- */
        a.user-badge {
            text-decoration: none; 
            color: inherit;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            transition: opacity 0.2s;
        }
        a.user-badge:hover {
            opacity: 0.8;
        }

        /* --- 2. SCROLLABLE PRODUCT GRID STYLE --- */
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 15px;
            
            /* SCROLL MAGIC HERE */
            max-height: calc(100vh - 280px); /* Fill remaining height */
            overflow-y: auto; /* Enable Vertical Scroll */
            padding: 5px; /* Padding for focus rings */
            padding-right: 10px; /* Space for scrollbar */
        }

        /* CUSTOM SCROLLBAR DESIGN (Chrome/Safari) */
        .product-grid::-webkit-scrollbar {
            width: 8px;
        }
        .product-grid::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        .product-grid::-webkit-scrollbar-thumb {
            background: #8B4513; /* Coffee Brown */
            border-radius: 4px;
        }
        .product-grid::-webkit-scrollbar-thumb:hover {
            background: #6F370F; /* Darker Brown on Hover */
        }
    </style>

    <script>
        var productData = {}; 
        var currentPrice = 0;

        function openPOSModal(p) {
            document.getElementById('mId').value = p.id;
            document.getElementById('mNameIn').value = p.name;
            document.getElementById('mTitle').innerText = p.name;
            
            document.getElementById('btnTempHot').style.display = 'none';
            document.getElementById('btnTempCold').style.display = 'none';
            document.getElementById('sizeContainer').innerHTML = '';
            document.getElementById('iceGroup').style.display = 'none';
            
            document.getElementById('inTemp').value = '';
            document.getElementById('inSize').value = '';
            document.getElementById('inIce').value = 'Normal Ice';
            document.getElementById('qtyDisplay').value = 1;
            document.getElementById('inQty').value = 1;

            document.querySelectorAll('.opt-btn').forEach(b => b.classList.remove('selected'));
            document.querySelectorAll('#iceContainer .opt-btn')[0].classList.add('selected');

            const variants = productData[p.id] || [];
            
            if (variants.length > 0) {
                document.getElementById('variantArea').style.display = 'block';
                document.getElementById('singleArea').style.display = 'none';
                
                const temps = [...new Set(variants.map(v => v.temp))];
                if(temps.includes('Hot')) document.getElementById('btnTempHot').style.display = 'flex';
                if(temps.includes('Cold')) document.getElementById('btnTempCold').style.display = 'flex';
                
                selectTemp(temps[0], p.id); 
            } else {
                document.getElementById('variantArea').style.display = 'none';
                document.getElementById('singleArea').style.display = 'block';
                document.getElementById('mPrice').value = p.price;
                currentPrice = parseFloat(p.price);
                updateTotalBtn();
            }
            
            document.getElementById('posModal').style.display = 'flex';
        }

        function selectTemp(temp, pid) {
            document.getElementById('inTemp').value = temp;
            document.querySelectorAll('.temp-btn').forEach(b => b.classList.remove('selected'));
            if(temp === 'Hot') document.getElementById('btnTempHot').classList.add('selected');
            if(temp === 'Cold') document.getElementById('btnTempCold').classList.add('selected');

            document.getElementById('iceGroup').style.display = (temp === 'Cold') ? 'block' : 'none';

            const sizeContainer = document.getElementById('sizeContainer');
            sizeContainer.innerHTML = '';
            
            const sizes = productData[pid].filter(v => v.temp === temp);
            
            sizes.forEach((v, index) => {
                const btn = document.createElement('div');
                btn.className = 'opt-btn';
                if(index === 0) btn.classList.add('selected');
                btn.innerHTML = `${v.size}<small>₱${parseFloat(v.price).toFixed(2)}</small>`;
                btn.onclick = function() {
                    document.querySelectorAll('#sizeContainer .opt-btn').forEach(b => b.classList.remove('selected'));
                    btn.classList.add('selected');
                    document.getElementById('inSize').value = v.size + "|" + v.price;
                    currentPrice = parseFloat(v.price);
                    updateTotalBtn();
                };
                sizeContainer.appendChild(btn);
                
                if(index === 0) {
                    document.getElementById('inSize').value = v.size + "|" + v.price;
                    currentPrice = parseFloat(v.price);
                }
            });
            updateTotalBtn();
        }

        function selectIce(level, elem) {
            document.getElementById('inIce').value = level;
            document.querySelectorAll('#iceContainer .opt-btn').forEach(b => b.classList.remove('selected'));
            elem.classList.add('selected');
        }

        function changeQty(change) {
            let q = parseInt(document.getElementById('qtyDisplay').value);
            q += change;
            if(q < 1) q = 1;
            document.getElementById('qtyDisplay').value = q;
            document.getElementById('inQty').value = q;
            updateTotalBtn();
        }

        function updateTotalBtn() {
            let q = parseInt(document.getElementById('inQty').value);
            let total = currentPrice * q;
            document.getElementById('btnAddLabel').innerText = "Add - ₱" + total.toFixed(2);
        }

        function closeModal() {
            document.getElementById('posModal').style.display = 'none';
        }

        function filterTab(tab) {
            document.querySelectorAll('.product-item').forEach(el => {
                if (el.dataset.tab === tab) {
                    el.style.display = 'flex'; 
                } else {
                    el.style.display = 'none';
                }
            });
            
            document.querySelectorAll('.menu-tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
        }

        function showSection(section) {
            if(section === 'drinks') {
                document.getElementById('drinks-wrapper').style.display = 'block';
                document.getElementById('snacks-wrapper').style.display = 'none';
                document.getElementById('btnDrinks').classList.add('active');
                document.getElementById('btnSnacks').classList.remove('active');
            } else {
                document.getElementById('drinks-wrapper').style.display = 'none';
                document.getElementById('snacks-wrapper').style.display = 'block';
                document.getElementById('btnDrinks').classList.remove('active');
                document.getElementById('btnSnacks').classList.add('active');
            }
        }
    </script>
</head>
<body>
<div class="container">
    <div class="header-bar">
        <h2>Barista Dashboard</h2>
        
        <a href="profile.php" class="user-badge">
            <?php echo htmlspecialchars($_SESSION['username']); ?> 
            <span class="role-icon">B</span>
        </a>
    </div>

    <div class="top-nav">
        <a href="barista_OOH.php" class="nav-btn">Online History</a> 
        <a href="barista_TH.php" class="nav-btn">POS History</a> 
        <a href="barista_label_module.php" class="nav-btn">🏷️ Custom Labeling</a>
        <a href="petty_cash.php" class="nav-btn">Petty Cash</a> 
        <a href="logout.php" class="nav-btn btn-red">Logout</a>
    </div>

    <?php if (isset($_SESSION['message_success'])): ?><p class="message-success"><?php echo $_SESSION['message_success']; unset($_SESSION['message_success']); ?></p><?php endif; ?>
    <?php if (isset($_SESSION['message_error'])): ?><p class="message-error"><?php echo $_SESSION['message_error']; unset($_SESSION['message_error']); ?></p><?php endif; ?>

    <div class="main-layout">
        <div class="menu-section">
            <div class="menu-toggles">
                <button class="toggle-btn active" id="btnDrinks" onclick="showSection('drinks')">☕ DRINKS MENU</button>
                <button class="toggle-btn" id="btnSnacks" onclick="showSection('snacks')">🍩 SNACKS & PASTRIES</button>
            </div>

            <div id="drinks-wrapper">
                <div class="drink-tabs">
                    <?php 
                    $first = true;
                    foreach($products_by_tab as $tab => $items): 
                        $active = $first ? 'active' : ''; $first = false;
                        $safe_id = md5($tab);
                    ?>
                        <button class="menu-tab-btn <?php echo $active; ?>" onclick="filterTab('<?php echo $safe_id; ?>')"><?php echo strtoupper($tab); ?></button>
                    <?php endforeach; ?>
                </div>
                <div class="product-grid">
                    <?php 
                    $first = true;
                    foreach($products_by_tab as $tab => $items): 
                        $display = $first ? 'flex' : 'none'; $first = false;
                        $safe_id = md5($tab);
                        foreach($items as $row): 
                            $pid = $row['id'];
                            $v_res = mysqli_query($conn, "SELECT * FROM product_variants WHERE product_id = $pid");
                            $variants = mysqli_fetch_all($v_res, MYSQLI_ASSOC);
                    ?>
                        <script>if(typeof productData === 'undefined') var productData = {}; productData[<?php echo $pid; ?>] = <?php echo json_encode($variants); ?>;</script>
                        <div class="product-card product-item" data-tab="<?php echo $safe_id; ?>" style="display: <?php echo $display; ?>;" onclick='openPOSModal(<?php echo htmlspecialchars(json_encode($row), ENT_QUOTES); ?>)'>
                            <div><h4><?php echo htmlspecialchars($row['name']); ?></h4><span class="price-tag">₱ <?php echo number_format($row['price'], 2); ?></span></div>
                            <button class="btn-options">View Options</button>
                        </div>
                    <?php endforeach; endforeach; ?>
                </div>
            </div>

            <div id="snacks-wrapper" style="display: none;">
                <div class="product-grid">
                    <?php foreach($snacks_list as $row): ?>
                        <div class="product-card" onclick='openPOSModal(<?php echo htmlspecialchars(json_encode($row), ENT_QUOTES); ?>)'>
                            <div><h4><?php echo htmlspecialchars($row['name']); ?></h4><span class="price-tag">₱ <?php echo number_format($row['price'], 2); ?></span></div>
                            <button class="btn-options">Add Item</button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="cart-section">
            <h3>Current POS Cart</h3>
            <div class="cart-items-container">
                <table class="cart-table">
                    <tr><th>Item</th><th>Qty</th><th>Total</th><th>X</th></tr>
                    <?php foreach ($_SESSION['cart'] as $key => $item): ?>
                    <tr>
                        <td><strong><?php echo $item['name']; ?></strong><br><small><?php echo $item['size']; ?> <?php if($item['temp']!='N/A') echo '| '.$item['temp']; ?></small></td>
                        <td><?php echo $item['quantity']; ?></td>
                        <td>₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                        <td><form method='post'><input type='hidden' name='cart_key' value='<?php echo $key; ?>'><button type='submit' name='remove_from_cart' class="btn-x">×</button></form></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
            <div class="cart-summary">
                
                <div style="margin-bottom:10px; padding-bottom:10px; border-bottom:1px dashed #ccc; font-size:13px; color:#555;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:5px;">
                        <span>Vatable Sales:</span>
                        <span>₱<?php echo number_format($vatable_sales, 2); ?></span>
                    </div>
                    <div style="display:flex; justify-content:space-between;">
                        <span>VAT (12%):</span>
                        <span>₱<?php echo number_format($vat_amount, 2); ?></span>
                    </div>
                </div>

                <div class="total-line"><span>Total:</span><span>₱ <?php echo number_format($total, 2); ?></span></div>
                <form action="" method="post">
                    <input type="hidden" name="total_amount" id="cartTotalValue" value="<?php echo $total; ?>">
                    
                    <input type="number" step="0.01" name="amount_received" id="amountReceivedInput" class="pay-input" placeholder="Enter Cash" oninput="checkPay()" required>
                    <button type="submit" name="process_payment" id="processPaymentBtn" class="btn-pay" disabled>PROCESS PAYMENT</button>
                </form>
                <form action="" method="post" style="margin-top:5px;"><button type="submit" name="clear_cart" class="btn-clear">Clear Cart</button></form>
            </div>
        </div>
    </div>
</div>

<div id="posModal" class="modal-overlay">
    <form class="modal-box" method="post">
        <h3 id="mTitle">Customize Item</h3>
        
        <input type="hidden" name="product_id" id="mId">
        <input type="hidden" name="product_name" id="mNameIn">
        <input type="hidden" name="temp_selection" id="inTemp">
        <input type="hidden" name="size_option" id="inSize">
        <input type="hidden" name="ice_level" id="inIce" value="Normal Ice">
        <input type="hidden" name="price" id="mPrice">
        <input type="hidden" name="quantity" id="inQty" value="1">

        <div id="variantArea">
            <div class="option-group">
                <label>Temperature</label>
                <div class="option-grid">
                    <div class="opt-btn temp-btn" id="btnTempHot" onclick="selectTemp('Hot', document.getElementById('mId').value)">🔥 HOT</div>
                    <div class="opt-btn temp-btn" id="btnTempCold" onclick="selectTemp('Cold', document.getElementById('mId').value)">❄️ COLD</div>
                </div>
            </div>

            <div class="option-group">
                <label>Size</label>
                <div class="option-grid" id="sizeContainer">
                    </div>
            </div>

            <div class="option-group" id="iceGroup" style="display:none;">
                <label>Ice Level</label>
                <div class="option-grid" id="iceContainer">
                    <div class="opt-btn selected" onclick="selectIce('Normal Ice', this)">Normal</div>
                    <div class="opt-btn" onclick="selectIce('Less Ice', this)">Less Ice</div>
                    <div class="opt-btn" onclick="selectIce('No Ice', this)">No Ice</div>
                </div>
            </div>
        </div>

        <div id="singleArea" style="display:none; text-align:center; padding:20px; color:#555;">
            <p>Standard item price. No customization options.</p>
        </div>

        <div class="option-group">
            <label>Quantity</label>
            <div class="qty-wrapper">
                <button type="button" class="qty-btn" onclick="changeQty(-1)">-</button>
                <input type="text" class="qty-display" id="qtyDisplay" value="1" readonly>
                <button type="button" class="qty-btn" onclick="changeQty(1)">+</button>
            </div>
        </div>

        <div class="modal-actions">
            <button type="button" class="btn-modal-cancel" onclick="closeModal()">Cancel</button>
            <button type="submit" name="add_to_cart" class="btn-modal-add">
                <span id="btnAddLabel">Add</span>
                <span>➜</span>
            </button>
        </div>
    </form>
</div>

<script>
    function checkPay() {
        var totalElement = document.getElementById('cartTotalValue');
        var totalAmount = totalElement ? parseFloat(totalElement.value) : 0;

        var cashInput = document.getElementById('amountReceivedInput');
        var cashAmount = parseFloat(cashInput.value);
        
        var payBtn = document.getElementById('processPaymentBtn');

        if (!isNaN(cashAmount) && cashAmount >= totalAmount && totalAmount > 0) {
            payBtn.disabled = false;
            payBtn.style.opacity = "1";
            payBtn.style.cursor = "pointer";
        } else {
            payBtn.disabled = true;
            payBtn.style.opacity = "0.5";
            payBtn.style.cursor = "not-allowed";
        }
    }

    <?php if(isset($_SESSION['last_transaction_id'])): ?>
    document.addEventListener("DOMContentLoaded", function() {
        var txnId = <?php echo $_SESSION['last_transaction_id']; ?>;
        var printUrl = "receipt_module_barista.php?type=pos&id=" + txnId;
        window.open(printUrl, 'Receipt', 'height=600,width=400,scrollbars=yes');
    });
    <?php unset($_SESSION['last_transaction_id']); endif; ?>
</script>
</body>
</html>