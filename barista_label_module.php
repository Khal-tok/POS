<?php
session_start();
include 'db.php'; 

// 1. Authorization
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] != 'barista') {
    header("location: login.php");
    exit;
}

// --- Fetch Pending Orders for Barista Labeling Module ---
$pending_orders = [];

// CRITICAL FIX: Only select columns that are guaranteed to exist. Removed o.cup_design and o.notes.
$pending_res = mysqli_query($conn, "SELECT o.id AS order_id, o.total_amount, u.username, 
                                     DATE_FORMAT(o.order_date, '%H:%i:%s') AS order_time_display 
                                     FROM online_orders o 
                                     JOIN users u ON o.user_id = u.id
                                     WHERE o.status IN ('Pending', 'Awaiting Payment') 
                                     ORDER BY o.order_date ASC");

// Fallback check: uses created_at if order_date fails.
if (!$pending_res) {
    $pending_res = mysqli_query($conn, "SELECT o.id AS order_id, o.total_amount, u.username, 
                                         DATE_FORMAT(o.created_at, '%H:%i:%s') AS order_time_display 
                                         FROM online_orders o 
                                         JOIN users u ON o.user_id = u.id
                                         WHERE o.status IN ('Pending', 'Awaiting Payment') 
                                         ORDER BY o.created_at ASC");
}

if ($pending_res) {
    while($order = mysqli_fetch_assoc($pending_res)) {
        // Hardcoded value for display since column does not exist in DB
        $order['cup_design'] = "Standard"; 

        $order['items'] = [];
        $items_res = mysqli_query($conn, "SELECT id AS order_item_id, product_id, quantity, size, temp, ice_level, price 
                                          FROM online_order_items 
                                          WHERE order_id = " . $order['order_id']);
        
        while($item = mysqli_fetch_assoc($items_res)) {
            $product_name_res = mysqli_query($conn, "SELECT name FROM products WHERE id = " . $item['product_id']);
            $product_name = mysqli_fetch_assoc($product_name_res);
            $item['product_name'] = $product_name['name'] ?? 'Unknown Product';

            $order['items'][] = $item;
        }
        $pending_orders[] = $order;
    }
}
// --- End: Labeling Module PHP Logic ---
?>

<!DOCTYPE html>
<html>
<head>
    <title>Barista Labeling Module</title>
    <link rel="stylesheet" href="barista_dashboard.css">
    <style>
        /* Shared Styles for Module */
        .module-container { 
            max-width: 1000px; 
            margin: 30px auto;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .header-bar { background: #4e342e; color: white; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; }
        .user-badge { background: #6d4c41; padding: 5px 10px; border-radius: 5px; font-weight: bold; }
        .top-nav { background: #f8f8f8; padding: 10px 30px; border-bottom: 1px solid #eee; }
        .nav-btn { text-decoration: none; color: #4e342e; padding: 8px 15px; margin-right: 10px; border-radius: 4px; font-weight: 600; }
        .nav-btn:hover { background: #eee; }
        .btn-red { background: #dc3545; color: white; }
        
        /* Order Table */
        .order-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 0.9em;
        }
        .order-table th, .order-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
            vertical-align: top;
        }
        .order-table th {
            background-color: #4e342e;
            color: white;
        }
        .order-items-list { list-style: none; padding: 0; margin: 0; }
        .order-items-list li { padding: 5px 0; font-size: 0.9em; border-bottom: 1px dashed #eee; }
        .order-items-list li:last-child { border-bottom: none; }
        .btn-label-print {
            background: #2E7D32; 
            color: white;
            border: none;
            padding: 8px 10px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            margin-top: 5px;
            display: block; 
            width: 100%;
            text-align: center;
        }

        /* Comment Box */
        .comment-module {
            background: #f8f8f8;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-top: 20px;
        }
        #commentBox {
            width: 100%;
            min-height: 80px;
            padding: 10px;
            box-sizing: border-box;
            border: 1px solid #ccc;
            border-radius: 4px;
            margin-bottom: 10px;
        }
        .btn-comment-print {
            background: #4e342e;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            float: right;
        }
    </style>
</head>
<body>
    <div class="header-bar">
        <h2>Barista Labeling & Comment Module</h2>
        <div class="user-badge">
            <?php echo htmlspecialchars($_SESSION['username']); ?> 
            <span class="role-icon">B</span>
        </div>
    </div>
    <div class="top-nav">
        <a href="barista_dashboard.php" class="nav-btn">← Back to POS</a>
        <a href="barista_OOH.php" class="nav-btn">Online History</a> 
        <a href="barista_TH.php" class="nav-btn">POS History</a> 
        <a href="petty_cash.php" class="nav-btn">Petty Cash</a> 
        <a href="logout.php" class="nav-btn btn-red">Logout</a>
    </div>

    <div class="module-container">
        <h2>☕ Online Orders Awaiting Preparation</h2>
        <?php if (empty($pending_orders)): ?>
            <p style="padding: 20px; background: #ffe; border-left: 5px solid orange;">No pending online orders to prepare right now. Great job!</p>
        <?php else: ?>
            <table class="order-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer / Design</th>
                        <th>Order Time</th>
                        <th>Items & Details</th>
                        <th>Print Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($pending_orders as $order): ?>
                    <tr>
                        <td>#<?php echo $order['order_id']; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($order['username']); ?></strong>
                            <div style="font-size: 0.9em; margin-top: 5px;">
                                Sticker: <b><?php echo htmlspecialchars($order['cup_design']); ?></b>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars($order['order_time_display']); ?></td>
                        <td>
                            <ul class="order-items-list">
                                <?php foreach($order['items'] as $item): 
                                    $details = htmlspecialchars($item['size'] . " | " . $item['temp'] . ($item['ice_level'] != 'N/A' ? " | Ice: " . $item['ice_level'] : ""));
                                ?>
                                    <li>
                                        **<?php echo htmlspecialchars($item['product_name']); ?>** (x<?php echo $item['quantity']; ?>)
                                        <br><small style="color: #666;"><?php echo $details; ?></small>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </td>
                        <td>
                            <?php foreach($order['items'] as $item): 
                                $item_details_string = urlencode(
                                    $item['product_name'] . ";" . 
                                    $item['size'] . ";" . 
                                    $item['temp'] . ";" . 
                                    $item['ice_level'] . ";" . 
                                    $order['username'] . ";" . 
                                    $order['order_id'] . ";" . 
                                    $item['order_item_id'] . ";" .
                                    "Standard" // Hardcoded 'Standard' since DB column doesn't exist
                                );
                            ?>
                                <button 
                                    class="btn-label-print" 
                                    onclick="printCupLabel('<?php echo $item_details_string; ?>')"
                                >
                                    Print Label (<?php echo htmlspecialchars($item['product_name']); ?>)
                                </button>
                                <br>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <div class="comment-module">
            <h3>📝 Print Barista Comment/Note</h3>
            <textarea id="commentBox" placeholder="Enter quick notes, special instructions, or internal messages here..."></textarea>
            <button class="btn-comment-print" onclick="printCommentLabel()">
                Print Comment Label
            </button>
            <div style="clear: both;"></div>
        </div>

    </div>

<script>
    // Function to handle the print button click (ORDER LABEL)
    function printCupLabel(itemString) {
        const url = 'print_cup_label.php?data=' + itemString;
        window.open(url, '_blank', 'width=400,height=600'); 
    }

    // Function to handle the printing of the comment box content
    function printCommentLabel() {
        const commentBox = document.getElementById('commentBox');
        let commentText = commentBox.value.trim();

        if (commentText === "") {
            alert("Please enter a message or note before printing.");
            return;
        }

        const encodedComment = encodeURIComponent(commentText);
        const url = 'print_comment_label.php?comment=' + encodedComment;
        
        window.open(url, '_blank', 'width=400,height=600'); 
        
        // Clear the box after printing (optional)
        commentBox.value = "";
    }
</script>
</body>
</html>