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
<body class="bg-gray-100 min-h-screen font-sans antialiased">
    <?php include 'partials/sidebar.php'; ?>
    <div class="container mx-auto ml-0 md:ml-64 px-4 md:px-6 py-4 md:py-6">
        <!-- Mobile-friendly header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4 gap-3">
            <a href="manager.php" class="text-blue-500 hover:underline flex items-center gap-2 text-sm md:text-base">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
                Back to Manager Panel
            </a>
            <div class="text-right">
                <h1 class="text-xl md:text-2xl font-bold text-gray-800">Invoice #<?php echo $invoice['id']; ?></h1>
                <p class="text-sm text-gray-600"><?php echo date('M j, Y g:i A', strtotime($invoice['creation_date'])); ?></p>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <!-- Invoice Header -->
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 text-white p-4 md:p-6">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                    <div>
                        <h2 class="text-xl md:text-2xl font-bold mb-2">Invoice Details</h2>
                        <p class="text-blue-100">Created on <?php echo date('F j, Y \a\t g:i A', strtotime($invoice['creation_date'])); ?></p>
                    </div>
                    <?php if (!empty($invoice['opened_in_fina'])): ?>
                        <div class="bg-green-500 text-white px-3 py-1 rounded-full text-sm font-medium">
                            ✓ Opened in FINA
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Invoice Details Grid -->
            <div class="p-4 md:p-6">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <!-- Customer & Service Info -->
                    <div class="space-y-3">
                        <h3 class="text-lg font-semibold text-gray-800 border-b border-gray-200 pb-2">Customer Information</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <p><span class="font-medium text-gray-600">Customer:</span><br><span class="text-gray-800"><?php echo htmlspecialchars($invoice['customer_name']); ?></span></p>
                                <p><span class="font-medium text-gray-600">Phone:</span><br><span class="text-gray-800"><?php echo htmlspecialchars($invoice['phone']); ?></span></p>
                            </div>
                            <div class="space-y-2">
                                <p><span class="font-medium text-gray-600">Service Manager:</span><br><span class="text-gray-800"><?php echo htmlspecialchars($invoice['service_manager']); ?></span></p>
                                <?php if (!empty($invoice['technician'])): ?>
                                    <p><span class="font-medium text-gray-600">Technician:</span><br><span class="text-gray-800"><?php echo htmlspecialchars($invoice['technician']); ?></span></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Vehicle Info -->
                    <div class="space-y-3">
                        <h3 class="text-lg font-semibold text-gray-800 border-b border-gray-200 pb-2">Vehicle Information</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <p><span class="font-medium text-gray-600">Make/Model:</span><br><span class="text-gray-800"><?php echo htmlspecialchars($invoice['car_mark']); ?></span></p>
                                <p><span class="font-medium text-gray-600">Plate Number:</span><br><span class="text-gray-800"><?php echo htmlspecialchars($invoice['plate_number']); ?></span></p>
                            </div>
                            <div class="space-y-2">
                                <?php if (!empty($invoice['vin'])): ?>
                                    <p><span class="font-medium text-gray-600">VIN:</span><br><span class="text-gray-800 font-mono text-sm"><?php echo htmlspecialchars($invoice['vin']); ?></span></p>
                                <?php endif; ?>
                                <p><span class="font-medium text-gray-600">Mileage:</span><br><span class="text-gray-800"><?php echo htmlspecialchars($invoice['mileage']); ?></span></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Items Table -->
                <div class="mb-6">
                    <h3 class="text-lg font-semibold text-gray-800 border-b border-gray-200 pb-2 mb-4">Service Items</h3>
                    <div class="overflow-x-auto -mx-4 md:mx-0">
                        <div class="inline-block min-w-full align-middle">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 md:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item Description</th>
                                        <th class="px-3 md:px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Qty</th>
                                        <th class="px-3 md:px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Part Price</th>
                                        <th class="px-3 md:px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Service Price</th>
                                        <th class="px-3 md:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Technician</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($items as $item): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-3 md:px-6 py-4">
                                            <div class="flex flex-col">
                                                <span class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($item['name']); ?></span>
                                                <div class="flex flex-wrap gap-1 mt-1">
                                                    <?php if (!empty($item['type'])): ?>
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-yellow-100 text-yellow-800"><?php echo ucfirst(htmlspecialchars($item['type'])); ?></span>
                                                    <?php endif; ?>

                                                    <?php if (!empty($item['db_id'])): ?>
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-blue-100 text-blue-800">DB: <?php echo strtoupper(htmlspecialchars($item['db_type'] ?? '')); ?></span>
                                                        <?php if (!empty($item['db_vehicle'])): ?>
                                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-gray-100 text-gray-700"><?php echo htmlspecialchars($item['db_vehicle']); ?></span>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-3 md:px-6 py-4 text-center text-sm text-gray-900"><?php echo number_format($item['qty'], 2); ?></td>
                                        <td class="px-3 md:px-6 py-4 text-right text-sm text-gray-900"><?php echo number_format($item['price_part'], 2); ?> ₾</td>
                                        <td class="px-3 md:px-6 py-4 text-right text-sm text-gray-900"><?php echo number_format($item['price_svc'], 2); ?> ₾</td>
                                        <td class="px-3 md:px-6 py-4 text-left text-sm text-gray-900"><?php echo htmlspecialchars($item['tech'] ?? ''); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Totals Section -->
                <div class="bg-gray-50 rounded-lg p-4 md:p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Invoice Summary</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
                        <div class="bg-white p-3 rounded-md border border-gray-200">
                            <p class="text-sm text-gray-600">Parts Total</p>
                            <p class="text-xl font-bold text-gray-900"><?php echo number_format($invoice['parts_total'], 2); ?> ₾</p>
                        </div>
                        <div class="bg-white p-3 rounded-md border border-gray-200">
                            <p class="text-sm text-gray-600">Service Total</p>
                            <p class="text-xl font-bold text-gray-900"><?php echo number_format($invoice['service_total'], 2); ?> ₾</p>
                        </div>
                        <div class="bg-white p-3 rounded-md border border-gray-200">
                            <p class="text-sm text-gray-600">Subtotal</p>
                            <p class="text-xl font-bold text-gray-900"><?php echo number_format($invoice['parts_total'] + $invoice['service_total'], 2); ?> ₾</p>
                        </div>
                        <div class="bg-gradient-to-r from-green-500 to-green-600 p-3 rounded-md text-white">
                            <p class="text-sm opacity-90">Grand Total</p>
                            <p class="text-xl font-bold"><?php echo number_format($invoice['grand_total'], 2); ?> ₾</p>
                        </div>
                    </div>

                    <!-- Print Button -->
                    <div class="flex justify-center">
                        <a href="create.php?print_id=<?php echo $invoice['id']; ?>" target="_blank" class="inline-flex items-center px-6 py-3 bg-yellow-500 hover:bg-yellow-600 text-slate-900 font-medium rounded-lg transition-colors">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                            </svg>
                            Print Invoice
                        </a>
                    </div>
                </div>

                <!-- Photos Section -->
                <?php if (!empty($invoice['images'])): ?>
                    <?php $imgs = json_decode($invoice['images'], true) ?: []; ?>
                    <?php if ($imgs): ?>
                        <div class="mt-6">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-semibold text-gray-800">Photos</h3>
                                <span class="text-sm text-gray-500 bg-gray-100 px-2 py-1 rounded-full"><?php echo count($imgs); ?> image<?php echo count($imgs) !== 1 ? 's' : ''; ?></span>
                            </div>
                            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
                                <?php foreach ($imgs as $index => $img): ?>
                                    <div class="relative group bg-gray-100 rounded-lg overflow-hidden">
                                        <a href="<?php echo htmlspecialchars($img); ?>" target="_blank" class="block aspect-square">
                                            <img src="<?php echo htmlspecialchars($img); ?>" class="w-full h-full object-cover hover:scale-105 transition-transform duration-200" alt="Invoice photo <?php echo $index + 1; ?>" />
                                        </a>
                                        <?php if (isset($_SESSION['user_id'])): ?>
                                            <button type="button"
                                                    onclick="deleteImage(<?php echo $invoice['id']; ?>, <?php echo $index; ?>, this)"
                                                    class="absolute top-2 right-2 bg-red-500 text-white rounded-full w-8 h-8 flex items-center justify-center text-sm opacity-0 group-hover:opacity-100 hover:bg-red-600 transition-all shadow-lg"
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
                    const imageContainer = buttonElement.closest('.relative');
                    imageContainer.remove();

                    // Update counter if it exists
                    const counter = document.querySelector('h3 span');
                    if (counter) {
                        const currentCount = parseInt(counter.textContent);
                        if (currentCount > 1) {
                            counter.textContent = `${currentCount - 1} images`;
                        } else {
                            // Hide the entire photos section if no images left
                            const photosSection = document.querySelector('.mt-6');
                            if (photosSection) {
                                photosSection.style.display = 'none';
                            }
                        }
                    }

                    // Show success message
                    showToast('Image deleted successfully', 'success');
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

        function showToast(message, type = 'info') {
            // Remove existing toast
            const existingToast = document.getElementById('toast');
            if (existingToast) {
                existingToast.remove();
            }

            // Create new toast
            const toast = document.createElement('div');
            toast.id = 'toast';
            toast.className = `fixed bottom-4 right-4 px-4 py-2 rounded-lg text-white text-sm font-medium shadow-lg z-50 transition-all duration-300 transform translate-y-full`;

            // Set color based on type
            if (type === 'success') {
                toast.classList.add('bg-green-500');
            } else if (type === 'error') {
                toast.classList.add('bg-red-500');
            } else {
                toast.classList.add('bg-blue-500');
            }

            toast.textContent = message;
            document.body.appendChild(toast);

            // Show toast
            setTimeout(() => {
                toast.classList.remove('translate-y-full');
            }, 100);

            // Hide toast after 3 seconds
            setTimeout(() => {
                toast.classList.add('translate-y-full');
                setTimeout(() => {
                    toast.remove();
                }, 300);
            }, 3000);
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