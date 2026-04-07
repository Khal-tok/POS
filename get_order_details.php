<?php
include 'db.php';

if(isset($_POST['order_id'])) {
    $order_id = intval($_POST['order_id']); // Security: Force integer
    
    // Fetch items + Product Name + Image
    $sql = "SELECT ooi.*, p.name, p.image_path 
            FROM online_order_items ooi 
            JOIN products p ON ooi.product_id = p.id 
            WHERE ooi.order_id = $order_id";
            
    $res = mysqli_query($conn, $sql);
    
    if(mysqli_num_rows($res) > 0) {
        echo '<table style="width:100%; border-collapse:collapse; font-family: Segoe UI, sans-serif;">';
        echo '<tr style="background:#f4f4f4; text-align:left; border-bottom:1px solid #ddd;">
                <th style="padding:10px; color:#4E342E;">Image</th>
                <th style="padding:10px; color:#4E342E;">Item</th>
                <th style="padding:10px; color:#4E342E;">Details</th>
                <th style="padding:10px; color:#4E342E;">Subtotal</th>
              </tr>';
        
        while($row = mysqli_fetch_assoc($res)) {
            // Handle Image path
            $img_filename = !empty($row['image_path']) ? $row['image_path'] : 'placeholder.png';
            $img_src = "product_images/" . $img_filename;
            
            // Calculate item subtotal
            $subtotal = $row['price'] * $row['quantity'];
            
            // Fix undefined keys by using defaults
            $size = isset($row['size']) ? $row['size'] : 'Standard';
            $temp = isset($row['temp']) ? $row['temp'] : 'N/A';
            
            // Formatting Add-ons
            $addons = "";
            if(!empty($row['addons_summary'])) {
                $addons = '<br><span style="color:#e65100; font-size:0.9em;">+ '.$row['addons_summary'].'</span>';
            }

            echo '<tr>';
            // 1. IMAGE
            echo '<td style="padding:10px; border-bottom:1px solid #eee;">
                    <img src="'.$img_src.'" style="width:60px; height:60px; object-fit:cover; border-radius:5px; border:1px solid #ccc;">
                  </td>';
            
            // 2. NAME
            echo '<td style="padding:10px; border-bottom:1px solid #eee;">
                    <strong style="color:#8B4513; font-size:1.1em;">'.htmlspecialchars($row['name']).'</strong>
                  </td>';
            
            // 3. DETAILS
            echo '<td style="padding:10px; border-bottom:1px solid #eee; font-size:0.9em; color:#555;">'
                 . 'Qty: <b>' . $row['quantity'] . '</b><br>'
                 . $size . ' | ' . $temp 
                 . $addons 
                 . '</td>';
            
            // 4. PRICE
            echo '<td style="padding:10px; border-bottom:1px solid #eee; font-weight:bold; color:#2E7D32;">
                    ₱' . number_format($subtotal, 2) . '
                  </td>';
            echo '</tr>';
        }
        echo '</table>';
    } else {
        echo '<p style="text-align:center; padding:20px;">No items found for this order.</p>';
    }
}
?>