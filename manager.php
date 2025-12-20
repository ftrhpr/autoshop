<?php
require 'config.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'manager'])) {
    header('Location: login.php');
    exit;
}

// Handle delete invoice
if (isset($_POST['delete_invoice'])) {
    $id = (int)$_POST['id'];
    $stmt = $pdo->prepare('DELETE FROM invoices WHERE id=?');
    $stmt->execute([$id]);

    $stmt = $pdo->prepare('INSERT INTO audit_logs (user_id, action, details, ip) VALUES (?, ?, ?, ?)');
    $stmt->execute([$_SESSION['user_id'], 'delete_invoice', "id={$id}", $_SERVER['REMOTE_ADDR'] ?? '']);

    $success = 'Invoice deleted successfully';
}

// Fetch invoices
$stmt = $pdo->query("SELECT * FROM invoices ORDER BY created_at DESC");
$invoices = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Panel - Auto Shop</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen overflow-x-hidden font-sans antialiased">
    <?php include 'partials/sidebar.php'; ?>

    <div class="container mx-auto p-4 md:p-6 ml-0 md:ml-64">
        <h2 class="text-2xl font-bold mb-6">Invoice Management</h2>

        <?php if (isset($success)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <div class="overflow-x-auto">
            <table class="bg-white rounded-lg shadow-md w-full min-w-[600px] text-sm">
                <thead>
                    <tr class="bg-gray-200">
                        <th class="px-2 md:px-4 py-2 text-left">ID</th>
                        <th class="px-2 md:px-4 py-2 text-left">Customer</th>
                        <th class="px-2 md:px-4 py-2 text-left">Car</th>
                        <th class="px-2 md:px-4 py-2 text-right">Total</th>
                        <th class="px-2 md:px-4 py-2 text-left">Created At</th>
                        <th class="px-2 md:px-4 py-2 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoices as $invoice): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-2 md:px-4 py-2"><?php echo $invoice['id']; ?></td>
                        <td class="px-2 md:px-4 py-2 truncate max-w-[150px]"><?php echo htmlspecialchars($invoice['customer_name']); ?></td>
                        <td class="px-2 md:px-4 py-2 truncate max-w-[120px]"><?php echo htmlspecialchars($invoice['car_mark']); ?></td>
                        <td class="px-2 md:px-4 py-2 text-right"><?php echo $invoice['grand_total']; ?> ₾</td>
                        <td class="px-2 md:px-4 py-2 truncate max-w-[140px]"><?php echo $invoice['created_at']; ?></td>
                        <td class="px-2 md:px-4 py-2 text-center">
                            <a href="view_invoice.php?id=<?php echo $invoice['id']; ?>" class="text-blue-500 hover:underline mr-2 text-xs md:text-sm">View</a>
                            <a href="print_invoice.php?id=<?php echo $invoice['id']; ?>" target="_blank" class="text-green-600 hover:underline mr-2 text-xs md:text-sm">Print</a>
                            <form method="post" style="display:inline-block" onsubmit="return confirm('Are you sure you want to delete this invoice?');">
                                <input type="hidden" name="id" value="<?php echo $invoice['id']; ?>">
                                <button type="submit" name="delete_invoice" class="text-red-600 hover:underline text-xs md:text-sm">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>