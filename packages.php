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
    <hr>
    <!-- Font Awesome & Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">

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
            margin: 20px auto;
            padding: 20px;
            text-align: center;
        }

        h1 {
            color: #2c3e50;
            font-size: 2.5em;
            margin-bottom: 30px;
        }

        .packages-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            padding: 20px;
        }

        .package-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .package-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .package-card h2 {
            color: #2c3e50;
            margin-bottom: 15px;
            font-size: 1.8em;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }

        .price {
            font-size: 1.8em;
            color: #2c3e50;
            margin: 20px 0;
            font-weight: bold;
        }

        .btn {
            display: inline-block;
            padding: 12px;
            width: 100%;
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

        .btn1 {
            display: block;
            width: 100%;
            padding: 12px;
            background: rgb(22, 172, 8);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 1.1em;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .btn1:hover {
            background: rgb(212, 143, 13);
        }

        /* Payment Modal */
        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }

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
            width: 320px;
            text-align: center;
            z-index: 1001;
            animation: fadeIn 0.3s ease-in-out;
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

        .processing-message {
            display: none;
            color: green;
            font-weight: bold;
            margin-top: 15px;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translate(-50%, -55%); }
            to { opacity: 1; transform: translate(-50%, -50%); }
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

  <!-- Payment Modal -->
  <div class="overlay" id="overlay"></div>
    <div id="payment-form">
        <button class="close-btn" onclick="closePaymentForm()">X</button>
        <h2>Payment Details</h2>
        <hr>
        <p id="selected-package"></p>
        <p id="selected-price"></p>
        <form id="mpesa-form">
            <div class="form-group">
                <label>Phone Number (254XXXXXXXXX)</label>
                <input type="text" id="phone" required>
            </div>
            <button type="submit" class="btn1">
                <i class="fas fa-mobile-alt"></i> Pay with M-Pesa <i class="bi bi-phone"></i>
            </button>
        </form>
        <p id="processing-message" class="processing-message">Processing Payment... Enter M-Pesa PIN to complete transaction.</p>
    </div>
<script>
    function purchasePackage(packageId, packageName, packagePrice) {
        document.getElementById('payment-form').style.display = 'block';
        sessionStorage.setItem('selected_package', packageId);
        sessionStorage.setItem('selected_price', packagePrice);
        document.getElementById('selected-package').textContent = "Package: " + packageName;
        document.getElementById('selected-price').textContent = "Price: KSH " + packagePrice;
    }

    function closePaymentForm() {
        document.getElementById('payment-form').style.display = 'none';
        document.getElementById('processing-message').style.display = 'none';
    }

    document.getElementById('mpesa-form').addEventListener('submit', function(e) {
        e.preventDefault();

        const phone = document.getElementById('phone').value.trim();
        const packageId = sessionStorage.getItem('selected_package');
        const packagePrice = sessionStorage.getItem('selected_price');
        const processingMessage = document.getElementById('processing-message');

        if (!phone || !packageId || !packagePrice) {
            alert('Please enter all details before proceeding.');
            return;
        }

        processingMessage.style.display = 'block';
        
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
            processingMessage.style.display = 'none';
            if (data.success) {
                alert('Please check your phone to complete the payment.');
                closePaymentForm();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            processingMessage.style.display = 'none';
            alert('Error processing payment: ' + error.message);
        });
    });
</script>
</body>
</html>
