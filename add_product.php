<?php
session_start();
include 'db.php';

// Check Auth
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] != 'admin') {
    header("location: login.php");
    exit;
}

$error = "";

// 1. Get Categories
$categories = [];
$cat_sql = "SELECT DISTINCT category FROM products ORDER BY category ASC";
$cat_res = mysqli_query($conn, $cat_sql);
while($row = mysqli_fetch_assoc($cat_res)) {
    if(!empty($row['category'])) $categories[] = $row['category'];
}
$defaults = ['Coffee', 'Non-Coffee', 'Snack', 'Pastry', 'Food', 'Dessert'];
foreach($defaults as $d) {
    if(!in_array($d, $categories)) $categories[] = $d;
}
sort($categories);

// 2. Get Subcategories
$subcategories = [];
$check_sub = mysqli_query($conn, "SHOW TABLES LIKE 'drinks_subcategories'");
if(mysqli_num_rows($check_sub) > 0) {
    $sub_sql = "SELECT * FROM drinks_subcategories ORDER BY sort_order ASC";
    $sub_res = mysqli_query($conn, $sub_sql);
    while($row = mysqli_fetch_assoc($sub_res)) {
        $subcategories[] = $row['name'];
    }
}

// === NEW FUNCTION: Get Ingredients for Recipe Builder ===
$all_ingredients = [];
$ing_res = mysqli_query($conn, "SELECT * FROM ingredients ORDER BY name ASC");
if($ing_res) {
    while($row = mysqli_fetch_assoc($ing_res)) {
        $all_ingredients[] = $row;
    }
}

// 3. Handle Form Submit
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    // $stock is no longer taken from POST; we set it to 0 below.
    $pricing_type = $_POST['pricing_type']; 
    
    // Category Logic
    if ($_POST['category'] === 'new_cat_option') {
        $category = mysqli_real_escape_string($conn, trim($_POST['new_category_text']));
        if(empty($category)) $category = "General"; 
    } else {
        $category = mysqli_real_escape_string($conn, $_POST['category']);
    }

    // Subcategory Logic
    $subcategory = NULL;
    if (!empty($_POST['subcategory'])) {
        $subcategory = mysqli_real_escape_string($conn, $_POST['subcategory']);
    }
    
    // Pricing Logic
    $base_price = 0;
    $variants_data = [];

    if ($pricing_type === 'variable') {
        if (isset($_POST['hot_prices'])) {
            foreach ($_POST['hot_prices'] as $size => $price) {
                if (!empty($price)) {
                    $p = floatval($price);
                    $variants_data[] = ['size' => $size, 'temp' => 'Hot', 'price' => $p];
                    if ($base_price == 0 || $p < $base_price) $base_price = $p;
                }
            }
        }
        if (isset($_POST['cold_prices'])) {
            foreach ($_POST['cold_prices'] as $size => $price) {
                if (!empty($price)) {
                    $p = floatval($price);
                    $variants_data[] = ['size' => $size, 'temp' => 'Cold', 'price' => $p];
                    if ($base_price == 0 || $p < $base_price) $base_price = $p;
                }
            }
        }
    } else {
        $base_price = floatval($_POST['single_price']);
    }

    $has_sizes = !empty($variants_data) ? 1 : 0;
    $temp_option = 'none';
    $has_hot = false; $has_cold = false;
    foreach($variants_data as $v) { if($v['temp']=='Hot') $has_hot=true; if($v['temp']=='Cold') $has_cold=true; }
    if ($has_hot && $has_cold) $temp_option = 'both';
    elseif ($has_hot) $temp_option = 'hot_only';
    elseif ($has_cold) $temp_option = 'cold_only';

    // Image Upload
    $image_path = "placeholder.png";
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
        $target_dir = "product_images/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
        $new_filename = time() . "_" . uniqid() . "." . strtolower(pathinfo($_FILES["product_image"]["name"], PATHINFO_EXTENSION));
        move_uploaded_file($_FILES["product_image"]["tmp_name"], $target_dir . $new_filename);
        $image_path = $new_filename;
    }

    // Insert DB - Stock is now hardcoded to 0 in the query
    $sql = "INSERT INTO products (name, price, category, subcategory, stock, image_path, has_sizes, temp_option) VALUES (?, ?, ?, ?, 0, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "sdssisi", $name, $base_price, $category, $subcategory, $image_path, $has_sizes, $temp_option);
    
    if (mysqli_stmt_execute($stmt)) {
        $product_id = mysqli_insert_id($conn);
        if ($has_sizes) {
            $sql_var = "INSERT INTO product_variants (product_id, size, temp, price) VALUES (?, ?, ?, ?)";
            $stmt_var = mysqli_prepare($conn, $sql_var);
            foreach ($variants_data as $v) {
                mysqli_stmt_bind_param($stmt_var, "issd", $product_id, $v['size'], $v['temp'], $v['price']);
                mysqli_stmt_execute($stmt_var);
            }
        }

        // === NEW FUNCTION: Save Recipe Links ===
        if (isset($_POST['ing_id']) && is_array($_POST['ing_id'])) {
            $sql_rec = "INSERT INTO recipes (product_id, ingredient_id, amount_needed) VALUES (?, ?, ?)";
            $stmt_rec = mysqli_prepare($conn, $sql_rec);
            foreach ($_POST['ing_id'] as $idx => $i_id) {
                $amt = floatval($_POST['ing_amt'][$idx]);
                if ($i_id > 0 && $amt > 0) {
                    mysqli_stmt_bind_param($stmt_rec, "iid", $product_id, $i_id, $amt);
                    mysqli_stmt_execute($stmt_rec);
                }
            }
        }

        header("location: admin_products.php?success=1");
        exit;
    } else {
        $error = "Error: " . mysqli_error($conn);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Product | Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-brown: #5D4037; --dark-brown: #3E2723; --light-bg: #f4f7f6;
            --white: #ffffff; --accent-blue: #1976D2; --accent-green: #2E7D32;
            --sidebar-width: 260px; --header-height: 70px;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--light-bg);
            color: #333;
            display: flex;
            min-height: 100vh;
            font-size: 15px;
        }
        a { text-decoration: none; color: inherit; }

        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--dark-brown) 0%, #2d1b18 100%);
            color: white;
            position: fixed;
            height: 100vh;
            left: 0; top: 0;
            display: flex; flex-direction: column;
            z-index: 100;
            box-shadow: 4px 0 15px rgba(0,0,0,0.1);
        }
        .brand-logo {
            height: var(--header-height);
            display: flex; align-items: center; padding: 0 25px;
            font-size: 1.3rem; font-weight: 700; letter-spacing: 0.5px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .nav-links { flex: 1; padding: 20px 0; overflow-y: auto; }
        .nav-item {
            display: block; padding: 15px 25px;
            color: rgba(255,255,255,0.7); font-weight: 500; font-size: 0.95rem;
            border-left: 4px solid transparent; cursor: pointer; transition: all 0.3s;
        }
        .nav-item:hover, .nav-item.active { background: rgba(255,255,255,0.05); color: white; border-left-color: #A1887F; }
        
        .submenu-container { display: none; background: rgba(0,0,0,0.2); }
        .submenu-container.show { display: block; }
        .sub-link { padding-left: 45px; font-size: 0.85rem; }
        
        .sidebar-footer { padding: 20px; font-size: 0.8rem; text-align: center; border-top: 1px solid rgba(255,255,255,0.1); color:rgba(255,255,255,0.4); }
        .dropdown-btn { display: flex; justify-content: space-between; align-items: center; }

        .main-content { margin-left: var(--sidebar-width); flex: 1; display: flex; flex-direction: column; }
        
        .top-header {
            height: var(--header-height);
            background: white; display: flex; justify-content: space-between; align-items: center;
            padding: 0 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .page-title { font-size: 1.4rem; font-weight: 700; color: var(--primary-brown); }
        .user-badge {
            padding: 8px 15px; background: var(--light-bg); border-radius: 20px;
            font-weight: 600; font-size: 0.85rem; color: var(--primary-brown); cursor: pointer;
            display: flex; align-items: center; gap: 8px;
        }
        
        .content-scroll { padding: 30px; overflow-y: auto; }
        
        .profile-dropdown-container { position: relative; }
        .dropdown-content { display: none; position: absolute; right: 0; top: 130%; background: white; box-shadow: 0 10px 30px rgba(0,0,0,0.1); border-radius: 12px; min-width: 180px; overflow: hidden; border: 1px solid #eee; z-index: 200; }
        .dropdown-content.show { display: block; animation: fadeIn 0.2s ease; }
        .dropdown-content a { display: block; padding: 12px 20px; font-size: 0.9rem; transition: background 0.2s; }
        .dropdown-content a:hover { background: #f9f9f9; }
        @keyframes fadeIn { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:translateY(0); } }

        .form-card {
            background: white; padding: 40px; border-radius: 16px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05); border: 1px solid rgba(0,0,0,0.02);
            max-width: 900px; margin: 0 auto;
        }
        
        .form-group { margin-bottom: 25px; position: relative; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #555; font-size: 0.9rem; }
        
        input[type="text"], input[type="number"], select {
            width: 100%; padding: 12px 15px; border: 1px solid #e0e0e0; border-radius: 8px;
            font-size: 0.95rem; color: #333; background-color: #fcfcfc;
            transition: all 0.3s ease; box-sizing: border-box; font-family: inherit;
        }
        input[type="text"]:focus, input[type="number"]:focus, select:focus {
            border-color: var(--primary-brown); background-color: #fff;
            box-shadow: 0 0 0 3px rgba(93, 64, 55, 0.1); outline: none;
        }
        
        input[type="file"] { padding: 10px; background: #f9f9f9; border: 1px dashed #ccc; border-radius: 8px; cursor: pointer; width: 100%; }

        .radio-group { display: flex; gap: 15px; }
        .radio-group label {
            flex: 1; cursor: pointer; padding: 15px; background: #fff;
            border: 2px solid #eee; border-radius: 10px; text-align: center;
            font-weight: 600; color: #777; transition: all 0.2s ease;
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .radio-group input[type="radio"] { display: none; }
        .radio-group label:has(input:checked) {
            border-color: var(--primary-brown); background-color: #EFEBE9;
            color: var(--primary-brown); box-shadow: 0 4px 10px rgba(93, 64, 55, 0.15);
        }

        .tabs {
            display: flex; gap: 10px; margin-bottom: 20px;
            background: #f4f7f6; padding: 5px; border-radius: 12px; width: fit-content;
        }
        .tab {
            padding: 10px 30px; background: transparent; border-radius: 8px;
            font-weight: 600; color: #777; transition: all 0.3s ease;
            cursor: pointer; font-size: 0.9rem;
        }
        .tab:hover { color: #333; background: rgba(0,0,0,0.05); }
        .tab.active {
            background: #fff; color: var(--primary-brown);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1); transform: scale(1.02);
        }
        
        .tab-content {
            display: none; padding: 25px; border: 1px solid #eee;
            background: #fff; border-radius: 12px; animation: fadeIn 0.3s ease;
        }
        .tab-content.active { display: block; }
        
        .price-row {
            display: flex; align-items: center; margin-bottom: 12px;
            padding: 8px 0; border-bottom: 1px dashed #f0f0f0;
        }
        .price-row label { width: 100px; margin: 0; color: #666; font-size: 0.9rem; font-weight: 500; }

        #new-cat-div { display: none; margin-top: 15px; animation: slideDown 0.3s ease; }
        #new-cat-div input { border: 2px solid #64B5F6; background: #E3F2FD; color: #0D47A1; font-weight: 600; }
        @keyframes slideDown { from { opacity:0; transform:translateY(-10px); } to { opacity:1; transform:translateY(0); } }
        
        #subcategory-div { display: none; margin-top: 20px; padding-top:20px; border-top: 1px dashed #eee;}

        .btn-submit {
            background: linear-gradient(135deg, #5D4037 0%, #3e2723 100%);
            color: white; border: none; padding: 15px; border-radius: 10px;
            cursor: pointer; font-size: 1.1rem; font-weight: 700; width: 100%;
            margin-top: 20px; box-shadow: 0 4px 15px rgba(93, 64, 55, 0.3);
            transition: all 0.3s ease; letter-spacing: 0.5px; font-family: inherit;
        }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(93, 64, 55, 0.4); }
        
        .error-msg { background: #FFEBEE; color: #C62828; padding: 15px; border-radius: 8px; margin-bottom: 20px; }

        /* === NEW RECIPE BUILDER STYLES === */
        .recipe-builder-card {
            background: #fafafa; border-radius: 12px; padding: 20px; border: 1px solid #eee; margin: 25px 0;
        }
        .recipe-builder-title { font-weight: 700; color: var(--primary-brown); margin-bottom: 15px; font-size: 1rem; display: flex; align-items: center; gap: 8px; }
        .recipe-row-item { display: grid; grid-template-columns: 2fr 1fr auto; gap: 10px; margin-bottom: 10px; align-items: center; }
        .btn-add-ingredient { background: #E8F5E9; color: #2E7D32; border: 1px dashed #2E7D32; padding: 10px; border-radius: 8px; width: 100%; cursor: pointer; font-weight: 600; transition: 0.2s; }
        .btn-add-ingredient:hover { background: #C8E6C9; }
        .btn-remove-ingredient { background: #FFEBEE; color: #C62828; border: none; padding: 10px; border-radius: 8px; cursor: pointer; }
    </style>
    
    <script>
        function toggleCategory() {
            var select = document.getElementById('category');
            var selectedVal = select.value.toLowerCase();
            var subDiv = document.getElementById('subcategory-div');
            var inputDiv = document.getElementById('new-cat-div');

            if(select.value === 'new_cat_option') {
                if(inputDiv) {
                    inputDiv.style.display = 'block';
                    document.getElementById('new_category_text').required = true;
                    document.getElementById('new_category_text').focus();
                }
            } else {
                if(inputDiv) {
                    inputDiv.style.display = 'none';
                    document.getElementById('new_category_text').required = false;
                }
            }

            if(subDiv) {
                if (selectedVal === 'drink' || selectedVal === 'coffee' || select.value === 'new_cat_option') {
                    subDiv.style.display = 'block';
                } else {
                    subDiv.style.display = 'none';
                    var subSelect = document.getElementById('subcategory');
                    if(subSelect) subSelect.value = "";
                }
            }
        }

        function togglePricing() {
            var radios = document.getElementsByName('pricing_type');
            var type = 'variable';
            for (var i = 0; i < radios.length; i++) {
                if (radios[i].checked) {
                    type = radios[i].value;
                    break;
                }
            }
            if(type === 'variable') {
                document.getElementById('variable-pricing').style.display = 'block';
                document.getElementById('single-pricing').style.display = 'none';
            } else {
                document.getElementById('variable-pricing').style.display = 'none';
                document.getElementById('single-pricing').style.display = 'block';
            }
        }

        function openTab(evt, tabName) {
            var i, x, tablinks;
            x = document.getElementsByClassName("tab-content");
            for (i = 0; i < x.length; i++) { x[i].style.display = "none"; }
            tablinks = document.getElementsByClassName("tab");
            for (i = 0; i < tablinks.length; i++) { tablinks[i].className = tablinks[i].className.replace(" active", ""); }
            document.getElementById(tabName).style.display = "block";
            evt.currentTarget.className += " active";
        }

        function toggleSidebarMenu(menuId) { document.getElementById(menuId).classList.toggle("show"); }
        function toggleProfileMenu() { document.getElementById("profileDropdown").classList.toggle("show"); }

        function addIngredientRow() {
            const container = document.getElementById('recipe-rows-container');
            const div = document.createElement('div');
            div.className = 'recipe-row-item';
            div.innerHTML = `
                <select name="ing_id[]" required>
                    <option value="">-- Select Ingredient --</option>
                    <?php foreach($all_ingredients as $ing): ?>
                        <option value="<?php echo $ing['id']; ?>"><?php echo htmlspecialchars($ing['name']); ?> (<?php echo $ing['unit']; ?>)</option>
                    <?php endforeach; ?>
                </select>
                <input type="number" name="ing_amt[]" step="0.01" placeholder="Amt" required>
                <button type="button" class="btn-remove-ingredient" onclick="this.parentElement.remove()">✕</button>
            `;
            container.appendChild(div);
        }
    </script>
</head>
<body onload="togglePricing(); toggleCategory();">

    <nav class="sidebar">
        <div class="brand-logo">HOSSANA CAFE</div>
        <div class="nav-links">
            <a href="admin_dashboard.php" class="nav-item">📊 Dashboard</a>
            
            <div class="nav-item dropdown-btn active" style="background:rgba(255,255,255,0.05); border-left-color: #A1887F;" onclick="toggleSidebarMenu('productMenu')">
                <span>☕ Products & Menu</span><span style="font-size: 10px;">▼</span>
            </div>
            
            <div id="productMenu" class="submenu-container show">
                <a href="add_product.php" class="nav-item sub-link" style="color:white; font-weight:700; background:rgba(255,255,255,0.1);">➕ Add Product</a>
                <a href="admin_products.php" class="nav-item sub-link">📋 Product List</a>
                <a href="manage_subcategories.php" class="nav-item sub-link">📂 Manage Subcategories</a>
            </div>

            <a href="admin_sales_report.php" class="nav-item">💰 Sales Reports</a>
            <a href="inventory.php" class="nav-item">📦 Inventory</a>
            <div class="nav-item" onclick="toggleSidebarMenu('userManage')" style="cursor:pointer;">👥 User Management <span style="float:right; font-size:10px;">▼</span></div>
            <div id="userManage" class="submenu-container">
                <a href="add_barista.php" class="nav-item sub-link">User Overview</a>
                <a href="add_barista.php" class="nav-item sub-link">Baristas</a>
                <a href="add_rider.php" class="nav-item sub-link">Riders</a>
        
            </div>
        </div>
        <div class="sidebar-footer">Logged in as Admin</div>
    </nav>

    <main class="main-content">
        <header class="top-header">
            <div class="page-title">Add New Product</div>
            <div class="profile-dropdown-container">
                <div class="user-badge" onclick="toggleProfileMenu()">
                    Admin Account <span style="font-size:10px;">▼</span>
                </div>
                <div id="profileDropdown" class="dropdown-content">
                    <a href="admin_profile.php">👤 Profile</a>
                    <a href="logout.php" style="color:#c62828;">🚪 Logout</a>
                </div>
            </div>
        </header>

        <div class="content-scroll">
            
            <?php if ($error): ?>
                <div class="error-msg">❌ <?php echo $error; ?></div>
            <?php endif; ?>

            <div class="form-card">
                <h3 style="margin-top:0; color:#5D4037; border-bottom:1px solid #eee; padding-bottom:15px; font-size:1.2rem;">Enter Product Details</h3>
                
                <form action="" method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>Category</label>
                        <select name="category" id="category" onchange="toggleCategory()" required>
                            <?php foreach($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo ucfirst($cat); ?></option>
                            <?php endforeach; ?>
                            <option value="new_cat_option" style="font-weight:bold; color:#1976D2;">+ Add New Category...</option>
                        </select>
                        
                        <div id="new-cat-div">
                            <input type="text" name="new_category_text" id="new_category_text" placeholder="Type the New Category Name here..." style="margin-top:10px;">
                        </div>

                        <div id="subcategory-div">
                            <label>Subcategory (Optional)</label>
                            <select name="subcategory" id="subcategory">
                                <option value="">-- None --</option>
                                <?php foreach($subcategories as $sub): ?>
                                    <option value="<?php echo htmlspecialchars($sub); ?>"><?php echo htmlspecialchars($sub); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Product Name</label>
                        <input type="text" name="name" required placeholder="e.g. Wintermelon Milk Tea">
                    </div>

                    <div class="recipe-builder-card">
                        <div class="recipe-builder-title">📋 Inventory Deduction Recipe</div>
                        <p style="font-size: 0.8rem; color: #777; margin-bottom: 10px;">Link ingredients (beans, cups, etc.) to deduct automatically when sold.</p>
                        <div id="recipe-rows-container">
                        </div>
                        <button type="button" class="btn-add-ingredient" onclick="addIngredientRow()">+ Add Ingredient to Recipe</button>
                    </div>

                    <div class="form-group">
                        <label>Pricing Strategy</label>
                        <div class="radio-group">
                            <label>
                                <input type="radio" name="pricing_type" value="variable" checked onclick="togglePricing()">
                                <span>🥤 Variable (Sizes)</span>
                            </label>
                            <label>
                                <input type="radio" name="pricing_type" value="single" onclick="togglePricing()">
                                <span>🍪 Single Price</span>
                            </label>
                        </div>
                    </div>

                    <div id="variable-pricing">
                        <div class="tabs">
                            <div class="tab active" onclick="openTab(event, 'Hot')">🔥 Hot</div>
                            <div class="tab" onclick="openTab(event, 'Cold')">❄️ Cold</div>
                        </div>
                        <div id="Hot" class="tab-content active">
                            <?php foreach(['8oz','10oz','12oz',] as $s): ?>
                            <div class="price-row"><label><?=$s?>:</label> <input type="number" name="hot_prices[<?=$s?>]" step="0.01" placeholder="0.00"></div>
                            <?php endforeach; ?>
                        </div>
                        <div id="Cold" class="tab-content">
                            <?php foreach(['12oz','16oz','22oz'] as $s): ?>
                            <div class="price-row"><label><?=$s?>:</label> <input type="number" name="cold_prices[<?=$s?>]" step="0.01" placeholder="0.00"></div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div id="single-pricing" style="display:none;" class="form-group">
                        <label>Price (₱)</label>
                        <input type="number" name="single_price" step="0.01" placeholder="0.00">
                    </div>

                    <div class="form-group">
                        <label>Product Image</label>
                        <input type="file" name="product_image">
                    </div>

                    <div style="margin-top:30px;">
                        <input type="submit" value="Save Product & Recipe" class="btn-submit">
                    </div>
                </form>
            </div>
            <div style="height:50px;"></div>
        </div>
    </main>
</body>
</html>