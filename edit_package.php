<?php
require 'config.php';
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $result = $conn->query("SELECT * FROM packages WHERE id=$id");
    $package = $result->fetch_assoc();
}

if (isset($_POST['update'])) {
    $package_name = $_POST['package_name'];
    $price = $_POST['price'];
    $conn->query("UPDATE packages SET package_name='$package_name', price='$price' WHERE id=$id");
    header("Location: packages.php");
}
?>

<form method="post">
    <input type="text" name="package_name" value="<?= $package['package_name'] ?>" required>
    <input type="number" name="price" value="<?= $package['price'] ?>" required>
    <button type="submit" name="update">Update Package</button>
</form>
