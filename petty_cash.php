<?php
session_start();
include 'db.php';

// Check if user is logged in and is a Barista
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] != 'barista') {
    header("location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$message = "";
$error = "";

// === 1. Handle Form Submission (Add Petty Cash Entry) ===
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_petty_cash'])) {
    $date = trim($_POST['date']);
    $type = $_POST['type'];
    $amount = filter_var($_POST['amount'], FILTER_VALIDATE_FLOAT);
    $description = trim($_POST['description']);

    if (empty($date) || empty($type) || $amount === false || $amount <= 0 || empty($description)) {
        $error = "Please fill in all fields correctly.";
    } else {
        $sql = "INSERT INTO petty_cash (user_id, date, type, amount, description) VALUES (?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "issds", $user_id, $date, $type, $amount, $description);

        if (mysqli_stmt_execute($stmt)) {
            $message = "Petty cash entry recorded successfully.";
            // Clear POST data to prevent re-submission on refresh
            $_POST = array(); 
        } else {
            $error = "Error recording entry: " . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    }
}

// === 2. Fetch Data for Display ===

// A. Calculate Current Balance (The original query that was failing)
$sql_balance = "
    SELECT 
        IFNULL(SUM(CASE WHEN type = 'In' THEN amount ELSE 0 END), 0) - 
        IFNULL(SUM(CASE WHEN type = 'Out' THEN amount ELSE 0 END), 0) AS current_balance
    FROM petty_cash";
$result_balance = mysqli_query($conn, $sql_balance);
$current_balance = mysqli_fetch_assoc($result_balance)['current_balance'] ?? 0;

// B. Fetch Transaction History
$sql_history = "SELECT * FROM petty_cash ORDER BY recorded_at DESC";
$result_history = mysqli_query($conn, $sql_history);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Petty Cash Management</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #F8F4EF; color: #4E342E; margin: 0; padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1); }
        h2 { color: #4E342E; border-bottom: 2px solid #E0E0E0; padding-bottom: 10px; margin-top: 0; margin-bottom: 25px; }
        h3 { color: #8B4513; border-bottom: 1px solid #F0F0F0; padding-bottom: 5px; margin-top: 25px; margin-bottom: 15px; }
        .balance-box { background: #EFEBE9; padding: 15px; border-radius: 5px; text-align: center; margin-bottom: 20px; }
        .balance-box h4 { margin: 0 0 5px 0; color: #5D4037; }
        .balance-amount { font-size: 2.5em; font-weight: bold; color: #4CAF50; } /* Green for positive, could use PHP for dynamic color */
        
        .form-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .form-grid label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-grid input[type="date"], 
        .form-grid select, 
        .form-grid input[type="number"],
        .form-grid input[type="text"] { 
            width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; 
        }
        .form-grid input[type="submit"] {
             background-color: #8B4513; color: white; padding: 10px 15px; border: none; border-radius: 5px; cursor: pointer; transition: background-color 0.2s; 
        }
        .form-grid input[type="submit"]:hover { background-color: #5D4037; }

        .message-success { background-color: #E8F5E9; color: #4CAF50; padding: 10px; margin: 10px 0; border-radius: 5px; }
        .message-error { background-color: #FFEBEE; color: #E57373; padding: 10px; margin: 10px 0; border-radius: 5px; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        table th, table td { padding: 10px; text-align: left; border-bottom: 1px solid #E0E0E0; }
        table th { background-color: #F5F5F5; color: #4E342E; font-weight: bold; }
        .type-in { color: green; font-weight: bold; }
        .type-out { color: red; font-weight: bold; }
        .back-link { color: #8B4513; text-decoration: none; font-weight: bold; margin-bottom: 20px; display: inline-block; }
    </style>
</head>
<body>
<div class="container">
    <a href="barista_dashboard.php" class="back-link">← Back to Dashboard</a>
    <h2>Petty Cash Management</h2>

    <?php if (!empty($message)): ?>
        <p class="message-success"><?php echo $message; ?></p>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <p class="message-error"><?php echo $error; ?></p>
    <?php endif; ?>

    <div class="balance-box">
        <h4>Current Petty Cash Balance</h4>
        <p class="balance-amount" style="color: <?php echo ($current_balance < 0) ? 'red' : '#4CAF50'; ?>;">
            ₱ <?php echo number_format($current_balance, 2); ?>
        </p>
    </div>

    <h3>Add New Entry</h3>
    <form action="petty_cash.php" method="post">
        <div class="form-grid">
            <div class="input-group">
                <label for="date">Date:</label>
                <input type="date" name="date" id="date" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
            
            <div class="input-group">
                <label for="type">Type:</label>
                <select name="type" id="type" required>
                    <option value="In">Cash In</option>
                    <option value="Out">Cash Out</option>
                </select>
            </div>
            
            <div class="input-group">
                <label for="amount">Amount:</label>
                <input type="number" step="0.01" name="amount" id="amount" min="0.01" required>
            </div>
            
            <div style="grid-column: 1 / 3;" class="input-group">
                <label for="description">Description:</label>
                <input type="text" name="description" id="description" required>
            </div>
            
            <div style="grid-column: 3 / 4; align-self: end;">
                <input type="submit" name="submit_petty_cash" value="Record Entry">
            </div>
        </div>
    </form>

    <h3>Transaction History</h3>
    <?php if (mysqli_num_rows($result_history) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Description</th>
                    <th>Recorded By</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                // Fetch usernames for display
                $users_result = mysqli_query($conn, "SELECT id, username FROM users");
                $users_map = [];
                while($user = mysqli_fetch_assoc($users_result)) {
                    $users_map[$user['id']] = $user['username'];
                }

                while($entry = mysqli_fetch_assoc($result_history)): 
                ?>
                <tr>
                    <td><?php echo date('Y-m-d H:i', strtotime($entry['recorded_at'])); ?></td>
                    <td class="<?php echo ($entry['type'] == 'In') ? 'type-in' : 'type-out'; ?>">
                        <?php echo $entry['type']; ?>
                    </td>
                    <td>₱ <?php echo number_format($entry['amount'], 2); ?></td>
                    <td><?php echo htmlspecialchars($entry['description']); ?></td>
                    <td><?php echo htmlspecialchars($users_map[$entry['user_id']] ?? 'Unknown'); ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No petty cash entries have been recorded yet.</p>
    <?php endif; ?>
</div>
</body>
</html>