<?php
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: manager.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ?");
$stmt->execute([$id]);
$invoice = $stmt->fetch();

if (!$invoice) {
    die('Invoice not found');
}

$items = json_decode($invoice['items'], true);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Invoice - Auto Shop</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-6">
    <?php include 'partials/sidebar.php'; ?>
    <div class="container mx-auto ml-0 md:ml-64">
        <a href="manager.php" class="text-blue-500 hover:underline mb-4 inline-block">&larr; Back to Manager Panel</a>

        <div class="bg-white p-6 rounded-lg shadow-md">
            <h2 class="text-2xl font-bold mb-4">Invoice #<?php echo $invoice['id']; ?></h2>

            <div class="grid grid-cols-2 gap-4 mb-6">
                <div>
                    <p><strong>Creation Date:</strong> <?php echo $invoice['creation_date']; ?></p>
                    <?php if (!empty($invoice['service_manager_id'])) {
                        $stmt = $pdo->prepare('SELECT username FROM users WHERE id = ? LIMIT 1');
                        $stmt->execute([(int)$invoice['service_manager_id']]);
                        $smu = $stmt->fetch();
                    }
                    ?>
                    <p><strong>Service Manager:</strong> <?php echo htmlspecialchars($invoice['service_manager']); ?><?php echo !empty($smu['username']) ? ' ('.$smu['username'].')' : ''; ?></p>
                    <p><strong>Customer:</strong> <?php echo htmlspecialchars($invoice['customer_name']); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($invoice['phone']); ?></p>
                </div>
                <div>
                    <p><strong>Car:</strong> <?php echo htmlspecialchars($invoice['car_mark']); ?></p>
                    <p><strong>Plate:</strong> <?php echo htmlspecialchars($invoice['plate_number']); ?></p>
                    <p><strong>Mileage:</strong> <?php echo htmlspecialchars($invoice['mileage']); ?></p>
                </div>
            </div>

            <table class="w-full border-collapse border border-gray-300 mb-4">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="border border-gray-300 px-4 py-2">Item</th>
                        <th class="border border-gray-300 px-4 py-2">Qty</th>
                        <th class="border border-gray-300 px-4 py-2">Part Price</th>
                        <th class="border border-gray-300 px-4 py-2">Service Price</th>
                        <th class="border border-gray-300 px-4 py-2">Technician</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td class="border border-gray-300 px-4 py-2"><?php echo htmlspecialchars($item['name']); ?></td>
                        <td class="border border-gray-300 px-4 py-2"><?php echo $item['qty']; ?></td>
                        <td class="border border-gray-300 px-4 py-2"><?php echo $item['price_part']; ?></td>
                        <td class="border border-gray-300 px-4 py-2"><?php echo $item['price_svc']; ?></td>
                        <td class="border border-gray-300 px-4 py-2"><?php echo htmlspecialchars($item['tech']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="text-right">
                <p><strong>Parts Total:</strong> <?php echo $invoice['parts_total']; ?> ₾</p>
                <p><strong>Service Total:</strong> <?php echo $invoice['service_total']; ?> ₾</p>
                <p class="text-xl font-bold"><strong>Grand Total:</strong> <?php echo $invoice['grand_total']; ?> ₾</p>
                <p class="mt-4"><a href="print_invoice.php?id=<?php echo $invoice['id']; ?>" target="_blank" class="px-3 py-2 bg-yellow-400 text-slate-900 rounded">Print Invoice</a></p>
            </div>
        </div>
    </div>
</body>
</html>