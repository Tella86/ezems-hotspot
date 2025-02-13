<?php
// Database connection
class Config {
    const DB_HOST = 'localhost';
    const DB_USER = 'root';
    const DB_PASS = 'Elon2508/*-';
    const DB_NAME = 'internet_service';
}

// Function to get all active packages
function getActivePackages() {
    try {
        $db = new PDO("mysql:host=" . Config::DB_HOST . ";dbname=" . Config::DB_NAME, Config::DB_USER, Config::DB_PASS);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $query = "SELECT * FROM packages WHERE is_active = 1 ORDER BY price ASC";
        $stmt = $db->query($query);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database Error: " . $e->getMessage());
        return false;
    }
}

$packages = getActivePackages();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Internet Packages</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            background-color: #f4f4f4;
            color: #333;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        h1 {
            text-align: center;
            color: #2c3e50;
            margin-bottom: 30px;
            font-size: 2.5em;
        }
        
        .packages-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            padding: 20px 0;
        }
        
        .package-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        
        .package-card:hover {
            transform: translateY(-5px);
        }
        
        .package-card h2 {
            color: #2c3e50;
            margin-bottom: 15px;
            font-size: 1.8em;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }
        
        .package-details {
            margin: 15px 0;
        }
        
        .package-details p {
            margin: 10px 0;
            font-size: 1.1em;
            color: #555;
        }
        
        .price {
            font-size: 1.8em;
            color: #2c3e50;
            margin: 20px 0;
            font-weight: bold;
        }
        
        .btn {
            display: block;
            width: 100%;
            padding: 12px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 1.1em;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        
        .btn:hover {
            background: #2980b9;
        }
        
        .duration {
            color: #7f8c8d;
            font-size: 0.9em;
            margin-top: 10px;
            text-align: center;
        }

        /* Payment Modal */
        #payment-form {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 20px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            border-radius: 10px;
            width: 300px;
            text-align: center;
        }

        .close-btn {
            background: red;
            color: white;
            border: none;
            padding: 5px 10px;
            cursor: pointer;
            border-radius: 5px;
            float: right;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Internet Packages</h1>
        
        <div class="packages-grid">
            <?php foreach($packages as $package): ?>
                <div class="package-card">
                    <h2><?= htmlspecialchars($package['name']) ?></h2>
                    <div class="package-details">
                        <p>Speed: <?= htmlspecialchars($package['speed_mbps']) ?> Mbps</p>
                        <p>Data: <?= htmlspecialchars($package['data_limit_gb']) ?> GB</p>
                        <div class="price">KSH <?= number_format($package['price'], 2) ?></div>
                        <div class="duration">Duration: <?= floor($package['duration_hours']/24) ?> days</div>
                    </div>
                    <button class="btn" onclick="purchasePackage(<?= $package['id'] ?>, '<?= htmlspecialchars($package['name']) ?>', <?= $package['price'] ?>)">
                        Purchase Now
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

  <!-- Payment Form (Popup) -->
<div id="payment-form" style="display: none;">
    <button class="close-btn" onclick="closePaymentForm()">X</button>
    <h2>Payment Details</h2>
    <p id="selected-package"></p>
    <p id="selected-price"></p>
    <form id="mpesa-form">
        <div class="form-group">
            <label>Phone Number (254XXXXXXXXX)</label>
            <input type="text" id="phone" required>
        </div>
        <button type="submit" class="btn">Pay with M-Pesa</button>
    </form>
</div>

<script>
    function purchasePackage(packageId, packageName, packagePrice) {
        // Show payment modal
        document.getElementById('payment-form').style.display = 'block';
        
        // Store package details
        sessionStorage.setItem('selected_package', packageId);
        sessionStorage.setItem('selected_price', packagePrice);
        
        // Display package details
        document.getElementById('selected-package').textContent = "Package: " + packageName;
        document.getElementById('selected-price').textContent = "Price: KSH " + packagePrice;
    }

    function closePaymentForm() {
        document.getElementById('payment-form').style.display = 'none';
    }

    document.getElementById('mpesa-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const phone = document.getElementById('phone').value.trim();
        const packageId = sessionStorage.getItem('selected_package');
        const packagePrice = sessionStorage.getItem('selected_price');

        if (!phone || !packageId || !packagePrice) {
            alert('Please enter all details before proceeding.');
            return;
        }

        fetch('mpesa_payment.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                phone: phone,
                package_id: packageId,
                package_price: packagePrice
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Please check your phone to complete the payment.');
                closePaymentForm();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            alert('Error processing payment: ' + error.message);
        });
    });
</script>

</body>
</html>
