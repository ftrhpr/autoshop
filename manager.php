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

// Identify recent invoices (created within last 2 hours) for highlighting
$recentThreshold = date('Y-m-d H:i:s', strtotime('-2 hours'));
$recentInvoiceIds = [];
foreach ($invoices as $invoice) {
    if ($invoice['created_at'] > $recentThreshold) {
        $recentInvoiceIds[] = $invoice['id'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Panel - Auto Shop</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Live update styles for new invoices */
        @keyframes invoice-blink {
            0%, 50% { background-color: #fef3c7; border-left: 4px solid #f59e0b; }
            51%, 100% { background-color: #fffbeb; border-left: 4px solid #fbbf24; }
        }
        .invoice-new {
            animation: invoice-blink 1.5s infinite;
            border-left: 4px solid #fbbf24;
            background-color: #fffbeb;
        }
        .invoice-new:hover {
            background-color: #fef3c7 !important;
        }
        .invoice-recent {
            background-color: #fef3c7;
            border-left: 4px solid #fbbf24;
        }
        .invoice-recent:hover {
            background-color: #fde68a !important;
        }
        .invoice-highlight {
            background-color: #fef3c7;
            border-left: 4px solid #fbbf24;
            transition: background-color 0.3s ease;
        }
        .invoice-highlight:hover {
            background-color: #fde68a !important;
        }
    </style>
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
            <div class="md:col-span-3 flex items-center justify-between">
                <div class="text-sm text-gray-600">Showing <span class="results-count"><?php echo $resultsCount; ?> result(s)</span></div>
                <div class="text-sm text-green-600 flex items-center">
                    <span class="w-2 h-2 bg-green-500 rounded-full mr-2 animate-pulse"></span>
                    Live updates active
                </div>
            </div>
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
                    <tr class="hover:bg-gray-50 <?php echo in_array($invoice['id'], $recentInvoiceIds) ? 'invoice-recent' : ''; ?>" data-invoice-id="<?php echo $invoice['id']; ?>" data-created-at="<?php echo $invoice['created_at']; ?>">
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

            // Live invoice updates
            initializeLiveUpdates();
        });

        function initializeLiveUpdates() {
            let lastTimestamp = null;
            let pollingInterval = 10000; // 10 seconds for manager panel
            let pollingTimer = null;
            let inFlight = false;
            let newInvoiceIds = new Set(); // Track new invoices that haven't been viewed

            function fetchLatestTimestamp() {
                return fetch('api_live_invoices.php', { cache: 'no-store' })
                    .then(response => response.json())
                    .then(data => {
                        if (data && data.success) {
                            lastTimestamp = data.latest_timestamp || null;
                        }
                    })
                    .catch(e => console.warn('fetchLatestTimestamp error', e));
            }

            function pollForUpdates() {
                if (inFlight) return;
                inFlight = true;

                const url = 'api_live_invoices.php' + (lastTimestamp ? ('?last_timestamp=' + encodeURIComponent(lastTimestamp)) : '');
                fetch(url, { cache: 'no-store' })
                    .then(response => response.json())
                    .then(data => {
                        if (data && data.success && data.new_count > 0) {
                            handleNewInvoices(data.invoices);
                            lastTimestamp = data.latest_timestamp;
                        }
                    })
                    .catch(e => console.warn('pollForUpdates error', e))
                    .finally(() => {
                        inFlight = false;
                    });
            }

            function handleNewInvoices(newInvoices) {
                const tableBody = document.querySelector('tbody');
                if (!tableBody) return;

                // Add new invoices to the top of the table
                newInvoices.forEach(invoice => {
                    const newRow = createInvoiceRow(invoice);
                    newRow.classList.add('invoice-new'); // Start with blinking
                    newInvoiceIds.add(invoice.id);

                    // Insert at the top
                    if (tableBody.firstChild) {
                        tableBody.insertBefore(newRow, tableBody.firstChild);
                    } else {
                        tableBody.appendChild(newRow);
                    }
                });

                // Update results count display
                updateResultsCount();

                // Auto-scroll to top if there are new invoices
                if (newInvoices.length > 0) {
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            }

            function createInvoiceRow(invoice) {
                const row = document.createElement('tr');
                row.className = 'hover:bg-gray-50';
                row.setAttribute('data-invoice-id', invoice.id);

                row.innerHTML = `
                    <td class="px-2 md:px-4 py-2">${invoice.id}</td>
                    <td class="px-2 md:px-4 py-2 truncate max-w-[150px]">${escapeHtml(invoice.customer_name || '')}</td>
                    <td class="px-2 md:px-4 py-2 truncate max-w-[120px]"></td>
                    <td class="px-2 md:px-4 py-2 truncate max-w-[120px]"></td>
                    <td class="px-2 md:px-4 py-2 truncate max-w-[120px]">${escapeHtml(invoice.plate_number || '')}</td>
                    <td class="px-2 md:px-4 py-2 truncate max-w-[140px]"></td>
                    <td class="px-2 md:px-4 py-2 truncate max-w-[120px]"></td>
                    <td class="px-2 md:px-4 py-2 truncate max-w-[140px]"></td>
                    <td class="px-2 md:px-4 py-2 text-right">${invoice.grand_total || 0} ₾</td>
                    <td class="px-2 md:px-4 py-2 truncate max-w-[140px]">${invoice.created_at}</td>
                    <td class="px-2 md:px-4 py-2 text-center">
                        <input type="checkbox" class="fina-checkbox w-4 h-4 text-green-600 bg-gray-100 border-gray-300 rounded focus:ring-green-500" data-invoice-id="${invoice.id}">
                    </td>
                    <td class="px-2 md:px-4 py-2 text-center">
                        <a href="view_invoice.php?id=${invoice.id}" class="text-blue-500 hover:underline mr-2 text-xs md:text-sm view-link">View</a>
                        <a href="print_invoice.php?id=${invoice.id}" target="_blank" class="text-green-600 hover:underline mr-2 text-xs md:text-sm">Print</a>
                        <form method="post" style="display:inline-block" onsubmit="return confirm('Are you sure you want to delete this invoice?');">
                            <input type="hidden" name="id" value="${invoice.id}">
                            <button type="submit" name="delete_invoice" class="text-red-600 hover:underline text-xs md:text-sm">Delete</button>
                        </form>
                    </td>
                `;

                // Add event listeners for the new row
                setupRowEventListeners(row);

                return row;
            }

            function setupRowEventListeners(row) {
                // Stop blinking and mark as seen when View link is clicked
                const viewLink = row.querySelector('.view-link');
                if (viewLink) {
                    viewLink.addEventListener('click', function() {
                        const invoiceId = parseInt(row.getAttribute('data-invoice-id'));
                        markInvoiceAsSeen(invoiceId, row);
                    });
                }

                // Also stop blinking on row click
                row.addEventListener('click', function(e) {
                    // Don't trigger if clicking on links/buttons
                    if (e.target.tagName === 'A' || e.target.tagName === 'BUTTON' || e.target.type === 'checkbox') {
                        return;
                    }
                    const invoiceId = parseInt(row.getAttribute('data-invoice-id'));
                    markInvoiceAsSeen(invoiceId, row);
                });

                // Setup FINA checkbox for new rows
                const finaCheckbox = row.querySelector('.fina-checkbox');
                if (finaCheckbox) {
                    finaCheckbox.addEventListener('change', function() {
                        const invoiceId = this.dataset.invoiceId;
                        const isChecked = this.checked;

                        this.disabled = true;
                        this.style.opacity = '0.5';

                        fetch('update_fina_status.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ invoice_id: invoiceId, opened_in_fina: isChecked ? 1 : 0 })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (!data.success) {
                                this.checked = !isChecked;
                                alert('Failed to update FINA status: ' + (data.error || 'Unknown error'));
                            }
                        })
                        .catch(error => {
                            this.checked = !isChecked;
                            alert('Error updating FINA status: ' + error.message);
                        })
                        .finally(() => {
                            this.disabled = false;
                            this.style.opacity = '';
                        });
                    });
                }
            }

            function markInvoiceAsSeen(invoiceId, row) {
                if (newInvoiceIds.has(invoiceId)) {
                    newInvoiceIds.delete(invoiceId);
                    row.classList.remove('invoice-new');
                    row.classList.add('invoice-highlight');
                    // Remove highlight after 30 seconds
                    setTimeout(() => {
                        row.classList.remove('invoice-highlight');
                    }, 30000);
                }
            }

            // Listen for cross-tab communication (when invoice is viewed in another tab)
            window.addEventListener('storage', function(e) {
                if (e.key === 'seen_invoices' && e.newValue) {
                    try {
                        const seenInvoices = JSON.parse(e.newValue);
                        seenInvoices.forEach(invoiceId => {
                            const row = document.querySelector(`tr[data-invoice-id="${invoiceId}"]`);
                            if (row) {
                                markInvoiceAsSeen(invoiceId, row);
                            }
                        });
                    } catch (e) {
                        console.warn('Error parsing seen_invoices from storage', e);
                    }
                }
            });

            // Get recent invoice IDs from PHP
            const recentInvoiceIds = <?php echo json_encode($recentInvoiceIds); ?>;

            // Initialize recent invoices as "new" unless already seen
            function initializeRecentInvoices() {
                try {
                    const seenInvoices = JSON.parse(sessionStorage.getItem('seen_invoices') || '[]');
                    const seenSet = new Set(seenInvoices);

                    recentInvoiceIds.forEach(invoiceId => {
                        if (!seenSet.has(invoiceId)) {
                            const row = document.querySelector(`tr[data-invoice-id="${invoiceId}"]`);
                            if (row) {
                                row.classList.add('invoice-new');
                                newInvoiceIds.add(invoiceId);
                            }
                        }
                    });
                } catch (e) {
                    console.warn('Error initializing recent invoices', e);
                }
            }

            // Check for already seen invoices on load
            try {
                const seenInvoices = JSON.parse(sessionStorage.getItem('seen_invoices') || '[]');
                seenInvoices.forEach(invoiceId => {
                    const row = document.querySelector(`tr[data-invoice-id="${invoiceId}"]`);
                    if (row && newInvoiceIds.has(invoiceId)) {
                        markInvoiceAsSeen(invoiceId, row);
                    }
                });
            } catch (e) {
                console.warn('Error checking seen invoices on load', e);
            }



            function updateResultsCount() {
                const tableBody = document.querySelector('tbody');
                if (tableBody) {
                    const rowCount = tableBody.children.length;
                    // Update any results count display if it exists
                    const countDisplay = document.querySelector('.results-count');
                    if (countDisplay) {
                        countDisplay.textContent = `Showing ${rowCount} invoice${rowCount !== 1 ? 's' : ''}`;
                    }
                }
            }

            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            // Initialize
            initializeRecentInvoices(); // Mark recent invoices as new
            fetchLatestTimestamp().then(() => {
                pollForUpdates();
                pollingTimer = setInterval(pollForUpdates, pollingInterval);
            });

            // Expose for debugging
            window.__liveInvoices = {
                pollForUpdates,
                fetchLatestTimestamp,
                newInvoiceIds,
                markAsSeen: function(invoiceId) {
                    const row = document.querySelector(`tr[data-invoice-id="${invoiceId}"]`);
                    if (row) {
                        markInvoiceAsSeen(invoiceId, row);
                    }
                }
            };
        }
    </script>
</body>
</html>