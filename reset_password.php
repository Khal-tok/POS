<?php
session_start();
// 1. Force Timezone
date_default_timezone_set('Asia/Manila');
include 'db.php'; 

$error = "";
$message = "";
$valid_token = false;
$token_from_url = "";
$debug_info = ""; // To show you what's wrong

// 1. GET DATA
if (isset($_GET['token'])) {
    $token_from_url = $_GET['token'];
    
    // 2. SEARCH BY TOKEN ONLY (Ignore username for now to prevent matching errors)
    $sql_find = "SELECT id, username, reset_expires_at FROM users WHERE reset_token = ?";
    $stmt_find = mysqli_prepare($conn, $sql_find);
    mysqli_stmt_bind_param($stmt_find, "s", $token_from_url);
    mysqli_stmt_execute($stmt_find);
    $result_find = mysqli_stmt_get_result($stmt_find);
    $user_data = mysqli_fetch_assoc($result_find);
    mysqli_stmt_close($stmt_find);

    if ($user_data) {
        // Token exists! Now check expiry using PHP time
        $expiry_str = $user_data['reset_expires_at'];
        $expiry_time = strtotime($expiry_str);
        $current_time = time();
        $current_str = date("Y-m-d H:i:s");

        if ($current_time > $expiry_time) {
            // DIAGNOSTIC ERROR MESSAGE
            $error = "Link Expired.<br>Server Time: <b>$current_str</b><br>Expiry Time: <b>$expiry_str</b>";
        } else {
            // Success!
            $valid_token = true;
            $username = $user_data['username']; 
        }
    } else {
        $error = "<b>Invalid Token.</b><br>The token in your link was not found in the database.<br><i>(Did you request a new password link? Requesting a new one invalidates the old one.)</i>";
    }
} else {
    if ($_SERVER["REQUEST_METHOD"] != "POST") {
        $error = "Access denied. No token provided.";
    }
}

// 3. HANDLE PASSWORD UPDATE
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['token'])) {
    $token_post = $_POST['token'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Verify token again before saving
    $sql_verify = "SELECT id FROM users WHERE reset_token = ?";
    $stmt_verify = mysqli_prepare($conn, $sql_verify);
    mysqli_stmt_bind_param($stmt_verify, "s", $token_post);
    mysqli_stmt_execute($stmt_verify);
    $res_verify = mysqli_stmt_get_result($stmt_verify);
    
    if (mysqli_num_rows($res_verify) > 0) {
        if (strlen($new_password) < 8) {
            $error = "New password must be at least 8 characters long.";
            $valid_token = true; 
        } elseif ($new_password !== $confirm_password) {
            $error = "Passwords do not match.";
            $valid_token = true;
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Update Password AND Clear Token
            $sql_update = "UPDATE users SET password = ?, reset_token = NULL, reset_expires_at = NULL WHERE reset_token = ?";
            $stmt_update = mysqli_prepare($conn, $sql_update);
            mysqli_stmt_bind_param($stmt_update, "ss", $hashed_password, $token_post);
            
            if (mysqli_stmt_execute($stmt_update)) {
                $message = "Success! Your password has been updated. <a href='customer_login.php'>Login here</a>.";
                $valid_token = false; 
            } else {
                $error = "Database error updating password.";
            }
        }
    } else {
        $error = "Invalid session. Please try clicking the link again.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Reset Password</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #F8F4EF; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .container { width: 90%; max-width: 450px; background: white; padding: 40px; border-radius: 10px; box-shadow: 0 10px 25px rgba(0,0,0,0.15); text-align: center; }
        .logo-header img { max-width: 70%; margin-bottom: 20px; }
        h2 { color: #8B4513; margin-bottom: 20px; }
        input[type="password"] { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; margin-bottom: 15px; }
        input[type="submit"] { width: 100%; padding: 12px; background: #66BB6A; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; font-weight: bold; }
        input[type="submit"]:hover { background: #4CAF50; }
        .error-msg { color: #D32F2F; background: #FFEBEE; padding: 15px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #FFCDD2; text-align: left; }
        .success-msg { color: #388E3C; background: #E8F5E9; padding: 10px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #C8E6C9; }
        .form-group { text-align: left; }
        label { font-weight: bold; display: block; margin-bottom: 5px; }
    </style>
</head>
<body>

<div class="container">
    <div class="logo-header"><img src="hosana.png" alt="Hosana Cafe"></div>
    
    <h2>Reset Password</h2>

    <?php if ($error): ?>
        <div class="error-msg"><?php echo $error; ?></div>
        <?php if (!$valid_token): ?>
            <p><a href="forgot_password.php" style="color:#8B4513; font-weight:bold;">Request a new link</a></p>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($message): ?>
        <div class="success-msg"><?php echo $message; ?></div>
    <?php endif; ?>

    <?php if ($valid_token): ?>
        <p style="margin-bottom:20px;">Resetting password for: <strong><?php echo htmlspecialchars($username); ?></strong></p>
        
        <form action="" method="post">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token_from_url ? $token_from_url : $_POST['token']); ?>">
            
            <div class="form-group">
                <label>New Password (Min 8 chars)</label>
                <input type="password" name="new_password" required minlength="8">
            </div>

            <div class="form-group">
                <label>Confirm New Password</label>
                <input type="password" name="confirm_password" required minlength="8">
            </div>

            <input type="submit" value="Update Password">
        </form>
    <?php endif; ?>
</div>

</body>
</html>