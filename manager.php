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

// Build filters from GET params (search form)
$filters = [];
$params = [];

$q = trim($_GET['q'] ?? '');
if ($q !== '') {
    $filters[] = '(i.customer_name LIKE ? OR i.plate_number LIKE ? OR i.vin LIKE ? OR i.customer_name LIKE ?)';
    $params[] = "%$q%";
    $params[] = "%$q%";
    $params[] = "%$q%";
    $params[] = "%$q%";
}
$plate = trim($_GET['plate'] ?? '');
if ($plate !== '') {
    $filters[] = 'i.plate_number LIKE ?';
    $params[] = "%$plate%";
}
$vin = trim($_GET['vin'] ?? '');
if ($vin !== '') {
    $filters[] = 'i.vin LIKE ?';
    $params[] = "%$vin%";
}
$sm = trim($_GET['sm'] ?? '');
if ($sm !== '') {
    $filters[] = 'u.username LIKE ?';
    $params[] = "%$sm%";
}
$dateFrom = trim($_GET['date_from'] ?? '');
if ($dateFrom !== '') {
    // Expect YYYY-MM-DD
    $filters[] = 'i.creation_date >= ?';
    $params[] = $dateFrom . ' 00:00:00';
}
$dateTo = trim($_GET['date_to'] ?? '');
if ($dateTo !== '') {
    $filters[] = 'i.creation_date <= ?';
    $params[] = $dateTo . ' 23:59:59';
}
$minTotal = trim($_GET['min_total'] ?? '');
if ($minTotal !== '' && is_numeric($minTotal)) {
    $filters[] = 'i.grand_total >= ?';
    $params[] = (float)$minTotal;
}
$maxTotal = trim($_GET['max_total'] ?? '');
if ($maxTotal !== '' && is_numeric($maxTotal)) {
    $filters[] = 'i.grand_total <= ?';
    $params[] = (float)$maxTotal;
}
$finaStatus = trim($_GET['fina_status'] ?? '');
if ($finaStatus !== '') {
    if ($finaStatus === 'opened') {
        $filters[] = 'i.opened_in_fina = 1';
    } elseif ($finaStatus === 'not_opened') {
        $filters[] = 'i.opened_in_fina = 0';
    }
}

// Compose SQL
$sql = 'SELECT i.*, u.username AS sm_username FROM invoices i LEFT JOIN users u ON i.service_manager_id = u.id';
if (!empty($filters)) {
    $sql .= ' WHERE ' . implode(' AND ', $filters);
}
$sql .= ' ORDER BY i.created_at DESC LIMIT 1000';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$invoices = $stmt->fetchAll();
$resultsCount = count($invoices);
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

        
        <!-- Filters -->
        <form method="get" class="mb-4 grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
            <div class="md:col-span-3 text-sm text-gray-600 mb-1">Showing <?php echo $resultsCount; ?> result(s)</div>
            <div>
                <label class="block text-xs font-medium text-gray-600">Search</label>
                <input type="text" name="q" value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>" placeholder="Customer, plate, VIN..." class="mt-1 block w-full rounded border-gray-200 p-2" />
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600">Service Manager</label>
                <input type="text" name="sm" value="<?php echo htmlspecialchars($_GET['sm'] ?? ''); ?>" placeholder="Manager username" class="mt-1 block w-full rounded border-gray-200 p-2" />
            </div>
            <div class="flex gap-2">
                <div class="flex-1">
                    <label class="block text-xs font-medium text-gray-600">Date From</label>
                    <input type="date" name="date_from" value="<?php echo htmlspecialchars($_GET['date_from'] ?? ''); ?>" class="mt-1 block w-full rounded border-gray-200 p-2" />
                </div>
                <div class="flex-1">
                    <label class="block text-xs font-medium text-gray-600">Date To</label>
                    <input type="date" name="date_to" value="<?php echo htmlspecialchars($_GET['date_to'] ?? ''); ?>" class="mt-1 block w-full rounded border-gray-200 p-2" />
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600">Plate</label>
                <input type="text" name="plate" value="<?php echo htmlspecialchars($_GET['plate'] ?? ''); ?>" placeholder="Plate..." class="mt-1 block w-full rounded border-gray-200 p-2" />
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600">VIN</label>
                <input type="text" name="vin" value="<?php echo htmlspecialchars($_GET['vin'] ?? ''); ?>" placeholder="VIN..." class="mt-1 block w-full rounded border-gray-200 p-2" />
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600">FINA Status</label>
                <select name="fina_status" class="mt-1 block w-full rounded border-gray-200 p-2">
                    <option value="">All</option>
                    <option value="opened" <?php echo ($_GET['fina_status'] ?? '') === 'opened' ? 'selected' : ''; ?>>Opened in FINA</option>
                    <option value="not_opened" <?php echo ($_GET['fina_status'] ?? '') === 'not_opened' ? 'selected' : ''; ?>>Not Opened</option>
                </select>
            </div>
            <div class="flex gap-2">
                <div class="flex-1">
                    <label class="block text-xs font-medium text-gray-600">Min Total</label>
                    <input type="number" step="0.01" name="min_total" value="<?php echo htmlspecialchars($_GET['min_total'] ?? ''); ?>" class="mt-1 block w-full rounded border-gray-200 p-2" />
                </div>
                <div class="flex-1">
                    <label class="block text-xs font-medium text-gray-600">Max Total</label>
                    <input type="number" step="0.01" name="max_total" value="<?php echo htmlspecialchars($_GET['max_total'] ?? ''); ?>" class="mt-1 block w-full rounded border-gray-200 p-2" />
                </div>
            </div>
            <div class="md:col-span-3 flex gap-2">
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">Apply</button>
                <a href="manager.php" class="px-4 py-2 bg-gray-200 rounded">Reset</a>
            </div>
        </form>

        <div class="overflow-x-auto">
            <table class="bg-white rounded-lg shadow-md w-full min-w-[700px] text-sm">
                <thead>
                    <tr class="bg-gray-200">
                        <th class="px-2 md:px-4 py-2 text-left">ID</th>
                        <th class="px-2 md:px-4 py-2 text-left">Customer</th>
                        <th class="px-2 md:px-4 py-2 text-left">Phone</th>
                        <th class="px-2 md:px-4 py-2 text-left">Car</th>
                        <th class="px-2 md:px-4 py-2 text-left">Plate</th>
                        <th class="px-2 md:px-4 py-2 text-left">VIN</th>
                        <th class="px-2 md:px-4 py-2 text-left">Mileage</th>
                        <th class="px-2 md:px-4 py-2 text-left">Service Manager</th>
                        <th class="px-2 md:px-4 py-2 text-right">Total</th>
                        <th class="px-2 md:px-4 py-2 text-left">Created At</th>
                        <th class="px-2 md:px-4 py-2 text-center">FINA</th>
                        <th class="px-2 md:px-4 py-2 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoices as $invoice): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-2 md:px-4 py-2"><?php echo $invoice['id']; ?></td>
                        <td class="px-2 md:px-4 py-2 truncate max-w-[150px]"><?php echo htmlspecialchars($invoice['customer_name']); ?></td>
                        <td class="px-2 md:px-4 py-2 truncate max-w-[120px]"><?php echo htmlspecialchars($invoice['phone']); ?></td>
                        <td class="px-2 md:px-4 py-2 truncate max-w-[120px]"><?php echo htmlspecialchars($invoice['car_mark']); ?></td>
                        <td class="px-2 md:px-4 py-2 truncate max-w-[120px]"><?php echo htmlspecialchars($invoice['plate_number']); ?></td>
                        <td class="px-2 md:px-4 py-2 truncate max-w-[140px]"><?php echo htmlspecialchars($invoice['vin'] ?? ''); ?></td>
                        <td class="px-2 md:px-4 py-2 truncate max-w-[120px]"><?php echo htmlspecialchars($invoice['mileage']); ?></td>
                        <td class="px-2 md:px-4 py-2 truncate max-w-[140px]"><?php echo htmlspecialchars($invoice['sm_username'] ?? ''); ?></td>
                        <td class="px-2 md:px-4 py-2 text-right"><?php echo $invoice['grand_total']; ?> ₾</td>
                        <td class="px-2 md:px-4 py-2 truncate max-w-[140px]"><?php echo $invoice['created_at']; ?></td>
                        <td class="px-2 md:px-4 py-2 text-center">
                            <input type="checkbox"
                                   class="fina-checkbox w-4 h-4 text-green-600 bg-gray-100 border-gray-300 rounded focus:ring-green-500"
                                   data-invoice-id="<?php echo $invoice['id']; ?>"
                                   <?php echo (!empty($invoice['opened_in_fina'])) ? 'checked' : ''; ?>>
                        </td>
                        <td class="px-2 md:px-4 py-2 text-center">
                            <div class="flex items-center justify-center gap-2">
                                <a href="view_invoice.php?id=<?php echo $invoice['id']; ?>" class="text-blue-500 hover:underline">View</a>
                                <a href="index.php?edit_id=<?php echo $invoice['id']; ?>" class="text-blue-500 hover:underline">Edit</a>
                                <a href="print_invoice.php?id=<?php echo $invoice['id']; ?>" class="text-blue-500 hover:underline" target="_blank">Print</a>
                                <a href="transfer_invoice.php?invoice_id=<?php echo $invoice['id']; ?>" class="text-green-500 hover:underline" onclick="return confirm('Are you sure you want to transfer this invoice to SQL Server?')">Transfer</a>
                                <?php if (currentUserCan('delete_invoice')): ?>
                                <form method="post" onsubmit="return confirm('Are you sure?');">
                                    <input type="hidden" name="id" value="<?php echo $invoice['id']; ?>" />
                                    <button type="submit" name="delete_invoice" class="text-red-500 hover:underline">Delete</button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($resultsCount === 0): ?>
            <div class="mt-4 text-sm text-gray-500">No invoices match the current filters.</div>
        <?php endif; ?>
    </div>

    <script>
        // Handle FINA checkbox changes
        document.addEventListener('DOMContentLoaded', function() {
            const finaCheckboxes = document.querySelectorAll('.fina-checkbox');

            finaCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const invoiceId = this.dataset.invoiceId;
                    const isChecked = this.checked;

                    // Show loading state
                    this.disabled = true;
                    const originalOpacity = this.style.opacity;
                    this.style.opacity = '0.5';

                    // Send AJAX request
                    fetch('update_fina_status.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            invoice_id: invoiceId,
                            opened_in_fina: isChecked ? 1 : 0
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (!data.success) {
                            // Revert checkbox on error
                            this.checked = !isChecked;
                            alert('Failed to update FINA status: ' + (data.error || 'Unknown error'));
                        }
                    })
                    .catch(error => {
                        // Revert checkbox on error
                        this.checked = !isChecked;
                        alert('Error updating FINA status: ' + error.message);
                        console.error('FINA status update error:', error);
                    })
                    .finally(() => {
                        // Restore original state
                        this.disabled = false;
                        this.style.opacity = originalOpacity;
                    });
                });
            });
        });
    </script>
</body>
</html>