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
$oils = json_decode($invoice['oils'] ?? '[]', true);
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
                    <?php
                    $tech_name = '';
                    if (!empty($invoice['technician_id'])){
                        $stmt = $pdo->prepare('SELECT name FROM technicians WHERE id = ? LIMIT 1');
                        $stmt->execute([(int)$invoice['technician_id']]);
                        $tch = $stmt->fetch(); if ($tch) $tech_name = $tch['name'];
                    }
                    ?>
                    <p><strong>Technician:</strong> <?php echo htmlspecialchars($invoice['technician'] ?: $tech_name); ?></p>
                    <p><strong>Customer:</strong> <?php echo htmlspecialchars($invoice['customer_name']); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($invoice['phone']); ?></p>
                    <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'manager'])): ?>
                        <p><strong>FINA Status:</strong>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo !empty($invoice['opened_in_fina']) ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                                <?php echo !empty($invoice['opened_in_fina']) ? 'Opened in FINA' : 'Not Opened'; ?>
                            </span>
                        </p>
                    <?php endif; ?>
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
                            <td class="border border-gray-300 px-2 md:px-4 py-2 truncate max-w-[200px]">
                                <?php echo htmlspecialchars($item['name']); ?>
                                <?php if (!empty($item['db_id'])): ?>
                                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs bg-blue-100 text-blue-800">DB: <?php echo strtoupper(htmlspecialchars($item['db_type'] ?? '')); ?></span>
                                    <?php if (!empty($item['db_vehicle'])): ?>
                                        <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs bg-gray-100 text-gray-700"><?php echo htmlspecialchars($item['db_vehicle']); ?></span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td class="border border-gray-300 px-2 md:px-4 py-2 text-center"><?php echo $item['qty']; ?></td>
                            <td class="border border-gray-300 px-2 md:px-4 py-2 text-right"><?php echo $item['price_part']; ?></td>
                            <td class="border border-gray-300 px-2 md:px-4 py-2 text-right"><?php echo $item['price_svc']; ?></td>
                            <td class="border border-gray-300 px-2 md:px-4 py-2 truncate max-w-[120px]"><?php echo htmlspecialchars($item['tech']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if (!empty($oils)): ?>
            <div class="overflow-x-auto mt-6">
                <h3 class="text-lg font-semibold mb-2">Oils</h3>
                <table class="w-full border-collapse border border-gray-300 mb-4 min-w-[500px] text-sm">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="border border-gray-300 px-2 md:px-4 py-2">Brand</th>
                            <th class="border border-gray-300 px-2 md:px-4 py-2">Viscosity</th>
                            <th class="border border-gray-300 px-2 md:px-4 py-2">Package</th>
                            <th class="border border-gray-300 px-2 md:px-4 py-2">Qty</th>
                            <th class="border border-gray-300 px-2 md:px-4 py-2">Price</th>
                            <th class="border border-gray-300 px-2 md:px-4 py-2">Discount</th>
                            <th class="border border-gray-300 px-2 md:px-4 py-2">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($oils as $oil): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="border border-gray-300 px-2 md:px-4 py-2"><?php echo htmlspecialchars($oil['brand']); ?></td>
                            <td class="border border-gray-300 px-2 md:px-4 py-2"><?php echo htmlspecialchars($oil['viscosity']); ?></td>
                            <td class="border border-gray-300 px-2 md:px-4 py-2"><?php echo htmlspecialchars($oil['package']); ?></td>
                            <td class="border border-gray-300 px-2 md:px-4 py-2 text-center"><?php echo $oil['qty']; ?></td>
                            <td class="border border-gray-300 px-2 md:px-4 py-2 text-right"><?php echo number_format($oil['price'], 2); ?> ₾</td>
                            <td class="border border-gray-300 px-2 md:px-4 py-2 text-center"><?php echo $oil['discount']; ?>%</td>
                            <td class="border border-gray-300 px-2 md:px-4 py-2 text-right"><?php echo number_format($oil['qty'] * $oil['price'] * (1 - $oil['discount'] / 100), 2); ?> ₾</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <div class="text-right">
                <p><strong>Parts Total:</strong> <?php echo $invoice['parts_total']; ?> ₾</p>
                <p><strong>Service Total:</strong> <?php echo $invoice['service_total']; ?> ₾</p>
                <p class="text-xl font-bold"><strong>Grand Total:</strong> <?php echo $invoice['grand_total']; ?> ₾</p>
                <p class="mt-4"><a href="index.php?print_id=<?php echo $invoice['id']; ?>" target="_blank" class="px-3 py-2 bg-yellow-400 text-slate-900 rounded">Print Invoice</a></p>

                <?php if (!empty($invoice['images'])): ?>
                    <?php $imgs = json_decode($invoice['images'], true) ?: []; ?>
                    <?php if ($imgs): ?>
                        <div class="mt-4">
                            <h3 class="font-semibold mb-2 flex items-center justify-between">
                                Photos
                                <?php if (count($imgs) > 0): ?>
                                    <span class="text-sm text-gray-500"><?php echo count($imgs); ?> image<?php echo count($imgs) !== 1 ? 's' : ''; ?></span>
                                <?php endif; ?>
                            </h3>
                            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                                <?php foreach ($imgs as $index => $img): ?>
                                    <div class="relative group">
                                        <a href="<?php echo htmlspecialchars($img); ?>" target="_blank" class="block">
                                            <img src="<?php echo htmlspecialchars($img); ?>" class="w-full h-24 object-cover rounded-lg border border-gray-200 hover:border-blue-300 transition-colors" />
                                        </a>
                                        <?php if (isset($_SESSION['user_id'])): ?>
                                            <button type="button"
                                                    onclick="deleteImage(<?php echo $invoice['id']; ?>, <?php echo $index; ?>, this)"
                                                    class="absolute top-1 right-1 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs opacity-0 group-hover:opacity-100 hover:bg-red-600 transition-all"
                                                    title="Delete image">
                                                ×
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function deleteImage(invoiceId, imageIndex, buttonElement) {
            if (!confirm('Are you sure you want to delete this image?')) {
                return;
            }

            // Disable button during request
            buttonElement.disabled = true;
            buttonElement.textContent = '...';

            fetch('delete_image.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    invoice_id: invoiceId,
                    image_index: imageIndex
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove the image container
                    buttonElement.closest('.relative').remove();
                    // Update counter if it exists
                    const counter = document.querySelector('h3 span');
                    if (counter) {
                        const currentCount = parseInt(counter.textContent);
                        if (currentCount > 1) {
                            counter.textContent = `${currentCount - 1} images`;
                        } else {
                            // Hide the entire photos section if no images left
                            const photosSection = buttonElement.closest('.mt-4');
                            if (photosSection) {
                                photosSection.style.display = 'none';
                            }
                        }
                    }
                } else {
                    alert('Failed to delete image: ' + (data.error || 'Unknown error'));
                    buttonElement.disabled = false;
                    buttonElement.textContent = '×';
                }
            })
            .catch(error => {
                alert('Error deleting image: ' + error.message);
                buttonElement.disabled = false;
                buttonElement.textContent = '×';
            });
        }
    </script>

    <script>
        // Mark invoice as seen when viewed (for live updates)
        (function() {
            const invoiceId = <?php echo (int)$invoice['id']; ?>;

            // Notify parent window (if opened in popup) or mark as seen locally
            if (window.opener && window.opener.__liveInvoices) {
                // If opened in popup, notify parent
                window.opener.__liveInvoices.markAsSeen && window.opener.__liveInvoices.markAsSeen(invoiceId);
            } else if (window.parent && window.parent !== window && window.parent.__liveInvoices) {
                // If in iframe, notify parent
                window.parent.__liveInvoices.markAsSeen && window.parent.__liveInvoices.markAsSeen(invoiceId);
            }

            // Also store in sessionStorage for cross-tab communication
            try {
                const seenInvoices = JSON.parse(sessionStorage.getItem('seen_invoices') || '[]');
                if (!seenInvoices.includes(invoiceId)) {
                    seenInvoices.push(invoiceId);
                    sessionStorage.setItem('seen_invoices', JSON.stringify(seenInvoices));
                }
            } catch (e) {
                console.warn('Could not update seen invoices in sessionStorage', e);
            }

            // Update DB
            fetch('../update_is_new.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'invoice_id=' + invoiceId
            }).then(response => response.json()).then(data => {
                if (!data.success) {
                    console.warn('Failed to update is_new:', data.error);
                }
            }).catch(e => console.warn('Error updating is_new:', e));
        })();
    </script>



</body>
</html>