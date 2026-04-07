<?php
session_start();
include 'db.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['role'] != 'admin') {
    header("location: login.php");
    exit;
}
                 
date_default_timezone_set('Asia/Manila');

$monthly_sales_data = [];
for ($i = 0; $i < 6; $i++) {
    $month_ts = strtotime("-$i months");
    $month_label = date('M Y', $month_ts);
    $year = date('Y', $month_ts);
    $month = date('m', $month_ts);
    
    $sql_pos = "SELECT IFNULL(SUM(total_amount), 0) FROM transactions 
                WHERE YEAR(transaction_date) = ? AND MONTH(transaction_date) = ?";
    $stmt_pos = mysqli_prepare($conn, $sql_pos);
    mysqli_stmt_bind_param($stmt_pos, "ss", $year, $month);
    mysqli_stmt_execute($stmt_pos);
    $pos_sale = mysqli_fetch_row(mysqli_stmt_get_result($stmt_pos))[0];
    
    $sql_online = "SELECT IFNULL(SUM(total_amount), 0) FROM online_orders 
               WHERE YEAR(order_date) = ? AND MONTH(order_date) = ? 
               AND (status = 'Completed' OR status = 'Delivered' OR status = 'COMPLETED' OR status = 'DELIVERED')";
    $stmt_online = mysqli_prepare($conn, $sql_online);
    mysqli_stmt_bind_param($stmt_online, "ss", $year, $month);
    mysqli_stmt_execute($stmt_online);
    $online_sale = mysqli_fetch_row(mysqli_stmt_get_result($stmt_online))[0];
    
    $monthly_sales_data[$month_label] = $pos_sale + $online_sale;
}
$monthly_sales_data = array_reverse($monthly_sales_data, true); 

$sql_best = "SELECT p.name, SUM(COALESCE(oi.quantity, 0) + COALESCE(ooi.quantity, 0)) AS total_sold
             FROM products p
             LEFT JOIN order_items oi ON p.id = oi.product_id
             LEFT JOIN online_order_items ooi ON p.id = ooi.product_id
             WHERE p.category = 'drink' 
             GROUP BY p.name ORDER BY total_sold DESC LIMIT 5";
$res_best = mysqli_query($conn, $sql_best);
$best_labels = []; $best_data = [];
while ($row = mysqli_fetch_assoc($res_best)) {
    $best_labels[] = $row['name'];
    $best_data[] = $row['total_sold'];
}

$sql_barista = "SELECT u.username, SUM(t.total_amount) AS total_sales
                FROM transactions t
                JOIN users u ON t.user_id = u.id
                WHERE u.role = 'barista'
                GROUP BY u.username ORDER BY total_sales DESC LIMIT 5";
$res_barista = mysqli_query($conn, $sql_barista);
$barista_labels = []; $barista_data = [];
while ($row = mysqli_fetch_assoc($res_barista)) {
    $barista_labels[] = $row['username'];
    $barista_data[] = $row['total_sales'];
}

$total_pos = mysqli_fetch_row(mysqli_query($conn, "SELECT IFNULL(SUM(total_amount), 0) FROM transactions"))[0];
$total_online = mysqli_fetch_row(mysqli_query($conn, "SELECT IFNULL(SUM(total_amount), 0) FROM online_orders WHERE (status = 'Completed' OR status = 'Delivered' OR status = 'COMPLETED' OR status = 'DELIVERED')"))[0];
$grand_total = $total_pos + $total_online;

$baristas_list = [];
$res_b_list = mysqli_query($conn, "SELECT id, username FROM users WHERE role = 'barista' ORDER BY username ASC");
while ($row = mysqli_fetch_assoc($res_b_list)) { $baristas_list[] = $row; }

$selected_id = isset($_GET['barista_id']) ? intval($_GET['barista_id']) : 0;
$indiv_name = "";
$indiv_petty = [];

if ($selected_id > 0) {
    $stmt_n = mysqli_prepare($conn, "SELECT username FROM users WHERE id = ?");
    mysqli_stmt_bind_param($stmt_n, "i", $selected_id);
    mysqli_stmt_execute($stmt_n);
    $indiv_name = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_n))['username'];

    $stmt_p = mysqli_prepare($conn, "SELECT * FROM petty_cash WHERE user_id = ? ORDER BY recorded_at DESC LIMIT 10");
    mysqli_stmt_bind_param($stmt_p, "i", $selected_id);
    mysqli_stmt_execute($stmt_p);
    $res_p = mysqli_stmt_get_result($stmt_p);
    while($r = mysqli_fetch_assoc($res_p)) { $indiv_petty[] = $r; }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sales Analytics | Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-brown: #5D4037;
            --dark-brown: #3E2723;
            --light-bg: #f4f7f6;
            --white: #ffffff;
            --accent-blue: #1976D2;
            --accent-green: #2E7D32;
            --sidebar-width: 260px;
            --header-height: 70px;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Poppins', 'Segoe UI', sans-serif; background-color: var(--light-bg); color: #333; display: flex; min-height: 100vh; }
        a { text-decoration: none; color: inherit; }
        .sidebar { width: var(--sidebar-width); background: linear-gradient(180deg, var(--dark-brown) 0%, #2d1b18 100%); color: white; position: fixed; height: 100vh; left: 0; top: 0; display: flex; flex-direction: column; z-index: 100; box-shadow: 4px 0 15px rgba(0,0,0,0.1); }
        .brand-logo { height: var(--header-height); display: flex; align-items: center; padding: 0 25px; font-size: 1.3rem; font-weight: 700; letter-spacing: 0.5px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .nav-links { flex: 1; padding: 20px 0; overflow-y: auto; }
        .nav-item { display: block; padding: 15px 25px; color: rgba(255,255,255,0.7); font-weight: 500; font-size: 0.95rem; border-left: 4px solid transparent; cursor: pointer; transition: all 0.3s; }
        .nav-item:hover, .nav-item.active { background: rgba(255,255,255,0.05); color: white; border-left-color: #A1887F; }
        .submenu-container { display: none; background: rgba(0,0,0,0.2); }
        .submenu-container.show { display: block; }
        .sub-link { padding-left: 45px; font-size: 0.85rem; }
        .sidebar-footer { padding: 20px; font-size: 0.8rem; text-align: center; border-top: 1px solid rgba(255,255,255,0.1); color:rgba(255,255,255,0.4); }
        .main-content { margin-left: var(--sidebar-width); flex: 1; display: flex; flex-direction: column; }
        .top-header { height: var(--header-height); background: white; display: flex; justify-content: space-between; align-items: center; padding: 0 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .page-title { font-size: 1.4rem; font-weight: 700; color: var(--primary-brown); }
        .user-badge { padding: 8px 15px; background: var(--light-bg); border-radius: 20px; font-weight: 600; font-size: 0.85rem; color: var(--primary-brown); cursor: pointer; display: flex; align-items: center; gap: 8px; }
        .content-scroll { padding: 30px; overflow-y: auto; }
        .metrics-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 25px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 25px; border-radius: 16px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); text-align: center; border: 1px solid rgba(0,0,0,0.02); transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-3px); }
        .card-title { color: #888; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; font-weight: 600; margin-bottom: 10px; }
        .card-value { font-size: 1.8rem; font-weight: 700; color: var(--primary-brown); }
        .charts-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 30px; margin-bottom: 40px; }
        .chart-box { background: white; padding: 30px; border-radius: 16px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); border: 1px solid rgba(0,0,0,0.02); display: flex; flex-direction: column; }
        .full-width { grid-column: span 2; }
        .chart-title { font-size: 1.1rem; font-weight: 700; color: #333; margin-bottom: 20px; border-bottom: 1px solid #f0f0f0; padding-bottom: 15px; }
        .barista-list { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 25px; background: white; padding: 20px; border-radius: 16px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); }
        .barista-btn { text-decoration: none; padding: 10px 20px; border-radius: 30px; background-color: #f5f5f5; color: #555; font-weight: 500; font-size: 0.9rem; transition: all 0.3s; }
        .barista-btn.active { background: linear-gradient(135deg, #5D4037 0%, #3e2723 100%); color: white; box-shadow: 0 4px 10px rgba(93, 64, 55, 0.3); }
        .table-container { background: white; border-radius: 16px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); overflow: hidden; border: 1px solid rgba(0,0,0,0.02); margin-bottom: 30px; }
        .table-header { padding: 20px 25px; border-bottom: 1px solid #eee; background: #fff; }
        .table-header h5 { margin: 0; font-size: 1.1rem; color: var(--primary-brown); }
        table { width: 100%; border-collapse: collapse; }
        thead th { background: #fafafa; color: var(--primary-brown); font-weight: 700; padding: 15px 25px; text-transform: uppercase; font-size: 0.75rem; border-bottom: 2px solid #eee; text-align: left; }
        tbody td { padding: 15px 25px; border-bottom: 1px solid #f8f8f8; color: #444; font-size: 0.9rem; vertical-align: top; }
        .item-list { list-style: none; padding: 0; margin: 0; font-size: 0.85rem; color: #555; }
        .tax-box { font-size: 0.75rem; color: #777; background: #f9f9f9; padding: 5px 8px; border-radius: 6px; border: 1px solid #eee; display:inline-block; }
        .badge-online { background: #E3F2FD; color: #1565C0; padding: 4px 10px; border-radius: 6px; font-weight: 600; font-size: 0.75rem; }
        .badge-walkin { background: #E8F5E9; color: #2E7D32; padding: 4px 10px; border-radius: 6px; font-weight: 600; font-size: 0.75rem; }
        .profile-dropdown-container { position: relative; }
        .dropdown-content { display: none; position: absolute; right: 0; top: 130%; background: white; box-shadow: 0 10px 30px rgba(0,0,0,0.1); border-radius: 12px; min-width: 180px; overflow: hidden; border: 1px solid #eee; z-index: 200; }
        .dropdown-content.show { display: block; animation: fadeIn 0.2s ease; }
        @keyframes fadeIn { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:translateY(0); } }
    </style>
    <script>
        function toggleSidebarMenu(menuId) { document.getElementById(menuId).classList.toggle("show"); }
        function toggleProfileMenu() { document.getElementById("profileDropdown").classList.toggle("show"); }
    </script>
</head>
<body>
    <nav class="sidebar">
        <div class="brand-logo">HOSSANA CAFE</div>
        <div class="nav-links">
            <a href="admin_dashboard.php" class="nav-item">📊 Dashboard</a>
            <div class="nav-item dropdown-btn" onclick="toggleSidebarMenu('productMenu')"><span>☕ Products & Menu</span><span style="font-size: 10px;">▼</span></div>
            <div id="productMenu" class="submenu-container">
                <a href="add_product.php" class="nav-item sub-link">➕ Add Product</a>
                <a href="admin_products.php" class="nav-item sub-link">📋 Product List</a>
                <a href="manage_subcategories.php" class="nav-item sub-link">📂 Manage Subcategories</a>
            </div>
            <a href="admin_sales_report.php" class="nav-item active" style="background:rgba(255,255,255,0.1); border-left-color:#A1887F;">💰 Sales Reports</a>
            <a href="inventory.php" class="nav-item">📦 Inventory</a>
            <div class="nav-item" onclick="toggleSidebarMenu('userManage')" style="cursor:pointer;">👥 User Management <span style="float:right; font-size:10px;">▼</span></div>
            <div id="userManage" class="submenu-container">
                <a href="manage_admins.php" class="nav-item sub-link">User Overview</a>
                <a href="add_barista.php" class="nav-item sub-link">Baristas</a>
                <a href="add_rider.php" class="nav-item sub-link">Riders</a>
            </div>
        </div>
        <div class="sidebar-footer">Logged in as Admin</div>
    </nav>

    <main class="main-content">
        <header class="top-header">
            <div class="page-title">Sales Analytics</div>
            <div class="profile-dropdown-container">
                <div class="user-badge" onclick="toggleProfileMenu()">Admin Account ▼</div>
                <div id="profileDropdown" class="dropdown-content">
                    <a href="admin_profile.php" style="display:block; padding:12px 20px;">👤 Profile</a>
                    <a href="logout.php" style="display:block; padding:12px 20px; color:#c62828;">🚪 Logout</a>
                </div>
            </div>
        </header>

        <div class="content-scroll">
            <div class="metrics-row">
                <div class="stat-card"><div class="card-title">TOTAL POS OO GANI SALES</div><div class="card-value">₱ <?= number_format($total_pos, 2); ?></div></div>
                <div class="stat-card"><div class="card-title">TOTAL ONLINE SALES</div><div class="card-value">₱ <?= number_format($total_online, 2); ?></div></div>
                <div class="stat-card"><div class="card-title">GRAND TOTAL REVENUE</div><div class="card-value" style="color:#2E7D32;">₱ <?= number_format($grand_total, 2); ?></div></div>
            </div>

            <div class="charts-grid">
                <div class="chart-box full-width"><div class="chart-title">Last 6 Months Revenue Trend</div><canvas id="monthlySalesChart" style="max-height: 300px;"></canvas></div>
                <div class="chart-box"><div class="chart-title">Top Baristas (Sales Processed)</div><canvas id="baristaSalesChart"></canvas></div>
                <div class="chart-box"><div class="chart-title">Top 5 Best Selling Drinks</div><canvas id="bestSellerChart"></canvas></div>
            </div>

            <h3 style="margin-bottom:15px; color:#5D4037;">📋 Detailed Barista Logs</h3>
            <div class="barista-list">
                <a href="admin_sales_report.php" class="barista-btn <?= ($selected_id == 0) ? 'active' : ''; ?>">All Store Orders</a>
                <?php foreach ($baristas_list as $b): ?>
                    <a href="?barista_id=<?= $b['id']; ?>" class="barista-btn <?= ($selected_id == $b['id']) ? 'active' : ''; ?>">
                       <?= htmlspecialchars($b['username']); ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <div class="table-container">
                <div class="table-header">
                    <h5>Viewing: <span style="color:#20639b; font-weight:700;"><?= ($selected_id > 0) ? htmlspecialchars($indiv_name) : "All Store Transactions"; ?></span></h5>
                </div>
                <table>
                    <thead>
                        <tr><th>ID</th><th>Date & Time</th><th>Type</th><th style="width: 25%;">Items Ordered</th><th>Details</th><th>Tax</th><th>Total</th></tr>
                    </thead>
                    <tbody>
                        <?php
                        $w_online = ($selected_id > 0) ? "WHERE prepared_by = $selected_id AND (status='Completed' OR status='Delivered' OR status='COMPLETED' OR status='DELIVERED')" : "WHERE (status='Completed' OR status='Delivered' OR status='COMPLETED' OR status='DELIVERED')";
                        $w_pos = ($selected_id > 0) ? "WHERE user_id = $selected_id" : "";

                        $sql_logs = "
                            (SELECT id, order_date as log_date, total_amount, delivery_address as info, 'Online' as type FROM online_orders $w_online)
                            UNION ALL
                            (SELECT id, transaction_date as log_date, total_amount, 'Face-to-Face' as info, 'Walk-in' as type FROM transactions $w_pos)
                            ORDER BY log_date DESC LIMIT 50";
                        
                        $res_logs = mysqli_query($conn, $sql_logs);
                        if (mysqli_num_rows($res_logs) > 0) {
                            while ($row = mysqli_fetch_assoc($res_logs)) {
                                $tid = $row['id']; $total = $row['total_amount']; $type = $row['type'];
                                $v_base = $total / 1.12; $v_tax = $total - $v_base;
                                $q_items = ($type == 'Online') ? "SELECT ooi.quantity, p.name FROM online_order_items ooi JOIN products p ON ooi.product_id = p.id WHERE ooi.order_id = $tid" : "SELECT oi.quantity, p.name FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.transaction_id = $tid";
                                $ritems = mysqli_query($conn, $q_items);
                        ?>
                        <tr>
                            <td>#<?= $tid; ?></td>
                            <td><?= date("M d, h:i A", strtotime($row['log_date'])); ?></td>
                            <td><span class="<?= ($type == 'Online' ? 'badge-online' : 'badge-walkin'); ?>"><?= strtoupper($type); ?></span></td>
                            <td><ul class="item-list"><?php while($i = mysqli_fetch_assoc($ritems)) echo "<li>{$i['quantity']}x {$i['name']}</li>"; ?></ul></td>
                            <td><?= ($type == 'Online') ? "📍 ".htmlspecialchars($row['info'] ?? 'N/A') : "Store Counter"; ?></td>
                            <td><div class="tax-box">Base: ₱<?=number_format($v_base,2)?><br>VAT: ₱<?=number_format($v_tax,2)?></div></td>
                            <td style="font-weight:bold; color:#28a745;">₱<?= number_format($total, 2); ?></td>
                        </tr>
                        <?php } } else { echo "<tr><td colspan='7' style='text-align:center; padding:30px;'>No transactions found.</td></tr>"; } ?>
                    </tbody>
                </table>
            </div>

            <?php if ($selected_id > 0 && !empty($indiv_petty)): ?>
                <div class="table-container"><div class="table-header"><h5>Petty Cash Logs (Last 10)</h5></div>
                    <table><thead><tr><th>ID</th><th>Date</th><th>Description</th><th>Amount</th></tr></thead>
                        <tbody><?php foreach ($indiv_petty as $p): ?>
                            <tr><td>#<?= $p['id']; ?></td><td><?= date('M d, H:i', strtotime($p['recorded_at'])); ?></td><td><?= htmlspecialchars($p['description']) ?></td><td>₱<?= number_format(abs($p['amount']), 2) ?></td></tr>
                        <?php endforeach; ?></tbody>
                    </table>
                </div>
            <?php endif; ?>
            <div style="height:50px;"></div>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            new Chart(document.getElementById('monthlySalesChart'), {
                type: 'line',
                data: {
                    labels: <?= json_encode(array_keys($monthly_sales_data)); ?>,
                    datasets: [{ label: 'Revenue (₱)', data: <?= json_encode(array_values($monthly_sales_data)); ?>, borderColor: '#5D4037', backgroundColor: 'rgba(93, 64, 55, 0.1)', borderWidth: 2, tension: 0.4, fill: true, pointBackgroundColor: '#fff', pointBorderColor: '#5D4037' }]
                },
                options: { responsive: true, plugins: { legend: {display: false} }, scales: { y: { grid: { borderDash: [5, 5] } }, x: { grid: { display: false } } } }
            });
            new Chart(document.getElementById('baristaSalesChart'), {
                type: 'bar',
                data: {
                    labels: <?= json_encode($barista_labels); ?>,
                    datasets: [{ label: 'Sales (₱)', data: <?= json_encode($barista_data); ?>, backgroundColor: '#A1887F', borderRadius: 4, hoverBackgroundColor: '#5D4037' }]
                },
                options: { indexAxis: 'y', responsive: true, plugins: { legend: {display: false} }, scales: { x: { grid: { display: false } }, y: { grid: { display: false } } } }
            });
            new Chart(document.getElementById('bestSellerChart'), {
                type: 'bar',
                data: {
                    labels: <?= json_encode($best_labels); ?>,
                    datasets: [{ label: 'Units Sold', data: <?= json_encode($best_data); ?>, backgroundColor: ['#5D4037', '#795548', '#8D6E63', '#A1887F', '#BCAAA4'], borderRadius: 4 }]
                },
                options: { responsive: true, plugins: { legend: {display: false} }, scales: { x: { grid: { display: false } }, y: { grid: { borderDash: [5, 5] } } } }
            });
        });
    </script>
</body>
</html>