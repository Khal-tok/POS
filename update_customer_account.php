<?php
session_start();
include 'db.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['role'] != 'customer') {
    header("location: customer_login.php");
    exit;
}

$current_username = $_SESSION['username'];
$error = $success = "";

$sql_fetch = "SELECT id, username FROM users WHERE username = ?";
$stmt_fetch = mysqli_prepare($conn, $sql_fetch);
mysqli_stmt_bind_param($stmt_fetch, "s", $current_username);
mysqli_stmt_execute($stmt_fetch);
$result_fetch = mysqli_stmt_get_result($stmt_fetch);
$user_data = mysqli_fetch_assoc($result_fetch);
$user_id = $user_data['id'];
mysqli_stmt_close($stmt_fetch);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_username = $_POST['username'];
    $new_password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if ($new_password !== $confirm_password) {
        $error = "New password and confirmation do not match.";
    } else {
        $sql_check_username = "SELECT id FROM users WHERE username = ? AND id != ?";
        $stmt_check = mysqli_prepare($conn, $sql_check_username);
        mysqli_stmt_bind_param($stmt_check, "si", $new_username, $user_id);
        mysqli_stmt_execute($stmt_check);
        mysqli_stmt_store_result($stmt_check);
        
        if (mysqli_stmt_num_rows($stmt_check) > 0) {
            $error = "This username is already taken by another user.";
        } else {
            $sql_update = "UPDATE users SET username = ?";
            $types = "s";
            $params = [$new_username];

            if (!empty($new_password)) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $sql_update .= ", password = ?";
                $types .= "s";
                $params[] = $hashed_password;
            }
            
            $sql_update .= " WHERE id = ?";
            $types .= "i";
            $params[] = $user_id;
            
            $stmt_update = mysqli_prepare($conn, $sql_update);
            mysqli_stmt_bind_param($stmt_update, $types, ...$params);

            if (mysqli_stmt_execute($stmt_update)) {
                $_SESSION['username'] = $new_username;
                $current_username = $new_username;
                $success = "Account successfully updated!";
            } else {
                $error = "Error updating account: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt_update);
        }
        mysqli_stmt_close($stmt_check);
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Update Account</title>
    <link rel="stylesheet" href="update_customer_account.css">
</head>
<body>
<div class="container">
    <h2>Update Account</h2>
    
    <div class="nav-links">
        <a href="customer_dashboard.php">← Back to Dashboard</a> | 
        <a href="logout_customer.php">Logout</a>
    </div>

    <?php if (!empty($error)): ?>
        <p class="message-error"><?php echo $error; ?></p>
    <?php endif; ?>
    <?php if (!empty($success)): ?>
        <p class="message-success"><?php echo $success; ?></p>
    <?php endif; ?>

    <form action="" method="post">
        <div>
            <label for="username">Current Username</label>
            <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($current_username); ?>" required>
        </div>

        <div>
            <label for="password">New Password <small style="color:#888; font-weight:normal;">(Leave blank to keep current)</small></label>
            <input type="password" id="password" name="password">
        </div>

        <div>
            <label for="confirm_password">Confirm New Password</label>
            <input type="password" id="confirm_password" name="confirm_password">
        </div>

        <input type="submit" value="Save Changes">
    </form>
    
    <div class="delete-section">
        <a href="customer_delete_account.php" class="delete-link" onclick="return confirm('Are you sure you want to delete your account? This cannot be undone.');">⚠ Delete My Account</a>
    </div>
</div>
</body>
</html>