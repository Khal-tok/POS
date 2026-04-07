<?php
session_start();
include 'db.php';

$message = "";
$error = "";

// 1. Get the token from the URL
if (isset($_GET['token'])) {
    $token = $_GET['token'];

    // 2. Prepare and execute the query to find the user with this token and unverified status
    $sql = "SELECT id, username FROM users WHERE verification_token = ? AND is_verified = 0";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $token);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($user = mysqli_fetch_assoc($result)) {
        // 3. If user is found, update their status to verified
        $user_id = $user['id'];
        
        // Mark as verified and clear the token
        $sql_update = "UPDATE users SET is_verified = 1, verification_token = NULL WHERE id = ?";
        $stmt_update = mysqli_prepare($conn, $sql_update);
        mysqli_stmt_bind_param($stmt_update, "i", $user_id);
        
        if (mysqli_stmt_execute($stmt_update)) {
            $message = "Success! Your account has been verified. You can now login.";
        } else {
            $error = "Verification failed. Could not update account status.";
        }
        mysqli_stmt_close($stmt_update);
        
    } else {
        // 4. Check if the token exists but is already verified
        $sql_check_verified = "SELECT id FROM users WHERE verification_token = ?";
        $stmt_check = mysqli_prepare($conn, $sql_check_verified);
        mysqli_stmt_bind_param($stmt_check, "s", $token);
        mysqli_stmt_execute($stmt_check);
        $check_result = mysqli_stmt_get_result($stmt_check);

        if (mysqli_num_rows($check_result) == 0) {
            $error = "Invalid or expired verification link.";
        } else {
             $error = "Your account is already verified or the token is no longer active.";
        }
        mysqli_stmt_close($stmt_check);
    }
    mysqli_stmt_close($stmt);

} else {
    $error = "Verification token is missing.";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Account Verification</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #F8F4EF; color: #4E342E; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .container { width: 90%; max-width: 500px; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1); text-align: center; }
        h2 { color: #8B4513; border-bottom: 2px solid #E0E0E0; padding-bottom: 10px; margin-top: 0; margin-bottom: 20px; }
        .message-success { background-color: #d4edda; color: #155724; padding: 15px; margin: 15px 0; border-radius: 5px; border: 1px solid #c3e6cb; font-weight: bold; }
        .message-error { background-color: #f8d7da; color: #721c24; padding: 15px; margin: 15px 0; border-radius: 5px; border: 1px solid #f5c6cb; font-weight: bold; }
        .login-link a { color: #8B4513; text-decoration: none; font-weight: bold; }
    </style>
</head>
<body>
<div class="container">
    <h2>Account Verification Status</h2>
    
    <?php if (!empty($message)): ?>
        <p class="message-success"><?php echo $message; ?></p>
    <?php endif; ?>
    
    <?php if (!empty($error)): ?>
        <p class="message-error"><?php echo $error; ?></p>
    <?php endif; ?>
    
    <p class="login-link">
        <a href="customer_login.php">Click here to login.</a>
    </p>
</div>
</body>
</html>