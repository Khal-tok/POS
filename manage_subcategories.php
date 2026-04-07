<?php
session_start();
include 'db.php';

// Authorization Check
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] != 'admin') {
    header("location: login.php");
    exit;
}

$message = "";
$error = "";
$edit_id = 0;
$edit_name = "";
$edit_sort_order = 0; 

// --- CRUD Logic ---

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';
    
    // --- CREATE/UPDATE (SAVE) ---
    if ($action == 'add' || $action == 'update') {
        $name = trim($_POST['name']);
        $sort_order = intval($_POST['sort_order'] ?? 0);
        $id = intval($_POST['id'] ?? 0);

        if (empty($name)) {
            $error = "Subcategory name cannot be empty.";
        } else {
            if ($action == 'add') {
                $stmt = mysqli_prepare($conn, "INSERT INTO drinks_subcategories (name, sort_order) VALUES (?, ?)");
                mysqli_stmt_bind_param($stmt, "si", $name, $sort_order);
                if (mysqli_stmt_execute($stmt)) {
                    $message = "Subcategory '$name' added successfully.";
                } else {
                    if (mysqli_errno($conn) == 1062) {
                        $error = "Error: Subcategory '$name' already exists.";
                    } else {
                        $error = "Error adding subcategory: " . mysqli_error($conn);
                    }
                }
            } elseif ($action == 'update' && $id > 0) {
                $stmt = mysqli_prepare($conn, "UPDATE drinks_subcategories SET name=?, sort_order=? WHERE id=?");
                mysqli_stmt_bind_param($stmt, "sii", $name, $sort_order, $id);
                if (mysqli_stmt_execute($stmt)) {
                    $message = "Subcategory updated successfully.";
                    // Reset edit state
                    $edit_id = 0; $edit_name = ""; $edit_sort_order = 0;
                } else {
                    $error = "Error updating subcategory: " . mysqli_error($conn);
                }
            }
        }
    }
    // --- DELETE ---
    elseif ($action == 'delete') {
        $id = intval($_POST['id']);
        // Set products with this subcategory to NULL first (Safe Delete)
        $sub_name_query = mysqli_query($conn, "SELECT name FROM drinks_subcategories WHERE id=$id");
        $sub_row = mysqli_fetch_assoc($sub_name_query);
        $sub_name = $sub_row['name'] ?? '';

        if ($sub_name) {
            $update_prods = mysqli_prepare($conn, "UPDATE products SET subcategory = NULL WHERE subcategory = ?");
            mysqli_stmt_bind_param($update_prods, "s", $sub_name);
            mysqli_stmt_execute($update_prods);
        }

        // Now delete the category
        $stmt = mysqli_prepare($conn, "DELETE FROM drinks_subcategories WHERE id=?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        if (mysqli_stmt_execute($stmt)) {
            $message = "Subcategory deleted. Related products are now 'Unassigned'.";
        } else {
            $error = "Error deleting subcategory.";
        }
    }
}

// --- CHECK FOR EDIT MODE ---
if (isset($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    $stmt = mysqli_prepare($conn, "SELECT * FROM drinks_subcategories WHERE id=?");
    mysqli_stmt_bind_param($stmt, "i", $edit_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($res)) {
        $edit_name = $row['name'];
        $edit_sort_order = $row['sort_order'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <title>Manage Subcategories | Hossana Cafe</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* --- MOTHER CODE VARIABLES (Synced with Add Product) --- */
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

        /* Hamburger Menu */
        .menu-toggle { display: none; font-size: 1.5rem; color: var(--p-brown); cursor: pointer; margin-right: 15px; }

        /* --- PAGE SPECIFIC: SPLIT LAYOUT --- */
        .split-container {
            display: grid;
            grid-template-columns: 1fr 1.5fr; /* Form takes less space than table */
            gap: 30px;
            align-items: start;
        }

        /* --- CARD STYLE (Matches Add Product) --- */
        .card-panel {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.03);
            border: 1px solid rgba(0,0,0,0.02);
            padding: 30px;
        }
        .card-header {
            font-size: 1.25rem; font-weight: 700; color: var(--p-brown);
            margin-bottom: 25px; border-bottom: 2px solid #f0f0f0; padding-bottom: 15px;
        }

        /* --- FORM STYLES --- */
        .form-group { margin-bottom: 25px; }
        .form-group label { display: block; font-weight: 600; color: var(--d-brown); margin-bottom: 10px; font-size: 0.95rem; }
        .form-group input {
            width: 100%; padding: 15px 20px; border-radius: 12px; border: 1px solid #ddd;
            font-size: 1rem; background: #fafafa; transition: 0.3s;
        }
        .form-group input:focus { border-color: var(--p-brown); background: white; outline: none; box-shadow: 0 4px 12px rgba(93, 64, 55, 0.1); }
        
        .btn-submit {
            width: 100%; background: var(--p-brown); color: white; border: none; padding: 16px;
            border-radius: 12px; font-weight: 600; font-size: 1rem; cursor: pointer; transition: 0.3s;
            box-shadow: 0 4px 15px rgba(93, 64, 55, 0.2);
        }
        .btn-submit:hover { background: var(--d-brown); transform: translateY(-2px); }
        .btn-cancel {
            display: inline-block; width: 100%; text-align: center; margin-top: 15px;
            color: #777; text-decoration: none; font-size: 0.9rem; font-weight: 500;
        }

        /* --- TABLE STYLES --- */
        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 20px; background: #fafafa; color: var(--p-brown); font-size: 0.9rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #eee; }
        td { padding: 20px; border-bottom: 1px solid #f8f8f8; font-size: 1rem; color: #444; }
        
        .badge-sort {
            background: #EFEBE9; color: var(--d-brown); padding: 5px 12px;
            border-radius: 8px; font-weight: 600; font-size: 0.85rem;
        }

        .btn-small { padding: 8px 16px; border-radius: 8px; font-size: 0.85rem; text-decoration: none; font-weight: 600; transition: 0.2s; border: none; cursor: pointer; }
        .btn-primary { background: #e3f2fd; color: #1565c0; }
        .btn-primary:hover { background: #bbdefb; }
        .btn-red { background: #ffebee; color: #c62828; }
        .btn-red:hover { background: #ffcdd2; }

        /* --- ALERTS --- */
        .alert { padding: 15px 20px; border-radius: 12px; margin-bottom: 25px; font-weight: 500; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: #E8F5E9; color: #2E7D32; border-left: 5px solid #2E7D32; }
        .alert-error { background: #FFEBEE; color: #C62828; border-left: 5px solid #C62828; }

        /* --- MEDIA QUERIES --- */
        @media (max-width: 1024px) {
            .split-container { grid-template-columns: 1fr; } /* Stack vertically on tablet */
        }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); width: 260px; }
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; width: 100%; }
            .menu-toggle { display: block; }
            .content-scroll { padding: 20px; }
            .top-header { padding: 0 20px; }
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
                <a href="admin_products.php" class="nav-item sub-link">📋 Product List</a>
                <a href="manage_subcategories.php" class="nav-item sub-link active">📂 Manage Subcategories</a>
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

    <main class="main-content">
        <header class="top-header">
            <div style="display:flex; align-items:center;">
                <div class="menu-toggle" onclick="toggleSidebar()">☰</div>
                <div class="page-title">Manage Subcategories</div>
            </div>
            
            <div class="user-badge" onclick="toggleProfileMenu()">Admin Account ▼</div>
            <div id="profileDropdown" class="dropdown-content">
                <a href="admin_profile.php">👤 Profile</a>
                <a href="logout.php" style="color:#c62828">🚪 Logout</a>
            </div>
        </header>

        <div class="content-scroll">
            
            <?php if (!empty($message)): ?>
                <div class="alert alert-success">✅ <?php echo $message; ?></div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="alert alert-error">⚠️ <?php echo $error; ?></div>
            <?php endif; ?>

            <div class="split-container">
                
                <div class="card-panel">
                    <div class="card-header">
                        <?php echo ($edit_id > 0) ? 'Edit Subcategory' : 'Add New Category'; ?>
                    </div>
                    
                    <form action="manage_subcategories.php" method="post">
                        <input type="hidden" name="action" value="<?php echo ($edit_id > 0) ? 'update' : 'add'; ?>">
                        <input type="hidden" name="id" value="<?php echo $edit_id; ?>">
                        
                        <div class="form-group">
                            <label>Subcategory Name</label>
                            <input type="text" name="name" value="<?php echo htmlspecialchars($edit_name); ?>" placeholder="e.g. Frappe, Iced Coffee" required>
                        </div>

                        <div class="form-group">
                            <label>Sort Order (Optional)</label>
                            <input type="number" name="sort_order" value="<?php echo $edit_sort_order; ?>" placeholder="1, 2, 3...">
                            <small style="color:#999; display:block; margin-top:5px;">Determines the display order in the menu.</small>
                        </div>

                        <button type="submit" class="btn-submit">
                            <?php echo ($edit_id > 0) ? 'Update Category' : 'Save Category'; ?>
                        </button>

                        <?php if($edit_id > 0): ?>
                            <a href="manage_subcategories.php" class="btn-cancel">Cancel Edit</a>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="card-panel">
                    <div class="card-header">Existing Subcategories</div>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th width="10%">Order</th>
                                    <th>Name</th>
                                    <th width="30%" style="text-align:right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $sql = "SELECT * FROM drinks_subcategories ORDER BY sort_order ASC, name ASC";
                                $res = mysqli_query($conn, $sql);
                                if (mysqli_num_rows($res) > 0):
                                    while ($row = mysqli_fetch_assoc($res)): 
                                ?>
                                <tr>
                                    <td><span class="badge-sort">#<?php echo $row['sort_order']; ?></span></td>
                                    <td style="font-weight:600; color:var(--d-brown);"><?php echo htmlspecialchars($row['name']); ?></td>
                                    <td style="text-align:right;">
                                        <a href="manage_subcategories.php?edit_id=<?php echo $row['id']; ?>" class="btn-small btn-primary">Edit</a>
                                        
                                        <form action="manage_subcategories.php" method="post" style="display:inline-block; margin-left:5px;" onsubmit="return confirm('WARNING: Products in this category will become UNASSIGNED. Continue?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                            <button type="submit" class="btn-small btn-red">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="3" style="text-align:center; padding:40px; color:#999;">No subcategories found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div> <div style="height:50px;"></div>
        </div>
    </main>

    <script>
        function toggleSidebarMenu(menuId) { document.getElementById(menuId).classList.toggle("show"); }
        function toggleProfileMenu() { document.getElementById("profileDropdown").classList.toggle("show"); }
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('active');
        }
    </script>
</body>
</html>