<?php
session_start();
include 'db.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $sql = "SELECT id, username, password, role FROM users 
            WHERE username = ? AND (role = 'admin' OR role = 'barista' OR role = 'rider')"; 
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($row) {
        if (password_verify($password, $row['password'])) {
            $_SESSION['loggedin'] = true;
            
            // WE USE BOTH FOR COMPATIBILITY ACROSS ALL YOUR MODULES
            $_SESSION['id'] = $row['id']; 
            $_SESSION['user_id'] = $row['id']; 
            
            $_SESSION['username'] = $row['username'];
            $_SESSION['role'] = $row['role'];

            if ($row['role'] == 'admin') {
                header("location: admin_dashboard.php");
            } elseif ($row['role'] == 'barista') {
                header("location: barista_dashboard.php");
            } elseif ($row['role'] == 'rider') {
                header("location: delivery_module.php"); 
            }
            exit;
        } else {
            $error = "Invalid username or password.";
        }
    } else {
        $sql_check_customer = "SELECT id FROM users WHERE username = ? AND role = 'customer'";
        $stmt_check = mysqli_prepare($conn, $sql_check_customer);
        mysqli_stmt_bind_param($stmt_check, "s", $username);
        mysqli_stmt_execute($stmt_check);
        $result_check = mysqli_stmt_get_result($stmt_check);
        
        if (mysqli_num_rows($result_check) > 0) {
             $error = "You are a customer. Please login via the customer portal.";
        } else {
             $error = "Invalid username or password.";
        }
        mysqli_stmt_close($stmt_check);
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>POS System Login</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #8B4513; 
            color: #4E342E;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }

        .container {
            width: 90%;
            max-width: 400px;
            background: #F8F4EF; 
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
            text-align: center;
        }

        .logo-header img {
            max-width: 150px;
            margin-bottom: 20px;
        }

        h2 {
            font-size: 1.5em;
            margin-bottom: 25px;
            border-bottom: 1px solid #ccc;
            padding-bottom: 10px;
        }
        
        form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        input[type="text"], 
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 5px;
            box-sizing: border-box; 
            font-size: 16px;
        }

        input[type="submit"] {
            width: 100%;
            padding: 12px;
            background-color: #8B4513; 
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
        }

        input[type="submit"]:hover {
            background-color: #5D4037;
        }
        
        .error-message {
            color: #cc0000;
            font-weight: bold;
            margin-top: 15px;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="logo-header">
        <img src="hosana.png" alt="Hosana Cafe Logo"> 
    </div>
    <h2>POS System wehhhhh Login</h2>
    
    <form action="" method="post">
        <label for="username" style="text-align: left; font-weight: bold;">Username:</label>
        <input type="text" id="username" name="username" required>
        
        <label for="password" style="text-align: left; font-weight: bold;">Password:</label>
        <input type="password" id="password" name="password" required>
        
        <input type="submit" value="Login">
    </form>
    
    <?php if (!empty($error)) { echo "<p class='error-message'>{$error}</p>"; } ?>
</div>
</body>
</html>