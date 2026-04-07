<?php
session_start();
include 'db.php';

// Check Auth
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] != 'admin') {
    header("location: login.php");
    exit;
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    mysqli_query($conn, "DELETE FROM products WHERE id=$id");
    mysqli_query($conn, "DELETE FROM product_variants WHERE product_id=$id");
    header("location: admin_products.php?msg=deleted");
    exit;
}

// Handle Search
$search = "";
// LOGIC CHANGE: Order by Subcategory first, then Category, then Name
$sql = "SELECT * FROM products ORDER BY 
        CASE WHEN subcategory IS NULL OR subcategory = '' THEN 1 ELSE 0 END, 
        subcategory ASC, category ASC, name ASC"; 

if(isset($_GET['search']) && !empty($_GET['search'])) {
    $search = mysqli_real_escape_string($conn, $_GET['search']);
    $sql = "SELECT * FROM products WHERE name LIKE '%$search%' OR category LIKE '%$search%' OR subcategory LIKE '%$search%' 
            ORDER BY 
            CASE WHEN subcategory IS NULL OR subcategory = '' THEN 1 ELSE 0 END, 
            subcategory ASC, category ASC, name ASC";
}

$result = mysqli_query($conn, $sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <title>Product List | Hossana Cafe</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* --- MOTHER CODE VARIABLES (Matching Add Product Tab) --- */
        :root {
            --p-brown: #5D4037; --d-brown: #3E2723; --bg: #f4f7f6; 
            --side-w: 260px; --head-h: 70px;
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Poppins', sans-serif; }
        
        body { background: var(--bg); display: flex; min-height: 100vh; font-size: 16px; color: #333; }
        
        /* --- SIDEBAR STYLES --- */
        .sidebar { width: var(--side-w); background: linear-gradient(180deg, var(--d-brown) 0%, #2d1b18 100%); color: white; position: fixed; height: 100vh; z-index: 1000; box-shadow: 4px 0 15px rgba(0,0,0,0.1); transition: transform 0.3s ease; }
        .brand-logo { height: var(--head-h); display: flex; align-items: center; padding: 0 25px; font-size: 1.25rem; font-weight: 700; border-bottom: 1px solid rgba(255,255,255,0.1); letter-spacing: 0.5px; }
        
        .nav-links { padding: 15px 0; }
        .nav-item { display: block; padding: 14px 25px; color: rgba(255,255,255,0.7); text-decoration: none; border-left: 4px solid transparent; font-size: 15px; transition: 0.3s; width: 100%; text-align: left; background: none; border: none; cursor: pointer; }
        .nav-item:hover, .nav-item.active { background: rgba(255,255,255,0.08); color: white; border-left-color: #A1887F; }
        
        .submenu-container { display: none; background: rgba(0,0,0,0.15); padding: 5px 0; }
        .submenu-container.show { display: block; }
        .sub-link { padding-left: 55px; font-size: 14px; }

        /* --- MAIN CONTENT LAYOUT --- */
        .main-content { margin-left: var(--side-w); flex: 1; display: flex; flex-direction: column; width: calc(100% - var(--side-w)); transition: margin 0.3s ease, width 0.3s ease; }
        
        .top-header { height: var(--head-h); background: white; display: flex; justify-content: space-between; align-items: center; padding: 0 30px; box-shadow: 0 2px 15px rgba(0,0,0,0.04); position: sticky; top: 0; z-index: 99; }
        .page-title { font-size: 1.4rem; font-weight: 700; color: var(--d-brown); }
        .user-badge { padding: 8px 18px; background: var(--bg); border-radius: 30px; font-weight: 600; font-size: 0.9rem; cursor: pointer; border: 1px solid #eee; }
        .content-scroll { padding: 40px; overflow-y: auto; }

        /* Dropdown Profile */
        .dropdown-content { display: none; position: absolute; right: 30px; top: 75px; background: white; box-shadow: 0 10px 40px rgba(0,0,0,0.1); border-radius: 12px; min-width: 180px; z-index: 200; border: 1px solid #eee; overflow: hidden; }
        .dropdown-content.show { display: block; }
        .dropdown-content a { display: block; padding: 12px 20px; font-size: 0.95rem; text-decoration: none; color: #333; transition: 0.2s; }

        /* Hamburger Menu (Hidden on Desktop) */
        .menu-toggle { display: none; font-size: 1.5rem; color: var(--p-brown); cursor: pointer; margin-right: 15px; }

        /* --- YOUR SPECIFIC GRID CSS (RETAINED & ADAPTED) --- */
        
        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            gap: 15px;
            flex-wrap: wrap; /* Allows wrapping on mobile */
        }

        .search-box { position: relative; flex: 1; max-width: 400px; }
        .search-box input {
            width: 100%; padding: 12px 15px 12px 40px; border: 1px solid #e0e0e0;
            border-radius: 30px; font-size: 0.95rem; box-shadow: 0 2px 5px rgba(0,0,0,0.02);
            transition: all 0.3s ease;
        }
        .search-box input:focus { border-color: #5D4037; outline: none; box-shadow: 0 4px 10px rgba(93, 64, 55, 0.1); }
        .search-icon { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #999; border:none; background:none; cursor: pointer; }

        .btn-green {
            background: #5D4037; color: white; padding: 12px 25px; border-radius: 30px; text-decoration: none; font-weight: 600;
            box-shadow: 0 4px 10px rgba(93, 64, 55, 0.2); white-space: nowrap; transition: 0.3s;
        }
        .btn-green:hover { background: #3E2723; transform: translateY(-2px); }

        /* SUBCATEGORY HEADERS */
        .group-header {
            color: #5D4037; font-size: 1.4rem; font-weight: 800; margin-top: 40px; margin-bottom: 20px;
            display: flex; align-items: center; gap: 15px; border-bottom: 2px solid #eee; padding-bottom: 10px;
        }
        .group-header .main-badge { 
            background: #EFEBE9; color: #5D4037; padding: 4px 10px; border-radius: 6px; 
            font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;
        }

        /* GRID CONTAINER - RESPONSIVE */
        .product-grid {
            display: grid;
            /* Auto-fill creates as many columns as fit. Min 220px keeps cards from getting too small. */
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 25px;
        }

        /* PRODUCT CARD */
        .product-card {
            background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease; position: relative;
            border: 1px solid rgba(0,0,0,0.02); display: flex; flex-direction: column;
        }
        .product-card:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }

        .card-img-container { width: 100%; height: 160px; background: #f4f4f4; position: relative; }
        .card-img { width: 100%; height: 100%; object-fit: cover; }
        
        .stock-badge {
            position: absolute; top: 10px; right: 10px; padding: 4px 10px; border-radius: 12px;
            font-size: 0.7rem; font-weight: bold; color: white; box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        .bg-ok { background: #2E7D32; }
        .bg-low { background: #C62828; }

        .card-content { padding: 15px; flex-grow: 1; }
        .prod-sub { font-size: 0.75rem; color: #888; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 5px; }
        .prod-title { font-size: 1.1rem; font-weight: 700; color: #333; margin: 0 0 5px 0; line-height: 1.3; }
        .prod-price { font-weight: 700; color: #5D4037; font-size: 1rem; }

        .card-actions { padding: 12px 15px; background: #fcfcfc; border-top: 1px solid #f0f0f0; display: flex; gap: 10px; }
        .btn-card {
            flex: 1; padding: 8px; border-radius: 8px; text-align: center; font-size: 0.85rem;
            font-weight: 600; text-decoration: none; transition: all 0.2s;
        }
        .btn-edit { background: #e3f2fd; color: #1565c0; }
        .btn-edit:hover { background: #bbdefb; }
        .btn-del { background: #ffebee; color: #c62828; }
        .btn-del:hover { background: #ffcdd2; }

        /* ========================================= */
        /* === RESPONSIVE MEDIA QUERIES === */
        /* ========================================= */
        @media (max-width: 1024px) {
            .product-grid { gap: 20px; }
        }

        @media (max-width: 768px) {
            /* Hide Sidebar by default on mobile */
            .sidebar { transform: translateX(-100%); width: 260px; }
            .sidebar.active { transform: translateX(0); }
            
            /* Expand Main Content */
            .main-content { margin-left: 0; width: 100%; }
            
            /* Show Hamburger */
            .menu-toggle { display: block; }
            
            /* Adjust padding */
            .content-scroll { padding: 20px; }
            .top-header { padding: 0 20px; }
            
            /* Stack Toolbar items */
            .toolbar { flex-direction: column; align-items: stretch; gap: 15px; }
            .search-box { max-width: 100%; }
            .btn-green { text-align: center; }
            
            /* Adjust Grid for Mobile */
            .product-grid {
                /* On small screens, cards can go down to 160px min width (2 columns usually) */
                grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
                gap: 15px;
            }
            .card-img-container { height: 140px; }
        }
    </style>
</head>
<body>

    <nav class="sidebar" id="sidebar">
        <div class="brand-logo">
            HOSSANA CAFE
            <span style="margin-left:auto; cursor:pointer; font-size:1.2rem;" onclick="toggleSidebar()" class="close-sidebar-btn">✕</span>
        </div>
        <div class="nav-links">
            <a href="admin_dashboard.php" class="nav-item">📊 Dashboard</a>
            
            <button class="nav-item active" onclick="toggleSidebarMenu('productMenu')">☕ Products & Menu <span style="float:right; font-size:10px;">▼</span></button>
            <div id="productMenu" class="submenu-container show">
                <a href="add_product.php" class="nav-item sub-link">➕ Add Product</a>
                <a href="admin_products.php" class="nav-item sub-link active">📋 Product List</a>
                <a href="manage_subcategories.php" class="nav-item sub-link">📂 Manage Subcategories</a>
            </div>
            
            <a href="admin_sales_report.php" class="nav-item">💰 Sales Reports</a>
            <a href="inventory.php" class="nav-item">📦 Inventory</a>
            
            <div class="nav-item" onclick="toggleSidebarMenu('userManage')" style="cursor:pointer;">👥 User Management <span style="float:right; font-size:10px;">▼</span></div>
            <div id="userManage" class="submenu-container">
                <a href="manage_admins.php" class="nav-item sub-link">User Overview</a>
                <a href="add_barista.php" class="nav-item sub-link">Baristas</a>
                <a href="add_rider.php" class="nav-item sub-link">Riders</a>
            </div>

        </div>
    </nav>

    <main class="main-content" id="mainContent">
        <header class="top-header">
            <div style="display:flex; align-items:center;">
                <div class="menu-toggle" onclick="toggleSidebar()">☰</div>
                <div class="page-title">Product Catalog</div>
            </div>
            
            <div class="user-badge" onclick="toggleProfileMenu()">Admin Account ▼</div>
            <div id="profileDropdown" class="dropdown-content">
                <a href="admin_profile.php">👤 Profile</a>
                <a href="logout.php" style="color:#c62828">🚪 Logout</a>
            </div>
        </header>

        <div class="content-scroll">
            
            <?php if(isset($_GET['success'])): ?>
                <div style="background:#d4edda; color:#155724; padding:15px; border-radius:10px; margin-bottom:20px; border-left: 5px solid #28a745;">
                    <b>Success!</b> New product added.
                </div>
            <?php endif; ?>
            <?php if(isset($_GET['msg']) && $_GET['msg']=='deleted'): ?>
                <div style="background:#f8d7da; color:#721c24; padding:15px; border-radius:10px; margin-bottom:20px; border-left: 5px solid #dc3545;">
                    Product deleted successfully.
                </div>
            <?php endif; ?>

            <div class="toolbar">
                <form class="search-box" method="GET" action="admin_products.php">
                    <button type="submit" class="search-icon">🔍</button>
                    <input type="text" name="search" placeholder="Search product..." value="<?php echo htmlspecialchars($search); ?>">
                </form>
                
                <a href="add_product.php" class="btn-green">
                    + Add New Product
                </a>
            </div>

            <?php 
            if(mysqli_num_rows($result) > 0):
                $current_group = null;
                $is_grid_open = false;

                while($row = mysqli_fetch_assoc($result)): 
                    //  - Conceptual tag for user understanding if needed
                    $img = !empty($row['image_path']) ? $row['image_path'] : "placeholder.png"; 
                    // Note: Ensure your image path logic matches your upload folder structure
                    
                    $stock_badge_class = ($row['stock'] < 20) ? 'bg-low' : 'bg-ok';
                    $stock_label = ($row['stock'] < 20) ? 'Low: '.$row['stock'] : 'Qty: '.$row['stock'];
                    
                    // Grouping Logic
                    $display_group = !empty($row['subcategory']) ? $row['subcategory'] : $row['category'];
                    
                    if ($display_group !== $current_group) {
                        if ($is_grid_open) { echo "</div>"; } // Close previous grid
                        
                        $current_group = $display_group;
                        $is_grid_open = true;

                        echo "<div class='group-header'>";
                        echo    ucfirst($current_group);
                        echo    "<span class='main-badge'>" . ucfirst($row['category']) . "</span>";
                        echo "</div>";
                        echo "<div class='product-grid'>"; // Start new grid
                    }
            ?>
                    <div class="product-card">
                        <div class="card-img-container">
                            <img src="<?php echo $img; ?>" class="card-img" alt="Product">
                            <div class="stock-badge <?php echo $stock_badge_class; ?>"><?php echo $stock_label; ?></div>
                        </div>
                        
                        <div class="card-content">
                            <h3 class="prod-title"><?php echo htmlspecialchars($row['name']); ?></h3>
                            <div class="prod-price">
                                <?php if($row['has_sizes']): ?>
                                    Variable Sizes
                                <?php else: ?>
                                    ₱<?php echo number_format($row['price'], 2); ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="card-actions">
                            <a href="edit_product.php?id=<?php echo $row['id']; ?>" class="btn-card btn-edit">Edit</a>
                            <a href="admin_products.php?delete=<?php echo $row['id']; ?>" class="btn-card btn-del" onclick="return confirm('Delete this product?');">Delete</a>
                        </div>
                    </div>

            <?php 
                endwhile; 
                if ($is_grid_open) { echo "</div>"; } 
            else?>
                <div style="text-align:center; padding:50px; background:white; border-radius:12px; color:#888;">
                    <h3>No products found.</h3>
                    <p>Try adjusting your search or add a new product.</p>
                </div>
            <?php endif; ?>

            <div style="height:50px;"></div>
        </div>
    </main>

    <script>
        function toggleSidebarMenu(menuId) { 
            document.getElementById(menuId).classList.toggle("show"); 
        }
        function toggleProfileMenu() { 
            document.getElementById("profileDropdown").classList.toggle("show"); 
        }
        
        // Responsive Sidebar Toggle
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            sidebar.classList.toggle('active');
        }
    </script>
</body>
</html>