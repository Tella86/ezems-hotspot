<?php
require 'config.php';
// require 'db_connection.php';
// session_start();

// // Ensure only admins can access
// if (!isset($_SESSION['admin_logged_in'])) {
//     header("Location: login.php");
//     exit();
// }

try {
    // Fetch Stats with Prepared Statements
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM users WHERE status = ?");
    $stmt->bind_param("s", $status);
    
    $status = 'active';
    $stmt->execute();
    $result = $stmt->get_result();
    $activeUsers = $result->fetch_assoc()['total'] ?? 0;
    $stmt->close();

    $stmt = $conn->prepare("SELECT SUM(amount) AS total FROM payments");
    $stmt->execute();
    $result = $stmt->get_result();
    $totalRevenue = $result->fetch_assoc()['total'] ?? 0;
    $stmt->close();

    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM packages");
    $stmt->execute();
    $result = $stmt->get_result();
    $activePackages = $result->fetch_assoc()['total'] ?? 0;
    $stmt->close();

    $stmt = $conn->prepare("SELECT SUM(amount) AS total FROM payments WHERE DATE(created_at) = CURDATE()");
    $stmt->execute();
    $result = $stmt->get_result();
    $todaySales = $result->fetch_assoc()['total'] ?? 0;
    $stmt->close();

    // Fetch Packages
    $packages = $conn->query("SELECT * FROM packages");

} catch (Exception $e) {
    die("Error fetching data: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Internet Service Management</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex">
        <!-- Sidebar -->
        <div class="w-64 bg-gray-800 text-white">
            <div class="p-4">
                <h1 class="text-xl font-bold">Admin Dashboard</h1>
            </div>
            <nav class="mt-4">
                <?php foreach (getNavItems() as $name => $url): ?>
                    <a href="<?= htmlspecialchars($url) ?>" class="block py-2 px-4 hover:bg-gray-700"><?= htmlspecialchars($name) ?></a>
                <?php endforeach; ?>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="flex-1 p-8">
            <div class="grid grid-cols-4 gap-4 mb-8">
                <!-- Stats Cards -->
                <div class="bg-white p-4 rounded-lg shadow">
                    <h3 class="text-gray-500">Active Users</h3>
                    <p class="text-2xl font-bold"><?= number_format($activeUsers) ?></p>
                </div>
                <div class="bg-white p-4 rounded-lg shadow">
                    <h3 class="text-gray-500">Total Revenue</h3>
                    <p class="text-2xl font-bold">Ksh <?= number_format($totalRevenue, 2) ?></p>
                </div>
                <div class="bg-white p-4 rounded-lg shadow">
                    <h3 class="text-gray-500">Active Packages</h3>
                    <p class="text-2xl font-bold"><?= number_format($activePackages) ?></p>
                </div>
                <div class="bg-white p-4 rounded-lg shadow">
                    <h3 class="text-gray-500">Today's Sales</h3>
                    <p class="text-2xl font-bold">Ksh <?= number_format($todaySales, 2) ?></p>
                </div>
            </div>

            <!-- Package Management -->
            <div class="bg-white rounded-lg shadow p-6 mb-8">
                <h2 class="text-xl font-bold mb-4">Package Management</h2>
                <table class="w-full border-collapse border border-gray-200">
                    <thead>
                        <tr class="bg-gray-200">
                            <th class="text-left py-2 px-4 border border-gray-300">Name</th>
                            <th class="text-left py-2 px-4 border border-gray-300">Speed</th>
                            <th class="text-left py-2 px-4 border border-gray-300">Data Limit</th>
                            <th class="text-left py-2 px-4 border border-gray-300">Price</th>
                            <th class="text-left py-2 px-4 border border-gray-300">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $packages->fetch_assoc()) : ?>
                        <tr class="border-b">
                            <td class="py-2 px-4 border border-gray-300"><?= htmlspecialchars($row['package_name']) ?></td>
                            <td class="py-2 px-4 border border-gray-300"><?= htmlspecialchars($row['speed']) ?> Mbps</td>
                            <td class="py-2 px-4 border border-gray-300"><?= htmlspecialchars($row['data_limit']) ?> GB</td>
                            <td class="py-2 px-4 border border-gray-300">Ksh <?= number_format($row['price'], 2) ?></td>
                            <td class="py-2 px-4 border border-gray-300">
                                <a href="edit_package.php?id=<?= (int) $row['id'] ?>" class="text-blue-500">Edit</a> | 
                                <a href="#" onclick="deletePackage(<?= (int) $row['id'] ?>)" class="text-red-500">Delete</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function deletePackage(id) {
            Swal.fire({
                title: "Are you sure?",
                text: "You won't be able to revert this!",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#d33",
                cancelButtonColor: "#3085d6",
                confirmButtonText: "Yes, delete it!"
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = "delete_package.php?id=" + id;
                }
            });
        }
    </script>
</body>
</html>
