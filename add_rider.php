<?php
session_start();
include 'db.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['role'] != 'admin') {
    header("location: login.php");
    exit;
}

$message = "";
$error = "";
$edit_mode = false;
$edit_id = 0;
$edit_username = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. ADD NEW RIDER
    if (isset($_POST['add_rider'])) {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $confirm_pass = $_POST['confirm_password'];

        if (empty($username) || empty($password)) {
            $error = "Please fill in all fields.";
        } elseif ($password !== $confirm_pass) {
            $error = "Passwords do not match.";
        } else {
            $check = mysqli_query($conn, "SELECT id FROM users WHERE username = '$username'");
            if (mysqli_num_rows($check) > 0) {
                $error = "Username already exists.";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $role = 'rider';
                $stmt = mysqli_prepare($conn, "INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
                mysqli_stmt_bind_param($stmt, "sss", $username, $hashed_password, $role);
                if (mysqli_stmt_execute($stmt)) { $message = "Rider account created successfully."; } 
                else { $error = "Error creating account."; }
            }
        }
    }
    // 2. UPDATE RIDER
    if (isset($_POST['update_rider'])) {
        $id = intval($_POST['edit_id']);
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $confirm_pass = $_POST['confirm_password'];

        if (empty($username)) { $error = "Username cannot be empty."; } 
        else {
            $check = mysqli_query($conn, "SELECT id FROM users WHERE username = '$username' AND id != $id");
            if (mysqli_num_rows($check) > 0) { $error = "Username already exists."; } 
            else {
                if (!empty($password)) {
                    if ($password !== $confirm_pass) { $error = "New passwords do not match."; } 
                    else {
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = mysqli_prepare($conn, "UPDATE users SET username=?, password=? WHERE id=? AND role='rider'");
                        mysqli_stmt_bind_param($stmt, "ssi", $username, $hashed_password, $id);
                        if (mysqli_stmt_execute($stmt)) { $message = "Account updated (Password changed)."; $edit_mode = false; }
                    }
                } else {
                    $stmt = mysqli_prepare($conn, "UPDATE users SET username=? WHERE id=? AND role='rider'");
                    mysqli_stmt_bind_param($stmt, "si", $username, $id);
                    if (mysqli_stmt_execute($stmt)) { $message = "Account details updated."; $edit_mode = false; }
                }
            }
        }
    }
}

if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = mysqli_prepare($conn, "DELETE FROM users WHERE id=? AND role='rider'");
    mysqli_stmt_bind_param($stmt, "i", $id);
    if (mysqli_stmt_execute($stmt)) { $message = "Rider account deleted."; } 
    else { $error = "Error deleting account."; }
}

if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $res = mysqli_query($conn, "SELECT * FROM users WHERE id=$edit_id AND role='rider'");
    if ($row = mysqli_fetch_assoc($res)) { $edit_mode = true; $edit_username = $row['username']; }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Riders | Hossana Cafe</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --p-brown: #5D4037; --d-brown: #3E2723; --bg: #f4f7f6; --side-w: 260px; --head-h: 70px; }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Poppins', sans-serif; }
        body { background: var(--bg); display: flex; min-height: 100vh; font-size: 16px; color: #333; }
        .sidebar { width: var(--side-w); background: linear-gradient(180deg, var(--d-brown) 0%, #2d1b18 100%); color: white; position: fixed; height: 100vh; z-index: 1000; box-shadow: 4px 0 15px rgba(0,0,0,0.1); transition: transform 0.3s ease; }
        .brand-logo { height: var(--head-h); display: flex; align-items: center; padding: 0 25px; font-size: 1.25rem; font-weight: 700; border-bottom: 1px solid rgba(255,255,255,0.1); letter-spacing: 0.5px; }
        .nav-links { padding: 15px 0; }
        .nav-item { display: block; padding: 14px 25px; color: rgba(255,255,255,0.7); text-decoration: none; border-left: 4px solid transparent; font-size: 15px; transition: 0.3s; width: 100%; text-align: left; background: none; border: none; cursor: pointer; }
        .nav-item:hover, .nav-item.active { background: rgba(255,255,255,0.08); color: white; border-left-color: #A1887F; }
        .submenu-container { display: none; background: rgba(0,0,0,0.15); padding: 5px 0; }
        .submenu-container.show { display: block; }
        .sub-link { padding-left: 55px; font-size: 14px; }
        .main-content { margin-left: var(--side-w); flex: 1; display: flex; flex-direction: column; width: calc(100% - var(--side-w)); transition: margin 0.3s ease, width 0.3s ease; }
        .top-header { height: var(--head-h); background: white; display: flex; justify-content: space-between; align-items: center; padding: 0 30px; box-shadow: 0 2px 15px rgba(0,0,0,0.04); position: sticky; top: 0; z-index: 99; }
        .page-title { font-size: 1.4rem; font-weight: 700; color: var(--d-brown); }
        .user-badge { padding: 8px 18px; background: var(--bg); border-radius: 30px; font-weight: 600; font-size: 0.9rem; cursor: pointer; border: 1px solid #eee; }
        .content-scroll { padding: 40px; overflow-y: auto; }
        .split-container { display: grid; grid-template-columns: 1fr 1.5fr; gap: 30px; align-items: start; }
        .card-panel { background: white; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.03); border: 1px solid rgba(0,0,0,0.02); padding: 30px; }
        .card-header { font-size: 1.25rem; font-weight: 700; color: var(--p-brown); margin-bottom: 25px; border-bottom: 2px solid #f0f0f0; padding-bottom: 15px; }
        .form-group { margin-bottom: 25px; }
        .form-group label { display: block; font-weight: 600; color: var(--d-brown); margin-bottom: 10px; font-size: 0.95rem; }
        .form-group input { width: 100%; padding: 15px 20px; border-radius: 12px; border: 1px solid #ddd; font-size: 1rem; background: #fafafa; transition: 0.3s; }
        .form-group input:focus { border-color: var(--p-brown); background: white; outline: none; box-shadow: 0 4px 12px rgba(93, 64, 55, 0.1); }
        .btn-submit { width: 100%; background: var(--p-brown); color: white; border: none; padding: 16px; border-radius: 12px; font-weight: 600; font-size: 1rem; cursor: pointer; transition: 0.3s; box-shadow: 0 4px 15px rgba(93, 64, 55, 0.2); }
        .btn-submit:hover { background: var(--d-brown); transform: translateY(-2px); }
        .btn-cancel { display: block; text-align: center; margin-top: 15px; color: #777; text-decoration: none; font-size: 0.9rem; font-weight: 600; }
        .btn-cancel:hover { color: #333; }
        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 20px; background: #fafafa; color: var(--p-brown); font-size: 0.9rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #eee; }
        td { padding: 20px; border-bottom: 1px solid #f8f8f8; font-size: 1rem; color: #444; }
        .btn-small { padding: 8px 16px; border-radius: 8px; font-size: 0.85rem; text-decoration: none; font-weight: 600; border: none; cursor: pointer; transition:0.2s; }
        .btn-blue { background: #e3f2fd; color: #1565c0; margin-right: 5px; }
        .btn-blue:hover { background: #bbdefb; }
        .btn-red { background: #ffebee; color: #c62828; }
        .btn-red:hover { background: #ffcdd2; }
        .alert { padding: 15px 20px; border-radius: 12px; margin-bottom: 25px; font-weight: 500; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: #E8F5E9; color: #2E7D32; border-left: 5px solid #2E7D32; }
        .alert-error { background: #FFEBEE; color: #C62828; border-left: 5px solid #C62828; }
        .dropdown-content { display: none; position: absolute; right: 30px; top: 75px; background: white; box-shadow: 0 10px 40px rgba(0,0,0,0.1); border-radius: 12px; min-width: 180px; z-index: 200; border: 1px solid #eee; overflow: hidden; }
        .dropdown-content.show { display: block; }
        .dropdown-content a { display: block; padding: 12px 20px; font-size: 0.95rem; text-decoration: none; color: #333; transition: 0.2s; }
        .menu-toggle { display: none; font-size: 1.5rem; color: var(--p-brown); cursor: pointer; margin-right: 15px; }
        @media (max-width: 1024px) { .split-container { grid-template-columns: 1fr; } }
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
        <div class="brand-logo">HOSSANA CAFE <span style="margin-left:auto; cursor:pointer; font-size:1.2rem;" onclick="toggleSidebar()" class="close-sidebar-btn">✕</span></div>
        <div class="nav-links">
            <a href="admin_dashboard.php" class="nav-item">📊 Dashboard</a>
            <button class="nav-item" onclick="toggleSidebarMenu('productMenu')">☕ Products & Menu <span style="float:right; font-size:10px;">▼</span></button>
            <div id="productMenu" class="submenu-container">
                <a href="add_product.php" class="nav-item sub-link">➕ Add Product</a>
                <a href="admin_products.php" class="nav-item sub-link">📋 Product List</a>
                <a href="manage_subcategories.php" class="nav-item sub-link">📂 Manage Subcategories</a>
            </div>
            <a href="admin_sales_report.php" class="nav-item">💰 Sales Reports</a>
            <a href="inventory.php" class="nav-item">📦 Inventory</a>
            <button class="nav-item active" onclick="toggleSidebarMenu('userManage')">👥 User Management <span style="float:right; font-size:10px;">▼</span></button>
            <div id="userManage" class="submenu-container show">
                <a href="manage_admins.php" class="nav-item sub-link">User Overview</a>
                <a href="add_barista.php" class="nav-item sub-link">Baristas</a>
                <a href="add_rider.php" class="nav-item sub-link active">Riders</a>
            </div>
        </div>
    </nav>
    <main class="main-content">
        <header class="top-header">
            <div style="display:flex; align-items:center;">
                <div class="menu-toggle" onclick="toggleSidebar()">☰</div>
                <div class="page-title">Manage Riders</div>
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
                    <div class="card-header"><?php echo $edit_mode ? "Edit Rider Account" : "Add New Rider"; ?></div>
                    <form action="add_rider.php" method="post">
                        <?php if($edit_mode): ?><input type="hidden" name="edit_id" value="<?php echo $edit_id; ?>"><?php endif; ?>
                        <div class="form-group">
                            <label>Username</label>
                            <input type="text" name="username" placeholder="Enter username" value="<?php echo htmlspecialchars($edit_username); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Password <?php if($edit_mode) echo '<small style="font-weight:400; color:#888;">(Leave blank to keep current)</small>'; ?></label>
                            <input type="password" name="password" placeholder="<?php echo $edit_mode ? 'New password (optional)' : 'Enter password'; ?>" <?php echo $edit_mode ? '' : 'required'; ?>>
                        </div>
                        <div class="form-group">
                            <label>Confirm Password</label>
                            <input type="password" name="confirm_password" placeholder="Repeat password" <?php echo $edit_mode ? '' : 'required'; ?>>
                        </div>
                        <button type="submit" name="<?php echo $edit_mode ? 'update_rider' : 'add_rider'; ?>" class="btn-submit"><?php echo $edit_mode ? "Update Account" : "Create Account"; ?></button>
                        <?php if($edit_mode): ?><a href="add_rider.php" class="btn-cancel">Cancel Edit</a><?php endif; ?>
                    </form>
                </div>
                <div class="card-panel">
                    <div class="card-header">Existing Rider Accounts</div>
                    <div class="table-wrapper">
                        <table>
                            <thead><tr><th>ID</th><th>Username</th><th style="text-align:right;">Actions</th></tr></thead>
                            <tbody>
                                <?php 
                                $sql = "SELECT id, username FROM users WHERE role='rider' ORDER BY id DESC";
                                $res = mysqli_query($conn, $sql);
                                if (mysqli_num_rows($res) > 0):
                                    while ($row = mysqli_fetch_assoc($res)): 
                                ?>
                                <tr>
                                    <td style="color:#888;">#<?php echo $row['id']; ?></td>
                                    <td style="font-weight:600; color:var(--d-brown);"><?php echo htmlspecialchars($row['username']); ?></td>
                                    <td style="text-align:right;">
                                        <a href="add_rider.php?edit=<?php echo $row['id']; ?>" class="btn-small btn-blue">Edit</a>
                                        <a href="add_rider.php?delete=<?php echo $row['id']; ?>" class="btn-small btn-red" onclick="return confirm('Delete this rider account?');">Delete</a>
                                    </td>
                                </tr>
                                <?php endwhile; else: ?>
                                    <tr><td colspan="3" style="text-align:center; padding:40px; color:#999;">No rider accounts found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div style="height:50px;"></div>
        </div>
    </main>
    <script>
        function toggleSidebarMenu(menuId) { document.getElementById(menuId).classList.toggle("show"); }
        function toggleProfileMenu() { document.getElementById("profileDropdown").classList.toggle("show"); }
        function toggleSidebar() { document.getElementById('sidebar').classList.toggle('active'); }
    </script>
</body>
</html>