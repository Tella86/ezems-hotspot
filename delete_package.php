<?php
require 'config.php';
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $conn->query("DELETE FROM packages WHERE id=$id");
    header("Location: packages.php");
}
?>
