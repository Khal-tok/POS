<?php
session_start();
date_default_timezone_set('Asia/Manila'); 
include 'db.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['role'] != 'admin') {
    header("location: login.php");
    exit;
}

$today = date('Y-m-d');

$revenue_query = mysqli_query($conn, "SELECT SUM(total_amount) as total FROM transactions");
$revenue_data = mysqli_fetch_assoc($revenue_query);
$total_revenue = $revenue_data['total'] ?? 0;

$orders_query = mysqli_query($conn, "SELECT COUNT(*) as count FROM transactions");
$orders_data = mysqli_fetch_assoc($orders_query);
$total_orders = $orders_data['count'] ?? 0;

$online_query = mysqli_query($conn, "SELECT COUNT(*) as count FROM online_orders WHERE status = 'Pending'");
$online_data = mysqli_fetch_assoc($online_query);
$pending_online = $online_data['count'] ?? 0;

$chart_labels = [];
$chart_data = [];
for ($i = 6; $i >= 0; $i--) {
    $date_check = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[] = date('M d', strtotime($date_check)); 
    
    $daily_sql = "SELECT SUM(total_amount) as total FROM transactions WHERE DATE(order_time) = '$date_check'";
    $daily_res = mysqli_query($conn, $daily_sql);
    $daily_row = mysqli_fetch_assoc($daily_res);
    $chart_data[] = $daily_row['total'] ?? 0;
}

$cat_labels = [];
$cat_data = [];
$cat_sql = "SELECT p.category, SUM(oi.quantity) as qty_sold
            FROM order_items oi
            JOIN transactions t ON oi.transaction_id = t.id
            JOIN products p ON oi.product_id = p.id
            GROUP BY p.category
            ORDER BY qty_sold DESC LIMIT 5";
$cat_res = mysqli_query($conn, $cat_sql);

if($cat_res && mysqli_num_rows($cat_res) > 0) {
    while($row = mysqli_fetch_assoc($cat_res)){
        $cat_labels[] = ucfirst($row['category']);
        $cat_data[] = $row['qty_sold'];
    }
} else {
    $cat_labels = ['No Data'];
    $cat_data = [0]; 
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Hossana Cafe</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --p-brown: #5D4037; --d-brown: #3E2723; --bg: #f4f7f6; --side-w: 260px; --head-h: 70px;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Poppins', sans-serif; }
        
        body { background: var(--bg); display: flex; min-height: 100vh; font-size: 16px; color: #333; }
        
        .sidebar { width: var(--side-w); background: linear-gradient(180deg, var(--d-brown) 0%, #2d1b18 100%); color: white; position: fixed; height: 100vh; z-index: 100; box-shadow: 4px 0 15px rgba(0,0,0,0.1); }
        .brand-logo { height: var(--head-h); display: flex; align-items: center; padding: 0 25px; font-size: 1.25rem; font-weight: 700; border-bottom: 1px solid rgba(255,255,255,0.1); letter-spacing: 0.5px; }
        
        .nav-links { padding: 15px 0; }
        .nav-item { display: block; padding: 14px 25px; color: rgba(255,255,255,0.7); text-decoration: none; border-left: 4px solid transparent; font-size: 15px; transition: 0.3s; width: 100%; text-align: left; background: none; border: none; cursor: pointer; }
        .nav-item:hover, .nav-item.active { background: rgba(255,255,255,0.08); color: white; border-left-color: #A1887F; }
        
        .submenu-container { display: none; background: rgba(0,0,0,0.15); padding: 5px 0; }
        .submenu-container.show { display: block; }
        .sub-link { padding-left: 50px; font-size: 14px; }

        .main-content { margin-left: var(--side-w); flex: 1; display: flex; flex-direction: column; width: calc(100% - var(--side-w)); }
        .top-header { height: var(--head-h); background: white; display: flex; justify-content: space-between; align-items: center; padding: 0 30px; box-shadow: 0 2px 15px rgba(0,0,0,0.04); position: sticky; top: 0; z-index: 99; }
        .page-title { font-size: 1.4rem; font-weight: 700; color: var(--d-brown); }
        .user-badge { padding: 8px 18px; background: var(--bg); border-radius: 30px; font-weight: 600; font-size: 0.9rem; cursor: pointer; border: 1px solid #eee; }

        .content-scroll { padding: 40px; overflow-y: auto; }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px; margin-bottom: 35px; }
        .stat-card { background: white; padding: 30px; border-radius: 20px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 10px 30px rgba(0,0,0,0.03); border: 1px solid rgba(0,0,0,0.02); }
        .stat-card h3 { font-size: 2.2rem; color: var(--p-brown); margin-bottom: 6px; font-weight: 700; }
        .stat-card p { color: #888; font-size: 1rem; font-weight: 500; }
        
        .charts-grid { display: grid; grid-template-columns: 2fr 1.2fr; gap: 30px; margin-bottom: 35px; }
        .chart-card { background: white; padding: 30px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.03); border: 1px solid rgba(0,0,0,0.02); }
        .chart-header { font-weight: 700; color: var(--p-brown); margin-bottom: 25px; font-size: 1.15rem; border-bottom: 1px solid #f0f0f0; padding-bottom: 15px; }

        .table-container { background: white; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.03); overflow: hidden; border: 1px solid rgba(0,0,0,0.02); }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 20px 25px; background: #fafafa; color: var(--p-brown); font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }
        td { padding: 20px 25px; border-bottom: 1px solid #f8f8f8; font-size: 1rem; color: #444; }
        .btn-small { padding: 10px 20px; border-radius: 30px; font-size: 0.85rem; text-decoration: none; background: var(--p-brown); color: white; font-weight: 600; transition: 0.3s; }

        .dropdown-content { display: none; position: absolute; right: 30px; top: 75px; background: white; box-shadow: 0 10px 40px rgba(0,0,0,0.1); border-radius: 12px; min-width: 180px; z-index: 200; border: 1px solid #eee; overflow: hidden; }
        .dropdown-content.show { display: block; }
        .dropdown-content a { display: block; padding: 12px 20px; font-size: 0.95rem; text-decoration: none; color: #333; transition: 0.2s; }
    </style>
</head>
<body>
    <nav class="sidebar">
        <div class="brand-logo">HOSSANA CAFE</div>
        <div class="nav-links">
            <a href="admin_dashboard.php" class="nav-item active">📊 Dashboard</a>
            <div class="nav-item" onclick="toggleSidebarMenu('productMenu')" style="cursor:pointer;">☕ Products & Menu <span style="float:right; font-size:10px;">▼</span></div>
            <div id="productMenu" class="submenu-container">
                <a href="add_product.php" class="nav-item sub-link">➕ Add Product</a>
                <a href="admin_products.php" class="nav-item sub-link">📋 Product List</a>
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

    <main class="main-content">
        <header class="top-header">
            <div class="page-title">Dashboard Overview</div>
            <div class="user-badge" onclick="toggleProfileMenu()">Admin Account ▼</div>
            <div id="profileDropdown" class="dropdown-content">
                <a href="admin_profile.php">👤 Profile</a>
                <a href="logout.php" style="color:#c62828">🚪 Logout</a>
            </div>
        </header>

        <div class="content-scroll">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>₱<?=number_format($total_revenue, 2)?></h3>
                        <p>Total Revenue</p>
                    </div>
                    <div style="font-size: 2.5rem;">💰</div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3><?=$total_orders?></h3>
                        <p>Transactions</p>
                    </div>
                    <div style="font-size: 2.5rem;">🧾</div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3><?=$pending_online?></h3>
                        <p>Pending Online</p>
                    </div>
                    <div style="font-size: 2.5rem;">🔔</div>
                </div>
            </div>

            <div class="charts-grid">
                <div class="chart-card">
                    <div class="chart-header">Sales Trend (Last 7 Days)</div>
                    <div style="height: 350px;"><canvas id="salesTrendChart"></canvas></div>
                </div>
                <div class="chart-card">
                    <div class="chart-header">Top Categories</div>
                    <div style="height: 350px;"><canvas id="categoryPieChart"></canvas></div>
                </div>
            </div>

            <div class="table-container">
                <div style="padding: 20px 25px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
                    <h4 style="font-size: 1.15rem; color: var(--p-brown); font-weight: 700;">Recent Transactions</h4>
                    <a href="admin_sales_report.php" style="font-size: 0.95rem; color: var(--p-brown); font-weight: 700; text-decoration: none;">View All</a>
                </div>
                <table>
                    <thead>
                        <tr><th>ID</th><th>Date</th><th>Barista</th><th>Amount</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        <?php 
                        $recent_sql = "SELECT t.*, u.username FROM transactions t JOIN users u ON t.user_id = u.id ORDER BY t.id DESC LIMIT 5";
                        $recent = mysqli_query($conn, $recent_sql);
                        while($row = mysqli_fetch_assoc($recent)): ?>
                        <tr>
                            <td style="font-weight: 700; color: #777;">#<?=$row['id']?></td>
                            <td><?=date('M d, h:i A', strtotime($row['order_time']))?></td>
                            <td style="font-weight:600; color: var(--d-brown);"><?=$row['username']?></td>
                            <td style="font-weight:700; color: var(--p-brown);">₱<?=number_format($row['total_amount'], 2)?></td>
                            <td><a href="receipt_module_admin.php?type=pos&id=<?=$row['id']?>" class="btn-small" target="_blank">Receipt</a></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        function toggleSidebarMenu(menuId) { document.getElementById(menuId).classList.toggle("show"); }
        function toggleProfileMenu() { document.getElementById("profileDropdown").classList.toggle("show"); }

        new Chart(document.getElementById('salesTrendChart'), {
            type: 'line',
            data: {
                labels: <?=json_encode($chart_labels)?>,
                datasets: [{
                    label: 'Daily Sales',
                    data: <?=json_encode($chart_data)?>,
                    borderColor: '#5D4037',
                    backgroundColor: 'rgba(93, 64, 55, 0.08)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 5,
                    pointBackgroundColor: '#5D4037'
                }]
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: false,
                plugins: { legend: { display: false } }
            }
        });

        new Chart(document.getElementById('categoryPieChart'), {
            type: 'doughnut',
            data: {
                labels: <?=json_encode($cat_labels)?>,
                datasets: [{
                    data: <?=json_encode($cat_data)?>,
                    backgroundColor: ['#5D4037', '#795548', '#8D6E63', '#A1887F', '#D7CCC8'],
                    borderWidth: 0
                }]
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: false, 
                plugins: { legend: { position: 'bottom', labels: { padding: 25, font: { size: 13, weight: '600' } } } },
                cutout: '70%'
            }
        });
    </script>
</body>
</html>