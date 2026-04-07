<?php
session_start();
include 'db.php';

// 1. AUTH & SETUP
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] != 'admin') { header("location: login.php"); exit; }

$target_dir = "product_images/";
if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);

// 2. FETCH SUBCATEGORIES (for dropdown)
$drinks_subcategories_list = [];
$result_subcats = mysqli_query($conn, "SELECT name FROM drinks_subcategories ORDER BY sort_order ASC, name ASC");
while ($row = mysqli_fetch_assoc($result_subcats)) $drinks_subcategories_list[] = $row['name'];

$product_id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_POST['product_id']) ? intval($_POST['product_id']) : 0);
$error_message = "";
$product_data = null;
// Array to hold existing variant prices [Temp][Size] => Price
$current_variants = ['Hot' => [], 'Cold' => []]; 

if ($product_id > 0) {
    // 3. FETCH MAIN PRODUCT DATA
    $stmt_fetch = mysqli_prepare($conn, "SELECT id, name, price, category, subcategory, image_path FROM products WHERE id = ?");
    mysqli_stmt_bind_param($stmt_fetch, "i", $product_id);
    mysqli_stmt_execute($stmt_fetch);
    $product_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_fetch));
    mysqli_stmt_close($stmt_fetch);

    if (!$product_data) { header("location: admin_products.php?error=Product not found."); exit; }

    // 4. FETCH EXISTING VARIANTS (If it's a drink)
    if($product_data['category'] == 'drink') {
        $stmt_var = mysqli_prepare($conn, "SELECT size, temp, price FROM product_variants WHERE product_id = ?");
        mysqli_stmt_bind_param($stmt_var, "i", $product_id);
        mysqli_stmt_execute($stmt_var);
        $result_var = mysqli_stmt_get_result($stmt_var);
        while($row_var = mysqli_fetch_assoc($result_var)){
            // Store like: $current_variants['Hot']['8oz'] = 120.00;
            $current_variants[$row_var['temp']][$row_var['size']] = $row_var['price'];
        }
        mysqli_stmt_close($stmt_var);
    }

    // 5. HANDLE FORM SUBMISSION
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $name = trim($_POST['name']);
        // We'll determine the final base price later based on variants
        $submitted_base_price = floatval($_POST['price']); 
        $category = $_POST['category'];
        $subcategory = ($category == 'drink' && isset($_POST['subcategory'])) ? trim($_POST['subcategory']) : null; 
        $current_image_path = $product_data['image_path']; 
        $uploadOk = 1;

        // --- A. Image Upload Logic ---
        if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
            $file_name = basename($_FILES["product_image"]["name"]);
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $unique_name = time() . '_' . uniqid() . '.' . $file_ext;
            $target_file = $target_dir . $unique_name;
            
            if ($_FILES["product_image"]["size"] > 5000000) { $error_message = "File too large."; $uploadOk = 0; }
            if(!in_array($file_ext, ['jpg','jpeg','png','gif'])) { $error_message = "Invalid file format."; $uploadOk = 0; }

            if ($uploadOk == 1 && move_uploaded_file($_FILES["product_image"]["tmp_name"], $target_file)) {
                if ($current_image_path != 'placeholder.png' && file_exists($target_dir . $current_image_path)) {
                    unlink($target_dir . $current_image_path);
                }
                $current_image_path = $unique_name;
            } else { $uploadOk = 0; }
        }
        
        if ($uploadOk == 1) { 
            // --- B. Update Main Product Table ---
            // We temporarily update with submitted price, will adjust after processing variants
            $stmt_update = mysqli_prepare($conn, "UPDATE products SET name=?, price=?, category=?, subcategory=?, image_path=? WHERE id=?");
            mysqli_stmt_bind_param($stmt_update, "sdsssi", $name, $submitted_base_price, $category, $subcategory, $current_image_path, $product_id); 
            mysqli_stmt_execute($stmt_update);
            mysqli_stmt_close($stmt_update);

            // --- C. Process Variants (If Drink) ---
            $lowest_price = null;
            $has_sizes_flag = 0;

            if ($category == 'drink') {
                // 1. Delete existing variants to replace with new data
                mysqli_query($conn, "DELETE FROM product_variants WHERE product_id = $product_id");

                // 2. Prepare insert statement
                $stmt_insert_var = mysqli_prepare($conn, "INSERT INTO product_variants (product_id, size, temp, price) VALUES (?, ?, ?, ?)");
                
                // Helper function to process price arrays
                $process_prices = function($temp, $prices_array) use ($stmt_insert_var, $product_id, &$lowest_price, &$has_sizes_flag) {
                    if (isset($prices_array) && is_array($prices_array)) {
                        foreach ($prices_array as $size => $price) {
                            if (!empty($price) && is_numeric($price)) {
                                $p = floatval($price);
                                mysqli_stmt_bind_param($stmt_insert_var, "issd", $product_id, $size, $temp, $p);
                                mysqli_stmt_execute($stmt_insert_var);
                                $has_sizes_flag = 1;
                                // Find lowest price for base price
                                if ($lowest_price === null || $p < $lowest_price) $lowest_price = $p;
                            }
                        }
                    }
                };

                // Process Hot and Cold posts
                $process_prices('Hot', $_POST['hot_prices'] ?? []);
                $process_prices('Cold', $_POST['cold_prices'] ?? []);
                mysqli_stmt_close($stmt_insert_var);
            }

            // --- D. Finalize Base Price & Has Sizes Flag ---
            // If variants exist, base price is the lowest variant. Else, use the submitted single price.
            $final_base_price = ($has_sizes_flag == 1 && $lowest_price !== null) ? $lowest_price : $submitted_base_price;
            
            // Update product with final price and flag
            mysqli_query($conn, "UPDATE products SET price = $final_base_price, has_sizes = $has_sizes_flag WHERE id = $product_id");

            header("location: admin_products.php?success_product_updated=" . urlencode($name));
            exit; 
        }
    }
} else { header("location: admin_products.php?error=Invalid ID."); exit; }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Product</title>
    <link rel="stylesheet" href="admin_style.css">
    <style>
        /* STYLES RETAINED FROM PREVIOUS VERSION */
        .preview-container { text-align: center; margin-bottom: 30px; padding: 20px; background: #fafafa; border-radius: 12px; border: 1px dashed #ccc; }
        .preview-img { max-width: 150px; height: auto; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .btn-update { background: linear-gradient(135deg, #1976D2 0%, #1565C0 100%); color: white; border: none; padding: 15px; border-radius: 10px; cursor: pointer; font-size: 1.1rem; font-weight: bold; width: 100%; margin-top: 10px; box-shadow: 0 4px 15px rgba(25, 118, 210, 0.3); transition: all 0.3s ease; }
        .btn-update:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(25, 118, 210, 0.4); }
        .form-group label { font-weight: 600; color: #555; margin-bottom: 8px; display: block; }
        input[type="text"], input[type="number"], select { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; box-sizing: border-box; font-size: 1rem; }
        input[type="file"] { padding: 10px; background: #f9f9f9; width: 100%; border-radius: 8px; border: 1px solid #ddd; }
        
        /* --- NEW STYLES FOR VARIANT TABS (Copied from add_product.php) --- */
        .tabs { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: none; margin-top: 10px; background: #f4f7f6; padding: 5px; border-radius: 12px; width: fit-content; }
        .tab { padding: 10px 25px; background: transparent; margin: 0; border-radius: 8px; font-weight: 600; color: #777; transition: all 0.3s ease; border: none; cursor: pointer; }
        .tab.active { background: #fff; color: #5D4037; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .tab-content { display: none; padding: 25px; border: 1px solid #eee; background: #fff; border-radius: 12px; }
        .tab-content.active { display: block; }
        .price-row { display: flex; align-items: center; margin-bottom: 12px; padding: 5px 0; border-bottom: 1px dashed #f0f0f0; }
        .price-row label { width: 120px; margin: 0; color: #666; font-size: 0.9rem; }
        #variant-pricing-container { display: none; margin-top: 30px; border-top: 2px dashed #eee; padding-top: 20px; }
    </style>
    <script>
        function toggleSidebarMenu(menuId) { document.getElementById(menuId).classList.toggle("show"); }
        function toggleProfileMenu() { document.getElementById("profileDropdown").classList.toggle("show"); }
        
        // Updated Toggle Function
        function toggleCategorySettings() {
            const categorySelect = document.getElementById('category');
            const subcategoryGroup = document.getElementById('subcategory-group');
            const variantContainer = document.getElementById('variant-pricing-container');
            const singlePriceInput = document.getElementById('price');
            
            if (categorySelect.value === 'drink') {
                // Show Drinks options
                subcategoryGroup.style.display = 'block';
                variantContainer.style.display = 'block';
                // Optional: Disable single price input if using variants, or keep it as fallback base price
                // singlePriceInput.setAttribute('readonly', true); 
                // singlePriceInput.style.background = '#eee';
            } else {
                // Hide Drinks options
                subcategoryGroup.style.display = 'none';
                variantContainer.style.display = 'none';
                // singlePriceInput.removeAttribute('readonly');
                // singlePriceInput.style.background = '#fff';
            }
        }

        // Tab Function
        function openTab(evt, tabName) {
            var i, x, tablinks;
            x = document.getElementsByClassName("tab-content");
            for (i = 0; i < x.length; i++) { x[i].style.display = "none"; }
            tablinks = document.getElementsByClassName("tab");
            for (i = 0; i < tablinks.length; i++) { tablinks[i].className = tablinks[i].className.replace(" active", ""); }
            document.getElementById(tabName).style.display = "block";
            evt.currentTarget.className += " active";
        }
    </script>
</head>
<body onload="toggleCategorySettings()">

    <nav class="sidebar">
        <div class="brand-logo">HOSSANA CAFE</div>
        <div class="nav-links">
            <a href="admin_dashboard.php" class="nav-item">📊 Dashboard</a>
            <div class="nav-item dropdown-btn active" onclick="toggleSidebarMenu('productMenu')">
                <span>☕ Products & Menu</span><span style="font-size: 10px;">▼</span>
            </div>
            <div id="productMenu" class="submenu-container show">
                <a href="add_product.php" class="nav-item sub-link">➕ Add Product</a>
                <a href="admin_products.php" class="nav-item sub-link" style="color:white; background:rgba(255,255,255,0.1);">📋 Product List</a>
                <a href="manage_subcategories.php" class="nav-item sub-link">📂 Manage Subcategories</a>
            </div>
            <a href="admin_sales_report.php" class="nav-item">💰 Sales Reports</a>
            <a href="inventory.php" class="nav-item">📦 Inventory</a>
            <a href="manage_users.php" class="nav-item">👥 User Management</a>
            <a href="online_orders.php" class="nav-item">🔔 Online Orders</a>
        </div>
        <div class="sidebar-footer">Logged in as Admin</div>
    </nav>

    <main class="main-content">
        <header class="top-header">
            <div class="page-title">Edit Product</div>
            <div class="profile-dropdown-container">
                <div class="user-badge" onclick="toggleProfileMenu()">Admin Account <span style="font-size:10px;">▼</span></div>
                <div id="profileDropdown" class="dropdown-content"><a href="admin_profile.php">👤 Profile</a><a href="logout.php" class="logout-link">🚪 Logout</a></div>
            </div>
        </header>

        <div class="content-scroll">
            <?php if (!empty($error_message)): ?>
                <div class="message-error" style="background:#ffebee; color:#c62828; padding:15px; border-radius:8px; margin-bottom:20px;">❌ <?php echo $error_message; ?></div>
            <?php endif; ?>

            <div class="form-card" style="background:white; padding:40px; border-radius:16px; box-shadow:0 10px 30px rgba(0,0,0,0.05);">
                <h3 style="margin-top:0; color:#173f5f; border-bottom:1px solid #eee; padding-bottom:15px; margin-bottom:25px;">Edit: <?php echo htmlspecialchars($product_data['name']); ?></h3>

                <div class="preview-container">
                    <label style="display:block; margin-bottom:10px; font-weight:bold; color:#777;">Current Image</label>
                    <img src="<?php echo $target_dir . htmlspecialchars($product_data['image_path']); ?>" alt="Product Image" class="preview-img">
                </div>

                <form action="" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">

                    <div class="form-group">
                        <label for="name">Product Name:</label>
                        <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($product_data['name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="price">Base Price (₱) / Single Price:</label>
                        <input type="number" step="0.01" name="price" id="price" value="<?php echo htmlspecialchars($product_data['price']); ?>" required>
                        <small style="color:#999;">If variants are set below, this will automatically update to the lowest variant price.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="category">Category:</label>
                        <select name="category" id="category" onchange="toggleCategorySettings()" required>
                            <option value="drink" <?php if ($product_data['category'] == 'drink') echo 'selected'; ?>>Drink</option>
                            <option value="snack" <?php if ($product_data['category'] == 'snack') echo 'selected'; ?>>Snack</option>
                            <option value="food" <?php if ($product_data['category'] == 'food') echo 'selected'; ?>>Food</option>
                            <option value="dessert" <?php if ($product_data['category'] == 'dessert') echo 'selected'; ?>>Dessert</option>
                        </select>
                    </div>

                    <div class="form-group" id="subcategory-group" style="display: none;">
                        <label for="subcategory">Subcategory:</label>
                        <select name="subcategory" id="subcategory">
                            <option value="">-- Select Subcategory --</option>
                            <?php foreach ($drinks_subcategories_list as $subcat): ?>
                                <option value="<?php echo htmlspecialchars($subcat); ?>" <?php if ($product_data['subcategory'] == $subcat) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($subcat); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="product_image">Change Image (Leave blank to keep current):</label>
                        <input type="file" name="product_image" id="product_image" accept=".jpg, .jpeg, .png, .gif">
                    </div>

                    <div id="variant-pricing-container">
                        <h4 style="color:#5D4037; margin-bottom: 15px;">Edit Variant Pricing (Drinks Only)</h4>
                        <p style="color:#777; font-size:0.9rem; margin-bottom:20px;">Leave fields blank to remove that variant option.</p>

                        <div class="tabs">
                            <div class="tab active" onclick="openTab(event, 'Hot')">🔥 Hot Prices</div>
                            <div class="tab" onclick="openTab(event, 'Cold')">❄️ Cold Prices</div>
                        </div>

                        <div id="Hot" class="tab-content active">
                            <?php 
                            $hot_sizes = ['8oz','10oz','12oz','16oz','Edible Cup'];
                            foreach($hot_sizes as $s): 
                                // Check if price exists for this size/temp combo
                                $val = isset($current_variants['Hot'][$s]) ? $current_variants['Hot'][$s] : '';
                            ?>
                            <div class="price-row">
                                <label><?=$s?>:</label> 
                                <input type="number" name="hot_prices[<?=$s?>]" step="0.01" placeholder="N/A" value="<?php echo $val; ?>">
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <div id="Cold" class="tab-content">
                            <?php 
                            $cold_sizes = ['10oz','12oz','16oz','22oz'];
                            foreach($cold_sizes as $s): 
                                 // Check if price exists for this size/temp combo
                                $val = isset($current_variants['Cold'][$s]) ? $current_variants['Cold'][$s] : '';
                            ?>
                            <div class="price-row">
                                <label><?=$s?>:</label> 
                                <input type="number" name="cold_prices[<?=$s?>]" step="0.01" placeholder="N/A" value="<?php echo $val; ?>">
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div style="margin-top:30px;">
                        <input type="submit" value="Update Product" class="btn-update">
                    </div>
                </form>

                <div style="text-align:center; margin-top:20px;">
                    <a href="admin_products.php" style="color:#777; text-decoration:none;">Cancel & Back to List</a>
                </div>
            </div>
            <div style="height:50px;"></div>
        </div>
    </main>
</body>
</html>