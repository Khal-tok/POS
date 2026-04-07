<?php
// This page is highly stripped down for fast loading and printing

// 1. Get and decode the comment text
$comment = $_GET['comment'] ?? 'No Message';
$comment_text = htmlspecialchars(urldecode($comment));

// Wrap lines for better readability on narrow receipt printers
$wrapped_comment = wordwrap($comment_text, 30, "\n", true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barista Comment Print</title>
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
        .header {
            font-size: 14px;
            font-weight: bold;
            color: #4e342e;
            margin-bottom: 5px;
            border-bottom: 2px solid #ccc;
            padding-bottom: 5px;
        }
        .message {
            white-space: pre-wrap; /* Preserve line breaks from wordwrap */
            font-size: 16px;
            font-weight: bold;
            margin-top: 10px;
            text-align: left;
        }
        .timestamp {
            font-size: 10px;
            color: #555;
            margin-top: 10px;
            text-align: center;
        }
        @media print {
            body { margin: 0; padding: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="label-container">
        <div class="header">BARISTA NOTE</div>
        
        <div class="message">
            <?php echo $wrapped_comment; ?>
        </div>

        <div class="timestamp">
            Printed: <?php echo date('H:i:s'); ?>
        </div>
    </div>
    <div class="no-print" style="margin-top: 20px;">
        <p>Label printed. You may close this window.</p>
    </div>
</body>
</html>