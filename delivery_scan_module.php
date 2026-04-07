<?php
session_start();

// Authorization Check
if (!isset($_SESSION['loggedin']) || ($_SESSION['role'] != 'rider' && $_SESSION['role'] != 'admin')) {
    header("location: login.php");
    exit;
}

$manual_order_id = isset($_GET['order_id']) ? $_GET['order_id'] : '';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Delivery Scanner</title>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <style>
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #f4f7f6; padding: 20px; text-align: center; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        h2 { color: #1a237e; }
        
        #reader {
            width: 100%;
            margin: 0 auto;
            border: 5px solid #1a237e;
            border-radius: 10px;
        }
        
        .manual-box {
            background: #e3f2fd;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
            border: 1px solid #90caf9;
        }
        
        input[type="number"] { padding: 10px; width: 60%; font-size: 16px; border: 1px solid #ccc; border-radius: 4px; }
        input[type="submit"] { padding: 10px 20px; font-size: 16px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; }
    </style>
</head>
<body>
<div class="container">
    <h2>📷 Scan Customer QR</h2>
    
    <p><a href="delivery_module.php" style="text-decoration: none; color: #1565c0;">← Back to Dashboard</a></p>

    <div id="reader"></div>
    <p id="scan-status" style="font-weight:bold; margin-top:10px; color:#555;">Waiting for camera permission...</p>

    <hr>
    
    <div class="manual-box">
        <h3>Manual Entry</h3>
        <p>Scanner not working? Enter Order ID below:</p>
        <form action="delivery_scan.php" method="get">
            <input type="number" name="order_id" value="<?php echo $manual_order_id; ?>" placeholder="Enter Order ID" required>
            <br><br>
            <input type="submit" value="Proceed to Confirmation">
        </form>
    </div>
</div>

<script>
    function onScanSuccess(decodedText, decodedResult) {
        document.getElementById('scan-status').innerText = "✅ Scanned! Processing...";
        
        // --- THE FIX IS HERE ---
        let finalOrderId = decodedText;

        // Check if the scanned text is a URL containing "order_id="
        if (decodedText.includes("order_id=")) {
            // Split the text to extract only the number after "order_id="
            let parts = decodedText.split("order_id=");
            // Get the part after the "=" and ensure it's just the number (parseInt)
            finalOrderId = parseInt(parts[1]);
        }

        // If scanning failed to get a number (e.g., weird text), alert user
        if (isNaN(finalOrderId)) {
            alert("Error: Could not read Order ID from QR Code.");
            document.getElementById('scan-status').innerText = "❌ Invalid QR Code";
            return;
        }

        // Redirect using ONLY the number
        window.location.href = "delivery_scan.php?order_id=" + finalOrderId;
    }

    function onScanFailure(error) {
        // Handle scan failure silently
    }

    let html5QrcodeScanner = new Html5QrcodeScanner(
        "reader", 
        { fps: 10, qrbox: {width: 250, height: 250} }, 
        false
    );
    html5QrcodeScanner.render(onScanSuccess, onScanFailure);
</script>
</body>
</html>