<?php
session_start();
include 'db.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['role'] != 'admin') {
    header("location: login.php");
    exit;
}

$success = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_ingredient'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $unit = mysqli_real_escape_string($conn, $_POST['unit']);
    $stock = floatval($_POST['stock_quantity']);
    $min_level = floatval($_POST['min_stock_level']);

    $sql = "INSERT INTO ingredients (name, unit, stock_quantity, min_stock_level) VALUES (?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ssdd", $name, $unit, $stock, $min_level);

    if (mysqli_stmt_execute($stmt)) {
        $success = "New supply registered successfully!";
    } else {
        $error = "Error: " . mysqli_error($conn);
    }
}

$ingredients = mysqli_query($conn, "SELECT * FROM ingredients ORDER BY name ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Inventory | Hossana Cafe</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-brown: #5D4037; --dark-brown: #3E2723; --light-bg: #f8f9fa;
            --white: #ffffff; --sidebar-width: 260px; --header-height: 70px;
            --success: #2E7D32; --danger: #C62828;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Poppins', sans-serif; background-color: var(--light-bg); display: flex; min-height: 100vh; }

        .sidebar { width: var(--sidebar-width); background: linear-gradient(180deg, var(--dark-brown) 0%, #2d1b18 100%); color: white; position: fixed; height: 100vh; z-index: 100; }
        .brand-logo { height: var(--header-height); display: flex; align-items: center; padding: 0 25px; font-size: 1.3rem; font-weight: 700; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .nav-links { flex: 1; padding: 20px 0; }
        .nav-item { display: block; padding: 15px 25px; color: rgba(255,255,255,0.7); text-decoration: none; font-size: 0.95rem; border-left: 4px solid transparent; cursor: pointer; }
        .nav-item:hover, .nav-item.active { background: rgba(255,255,255,0.05); color: white; border-left-color: #A1887F; }
        .submenu-container { display: none; background: rgba(0,0,0,0.2); }
        .sub-link { padding-left: 45px; font-size: 0.85rem; }

        .main-content { margin-left: var(--sidebar-width); flex: 1; display: flex; flex-direction: column; }
        .top-header { height: var(--header-height); background: white; padding: 0 30px; display: flex; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .content-scroll { padding: 30px; }
        
        .card { background: white; padding: 25px; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.03); margin-bottom: 30px; border: 1px solid #eee; }
        .section-title { font-size: 1.1rem; font-weight: 700; color: var(--primary-brown); margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; }
        .input-box label { display: block; font-size: 0.85rem; font-weight: 600; color: #666; margin-bottom: 8px; }
        input, select { width: 100%; padding: 12px; border: 1.5px solid #eee; border-radius: 10px; font-size: 0.95rem; transition: 0.3s; }
        input:focus { border-color: var(--primary-brown); outline: none; background: #fff; }
        
        .btn-primary { background: var(--primary-brown); color: white; border: none; padding: 12px 30px; border-radius: 10px; font-weight: 700; cursor: pointer; transition: 0.3s; }
        .btn-primary:hover { background: var(--dark-brown); transform: translateY(-2px); }

        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { text-align: left; padding: 15px; background: #fafafa; color: #888; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; }
        td { padding: 18px 15px; border-bottom: 1px solid #f8f8f8; font-size: 0.95rem; }
        
        .stock-tag { padding: 6px 12px; border-radius: 8px; font-size: 0.8rem; font-weight: 700; }
        .tag-ok { background: #E8F5E9; color: var(--success); }
        .tag-low { background: #FFEBEE; color: var(--danger); border: 1px solid #FFCDD2; }
    </style>
    <script>
        function toggleSidebarMenu(id) {
            const menu = document.getElementById(id);
            menu.style.display = (menu.style.display === "block") ? "none" : "block";
        }
    </script>
</head>
<body>

    <nav class="sidebar">
        <div class="brand-logo">HOSSANA CAFE</div>
        <div class="nav-links">
            <a href="admin_dashboard.php" class="nav-item">📊 Dashboard</a>
            <div class="nav-item" onclick="toggleSidebarMenu('productMenu')">☕ Products & Menu ▼</div>
            <div id="productMenu" class="submenu-container">
                <a href="add_product.php" class="nav-item sub-link">➕ Add Product</a>
                <a href="admin_products.php" class="nav-item sub-link">📋 Product List</a>
            </div>
            <a href="admin_sales_report.php" class="nav-item">💰 Sales Reports</a>
            <a href="inventory.php" class="nav-item active">📦 Inventory</a>
        </div>
    </nav>

    <main class="main-content">
        <header class="top-header"><h2 style="color:var(--primary-brown);">Inventory & Raw Materials</h2></header>

        <div class="content-scroll">
            <div class="card">
                <div class="section-title">✨ Register New Ingredient</div>
                <form action="" method="post">
                    <div class="form-grid">
                        <div class="input-box">
                            <label>Ingredient Name</label>
                            <input type="text" name="name" placeholder="e.g. Arabica Beans" required>
                        </div>
                        <div class="input-box">
                            <label>Measurement Unit</label>
                            <select name="unit">
                                <option value="grams">Grams (g)</option>
                                <option value="ml">Milliliters (ml)</option>
                                <option value="pcs">Pieces (pcs)</option>
                            </select>
                        </div>
                        <div class="input-box">
                            <label>Starting Stock</label>
                            <input type="number" name="stock_quantity" step="0.01" required>
                        </div>
                        <div class="input-box">
                            <label>Critical Level (Alert)</label>
                            <input type="number" name="min_stock_level" step="0.01" value="10">
                        </div>
                    </div>
                    <button type="submit" name="add_ingredient" class="btn-primary" style="margin-top:20px;">Add to Stock</button>
                </form>
            </div>

            <div class="card">
                <div class="section-title">📊 Current Stock Levels</div>
                <table>
                    <thead>
                        <tr>
                            <th>Ingredient</th>
                            <th>Stock Level</th>
                            <th>Unit</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = mysqli_fetch_assoc($ingredients)): 
                            $is_low = ($row['stock_quantity'] <= $row['min_stock_level']);
                        ?>
                        <tr>
                            <td style="font-weight:600;"><?php echo htmlspecialchars($row['name']); ?></td>
                            <td style="font-weight:700; font-size:1.1rem;"><?php echo number_format($row['stock_quantity'], 2); ?></td>
                            <td style="color:#888;"><?php echo $row['unit']; ?></td>
                            <td>
                                <span class="stock-tag <?php echo $is_low ? 'tag-low' : 'tag-ok'; ?>">
                                    <?php echo $is_low ? '⚠️ REPLENISH' : '✅ SUFFICIENT'; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</body>
</html>