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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Invoice - Auto Shop</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-4 md:p-6 min-h-screen overflow-x-hidden font-sans antialiased">
    <?php include 'partials/sidebar.php'; ?>
    <div class="container mx-auto ml-0 md:ml-64">
        <a href="manager.php" class="text-blue-500 hover:underline mb-4 inline-block">&larr; Back to Manager Panel</a>

        <div class="bg-white p-4 md:p-6 rounded-lg shadow-md">
            <h2 class="text-2xl font-bold mb-4">Invoice #<?php echo $invoice['id']; ?></h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
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
                    <p><strong>VIN:</strong> <?php echo htmlspecialchars($invoice['vin'] ?? ''); ?></p>
                    <p><strong>Mileage:</strong> <?php echo htmlspecialchars($invoice['mileage']); ?></p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full border-collapse border border-gray-300 mb-4 min-w-[500px] text-sm">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="border border-gray-300 px-2 md:px-4 py-2">Item</th>
                            <th class="border border-gray-300 px-2 md:px-4 py-2">Qty</th>
                            <th class="border border-gray-300 px-2 md:px-4 py-2">Part Price</th>
                            <th class="border border-gray-300 px-2 md:px-4 py-2">Service Price</th>
                            <th class="border border-gray-300 px-2 md:px-4 py-2">Technician</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="border border-gray-300 px-2 md:px-4 py-2 truncate max-w-[200px]"><?php echo htmlspecialchars($item['name']); ?></td>
                            <td class="border border-gray-300 px-2 md:px-4 py-2 text-center"><?php echo $item['qty']; ?></td>
                            <td class="border border-gray-300 px-2 md:px-4 py-2 text-right"><?php echo $item['price_part']; ?></td>
                            <td class="border border-gray-300 px-2 md:px-4 py-2 text-right"><?php echo $item['price_svc']; ?></td>
                            <td class="border border-gray-300 px-2 md:px-4 py-2 truncate max-w-[120px]"><?php echo htmlspecialchars($item['tech']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="text-right">
                <p><strong>Parts Total:</strong> <?php echo $invoice['parts_total']; ?> ₾</p>
                <p><strong>Service Total:</strong> <?php echo $invoice['service_total']; ?> ₾</p>
                <p class="text-xl font-bold"><strong>Grand Total:</strong> <?php echo $invoice['grand_total']; ?> ₾</p>
                <p class="mt-4"><a href="index.php?print_id=<?php echo $invoice['id']; ?>" target="_blank" class="px-3 py-2 bg-yellow-400 text-slate-900 rounded">Print Invoice</a></p>

                <?php if (!empty($invoice['images'])): ?>
                    <?php $imgs = json_decode($invoice['images'], true) ?: []; ?>
                    <?php if ($imgs): ?>
                        <div class="mt-4">
                            <h3 class="font-semibold mb-2">Photos</h3>
                            <div class="flex gap-2 flex-wrap">
                                <?php foreach ($imgs as $img): ?>
                                    <a href="<?php echo htmlspecialchars($img); ?>" target="_blank"><img src="<?php echo htmlspecialchars($img); ?>" class="w-32 h-auto object-cover rounded border" /></a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>