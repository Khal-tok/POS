<?php
session_start();
include 'db.php'; 

$username = isset($_GET['user']) ? $_GET['user'] : (isset($_POST['username']) ? $_POST['username'] : '');
$error = "";
$message = "";

// Ensure correct timezone is set
date_default_timezone_set('Asia/Manila'); 

if ($_SERVER["REQUEST_METHOD"] == "POST" && !empty($username)) {
    $otp_input = $_POST['otp_code'];

    // 1. Fetch user ID and Verification Code (NOT reset_token)
    // We strictly look for the verification_code column used in registration
    $sql_fetch = "SELECT id, verification_code FROM users WHERE username = ? AND role = 'customer'";
    $stmt_fetch = mysqli_prepare($conn, $sql_fetch);
    mysqli_stmt_bind_param($stmt_fetch, "s", $username);
    mysqli_stmt_execute($stmt_fetch);
    $result_fetch = mysqli_stmt_get_result($stmt_fetch);
    $user = mysqli_fetch_assoc($result_fetch);
    mysqli_stmt_close($stmt_fetch);

    if ($user) {
        $db_otp = $user['verification_code'];

        // 2. Check if OTP matches
        // (We removed the expiry check here because registration OTPs usually stay valid until used)
        if ($otp_input === $db_otp) {
            
            $user_id = $user['id'];
            
            // 3. Mark verified and clear the OTP
            $sql_verify = "UPDATE users SET is_verified = 1, verification_code = NULL WHERE id = ?";
            $stmt_verify = mysqli_prepare($conn, $sql_verify);
            mysqli_stmt_bind_param($stmt_verify, "i", $user_id);
            
            if (mysqli_stmt_execute($stmt_verify)) {
                // Log the user in and redirect to dashboard
                $_SESSION['loggedin'] = true;
                $_SESSION['username'] = $username;
                $_SESSION['role'] = 'customer';
                
                header("Location: customer_dashboard.php");
                exit;
            } else {
                $error = "Verification failed due to a database error.";
            }
            mysqli_stmt_close($stmt_verify);

        } else {
            $error = "Invalid verification code entered. Please try again.";
        }
    } else {
        $error = "User not found.";
    }
} elseif (empty($username)) {
    $error = "Access denied. Please register first.";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Verify Account</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* (Matching CSS styles) */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #F8F4EF; 
            color: #4E342E; 
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }

        .container {
            width: 90%;
            max-width: 400px;
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
            text-align: center;
        }

        .logo-header {
            margin-bottom: 20px; 
            padding-bottom: 10px;
        }
        .logo-header img {
            max-width: 70%; 
            height: auto;
            display: block;
            margin: 0 auto; 
        }

        h2 {
            color: #8B4513; 
            margin-bottom: 25px;
            padding-bottom: 10px;
        }
        
        form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        input[type="text"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 5px;
            box-sizing: border-box; 
            font-size: 20px;
            letter-spacing: 2px;
            font-weight: bold;
            text-align: center; /* Center the input code */
        }
        
        .form-group {
            text-align: left;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        input[type="submit"] {
            width: 100%;
            padding: 12px;
            background-color: #66BB6A; 
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
            margin-top: 10px;
        }

        input[type="submit"]:hover {
            background-color: #4CAF50;
        }
        
        p a {
            color: #8B4513;
            text-decoration: none;
            font-weight: bold;
        }

        p a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

<div class="container">
    
    <div class="logo-header">
        <img src="hosana.png" alt="Hosana Cafe Logo">
    </div>
    
    <h2>Verify Your Account</h2>

    <?php if (!empty($error)): ?>
        <p style="color:red;"><?php echo $error; ?></p>
        <?php if (strpos($error, 'expired') !== false): ?>
            <p><a href="customer_register.php">Click here to register and get a new code.</a></p>
        <?php endif; ?>

    <?php else: ?>
        <p>A 6-digit verification code has been sent to the email associated with <strong><?php echo htmlspecialchars($username); ?></strong>. Please enter the code below.</p>
        
        <form action="" method="post">
            <input type="hidden" name="username" value="<?php echo htmlspecialchars($username); ?>">
            
            <div class="form-group">
                <label for="otp_code">Verification Code:</label>
                <input type="text" id="otp_code" name="otp_code" maxlength="6" required autofocus placeholder="123456">
            </div>
            
            <input type="submit" value="Verify Account">
        </form>
    <?php endif; ?>
    
    <p style="margin-top: 20px;"><a href="customer_login.php">Back to Login</a></p>
</div>

</body>
</html>