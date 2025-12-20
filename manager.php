<?php
require 'config.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'manager'])) {
    header('Location: login.php');
    exit;
}

// Fetch invoices
$stmt = $pdo->query("SELECT * FROM invoices ORDER BY created_at DESC");
$invoices = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manager Panel - Auto Shop</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <nav class="bg-green-600 text-white p-4">
        <div class="container mx-auto flex justify-between">
            <h1 class="text-xl font-bold">Manager Panel</h1>
            <div>
                <a href="index.php" class="mr-4">Invoice Generator</a>
                <a href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mx-auto p-6">
        <h2 class="text-2xl font-bold mb-6">Invoice Management</h2>

        <table class="bg-white rounded-lg shadow-md w-full">
            <thead>
                <tr class="bg-gray-200">
                    <th class="px-4 py-2">ID</th>
                    <th class="px-4 py-2">Customer</th>
                    <th class="px-4 py-2">Car</th>
                    <th class="px-4 py-2">Total</th>
                    <th class="px-4 py-2">Created At</th>
                    <th class="px-4 py-2">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invoices as $invoice): ?>
                <tr>
                    <td class="px-4 py-2"><?php echo $invoice['id']; ?></td>
                    <td class="px-4 py-2"><?php echo htmlspecialchars($invoice['customer_name']); ?></td>
                    <td class="px-4 py-2"><?php echo htmlspecialchars($invoice['car_mark']); ?></td>
                    <td class="px-4 py-2"><?php echo $invoice['grand_total']; ?> ₾</td>
                    <td class="px-4 py-2"><?php echo $invoice['created_at']; ?></td>
                    <td class="px-4 py-2">
                        <a href="view_invoice.php?id=<?php echo $invoice['id']; ?>" class="text-blue-500 hover:underline">View</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>