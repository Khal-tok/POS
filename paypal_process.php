<?php
session_start();
// Disable error printing to prevent breaking JSON response
ini_set('display_errors', 0);
error_reporting(E_ALL);

include 'db.php';

// Set header to JSON
header('Content-Type: application/json');

try {
    // Get the raw POST data
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($_SESSION['loggedin'])) {
        throw new Exception("User not logged in.");
    }

    if (!$conn) {
        throw new Exception("Database connection failed.");
    }

    // 1. Get User ID
    $user_id = 0;
    $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ?");
    mysqli_stmt_bind_param($stmt, "s", $_SESSION['username']);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if($r = mysqli_fetch_assoc($res)) { $user_id = $r['id']; }
    mysqli_stmt_close($stmt);

    if ($user_id == 0) {
        throw new Exception("User account not found.");
    }

    // 2. Extract Data
    $address = isset($input['address']) ? $input['address'] : '';
    $contact = isset($input['contact']) ? $input['contact'] : '';
    $raw_notes = isset($input['notes']) ? $input['notes'] : '';
    $amount = $input['amount'];
    
    // --- CONFIRMATION LOGIC ---
    $paypal_ref = isset($input['orderID']) ? $input['orderID'] : 'Unknown';
    // Append PayPal Reference to notes for admin verification
    $final_notes = $raw_notes . " [PAID via PayPal. Ref: " . $paypal_ref . "]";

    // 3. Update User Contact
    $stmt_c = mysqli_prepare($conn, "UPDATE users SET contact_number = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt_c, "si", $contact, $user_id);
    mysqli_stmt_execute($stmt_c);
    mysqli_stmt_close($stmt_c);

    // 4. Insert Order (Status: Pending, Payment: PayPal)
    $sql = "INSERT INTO online_orders (user_id, total_amount, delivery_address, customer_notes, payment_method, status, order_date) VALUES (?, ?, ?, ?, 'PayPal', 'Pending', NOW())";
    $stmt_order = mysqli_prepare($conn, $sql);
    
    if (!$stmt_order) {
        throw new Exception("DB Error (Orders): " . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($stmt_order, "idss", $user_id, $amount, $address, $final_notes);

    if(mysqli_stmt_execute($stmt_order)) {
        $order_id = mysqli_insert_id($conn);
        
        // 5. Insert Items
        $sql_items = "INSERT INTO online_order_items (order_id, product_id, quantity, price, size, temp, ice_level) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt_items = mysqli_prepare($conn, $sql_items);
        
        if (!$stmt_items) {
            throw new Exception("DB Error (Items): " . mysqli_error($conn));
        }
        
        foreach($_SESSION['online_cart'] as $item) {
            $pid = isset($item['id']) ? $item['id'] : (isset($item['product_id']) ? $item['product_id'] : 0);
            $qty = isset($item['qty']) ? $item['qty'] : (isset($item['quantity']) ? $item['quantity'] : 1);
            
            $size = isset($item['size']) ? $item['size'] : 'Standard';
            $temp = isset($item['temp']) ? $item['temp'] : 'N/A';
            $ice  = isset($item['ice'])  ? $item['ice']  : 'N/A';
            $price = $item['price'];

            mysqli_stmt_bind_param($stmt_items, "iiidsss", $order_id, $pid, $qty, $price, $size, $temp, $ice);
            mysqli_stmt_execute($stmt_items);
        }
        mysqli_stmt_close($stmt_items);
        
        // Clear Cart
        $_SESSION['online_cart'] = []; 
        
        // === SEND SUCCESS + ORDER ID ===
        echo json_encode(['success' => true, 'order_id' => $order_id]);

    } else {
        throw new Exception("Error executing order insert: " . mysqli_stmt_error($stmt_order));
    }
    mysqli_stmt_close($stmt_order);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>