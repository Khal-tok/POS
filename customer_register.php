<?php
session_start();
include 'db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'src/Exception.php'; 
require 'src/PHPMailer.php';
require 'src/SMTP.php';

const SMTP_USERNAME = 'bogurtsherwin@gmail.com'; 
const SMTP_PASSWORD = 'rhvtsqdqyhporgqx';      
const SENDER_NAME = 'Hosana Cafe Verification';

$error = "";
$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $contact_number = trim($_POST['contact_number'] ?? '');

    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "All fields are required.";
    } elseif ($password !== $confirm_password) {
        $error = "Password and Confirm Password do not match.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {

        $sql_check = "SELECT id FROM users WHERE username = ? OR email = ?";
        $stmt_check = mysqli_prepare($conn, $sql_check);
        mysqli_stmt_bind_param($stmt_check, "ss", $username, $email);
        mysqli_stmt_execute($stmt_check);
        $result_check = mysqli_stmt_get_result($stmt_check);
        
        if (mysqli_num_rows($result_check) > 0) {
            $error = "Username or Email already registered.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $role = 'customer'; 

            $otp_code = rand(100000, 999999); 

            $sql_insert = "INSERT INTO users (username, email, password, role, contact_number, is_verified, verification_code) VALUES (?, ?, ?, ?, ?, 0, ?)";
            $stmt_insert = mysqli_prepare($conn, $sql_insert);
            mysqli_stmt_bind_param($stmt_insert, "ssssss", $username, $email, $hashed_password, $role, $contact_number, $otp_code);
            
            if (mysqli_stmt_execute($stmt_insert)) {

                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = SMTP_USERNAME;
                    $mail->Password   = SMTP_PASSWORD;
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                    $mail->Port       = 465;

                    $mail->setFrom(SMTP_USERNAME, SENDER_NAME);
                    $mail->addAddress($email, $username); 

                    $mail->isHTML(true);
                    $mail->Subject = 'Verify Your Hosana Cafe Account';
                    $mail->Body    = "
                        <div style='font-family: Arial, sans-serif; color: #333;'>
                            <h2 style='color: #8B4513;'>Welcome to Hosana Cafe!</h2>
                            <p>Thank you for registering. Your verification code is:</p>
                            <h1 style='background: #eee; padding: 10px; display: inline-block; letter-spacing: 5px; color: #8B4513;'>{$otp_code}</h1>
                            <p>Please enter this code on the verification page to activate your account.</p>
                        </div>
                    ";
                    $mail->AltBody = "Your verification code is: " . $otp_code;

                    $mail->send();
                    
                    header("Location: otp_verification.php?user=" . urlencode($username));
                    exit();

                } catch (Exception $e) {
                    $error = "Account created, but email failed to send. Mailer Error: {$mail->ErrorInfo}";
                }

            } else {
                $error = "Database Error: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt_insert);
        }
        mysqli_stmt_close($stmt_check);
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Customer Register</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #F8F4EF; color: #4E342E; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .container { width: 90%; max-width: 400px; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1); }
        h2 { color: #8B4513; border-bottom: 2px solid #E0E0E0; padding-bottom: 10px; margin-top: 0; margin-bottom: 20px; text-align: center;}
        .message-error { background-color: #f8d7da; color: #721c24; padding: 12px; margin: 15px 0; border-radius: 5px; border: 1px solid #f5c6cb; font-weight: bold; }
        form { display: flex; flex-direction: column; gap: 15px; }
        form label { font-weight: bold; text-align: left; }
        form input[type="text"], form input[type="email"], form input[type="password"] { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        form input[type="submit"] { background-color: #8B4513; color: white; border: none; padding: 12px; border-radius: 4px; cursor: pointer; font-size: 16px; transition: background-color 0.2s; }
        form input[type="submit"]:hover { background-color: #5D4037; }
        .login-link { margin-top: 15px; font-size: 0.9em; text-align: center; }
        .login-link a { color: #8B4513; text-decoration: none; font-weight: bold; }
    </style>
</head>
<body>
<div class="container">
    <h2>Customer Registration</h2>
    <?php if (!empty($error)): ?>
        <p class="message-error"><?php echo $error; ?></p>
    <?php endif; ?>
    
    <form action="" method="post">
        <label for="username">Name:</label>
        <input type="text" name="username" id="username" required>

        <label for="email">Email:</label>
        <input type="email" name="email" id="email" required>
        
        <label for="password">Password:</label>
        <input type="password" name="password" id="password" required>
        
        <label for="confirm_password">Confirm Password:</label>
        <input type="password" name="confirm_password" id="confirm_password" required>
        
        <label for="contact_number">Contact Number (Optional):</label>
        <input type="text" name="contact_number" id="contact_number">
        
        <input type="submit" value="Register">
    </form>
    
    <p class="login-link">Already have an account? <a href="customer_login.php">Login here.</a></p>
</div>
</body>
</html>