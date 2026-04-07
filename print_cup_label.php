<?php
// This page is highly stripped down for fast loading and printing

// 1. Get and decode the data string
$data_string = $_GET['data'] ?? '';

if (empty($data_string)) {
    die("Error: No label data provided.");
}

$data = explode(';', urldecode($data_string));

// Assign variables based on the structure defined in barista_dashboard.php (8 total elements now)
$product_name = htmlspecialchars($data[0] ?? 'N/A');
$size = htmlspecialchars($data[1] ?? 'N/A');
$temp = htmlspecialchars($data[2] ?? 'N/A');
$ice_level = htmlspecialchars($data[3] ?? 'N/A');
$customer_name = htmlspecialchars($data[4] ?? 'N/A');
$order_id = htmlspecialchars($data[5] ?? 'N/A');
$order_item_id = htmlspecialchars($data[6] ?? 'N/A'); 
$cup_design = htmlspecialchars($data[7] ?? 'Standard'); // Design is passed as the 8th piece of data

// Format ice detail
$ice_detail = ($temp === 'Cold' && $ice_level != 'N/A') ? " ({$ice_level})" : "";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cup Label Print</title>
    <style>
        /* Minimalist style optimized for small receipt/label printers */
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            text-align: center;
        }
        .label-container {
            width: 100%;
            height: auto;
            padding: 10px;
            box-sizing: border-box;
        }
        .main-item {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 5px;
            text-transform: uppercase;
        }
        .details {
            font-size: 14px;
            margin-bottom: 2px;
        }
        .design {
            font-size: 12px;
            font-weight: bold;
            color: #4e342e; /* Highlight the design */
            margin-top: 5px;
            padding: 5px;
            border: 1px dashed #4e342e;
        }
        .order-info {
            font-size: 10px;
            color: #555;
            margin-top: 10px;
            border-top: 1px dashed #ccc;
            padding-top: 5px;
        }
        @media print {
            body { margin: 0; padding: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="label-container">
        <div class="main-item"><?php echo $product_name; ?></div>
        
        <div class="details">
            <?php echo $size; ?> | <?php echo $temp; ?> <?php echo $ice_detail; ?>
        </div>
        
        <div class="design">
            STENCIL: <?php echo $cup_design; ?>
        </div>

        <div class="order-info">
            Order #<?php echo $order_id; ?><br>
            Cust: <?php echo $customer_name; ?>
        </div>
    </div>
    <div class="no-print" style="margin-top: 20px;">
        <p>Label printed. You may close this window.</p>
    </div>
</body>
</html>