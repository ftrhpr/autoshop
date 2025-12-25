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

// Check for update success
if (isset($_GET['updated'])) {
    $updated_id = (int)$_GET['updated'];
    $success = "Invoice #{$updated_id} updated successfully";
}

// Check for known error flags
if (isset($_GET['error'])) {
    $err = $_GET['error'];
    if ($err === 'missing_invoice_id') {
        $error = 'Invoice was saved but the system could not open the print view (missing invoice ID). Please open the invoice from the list.';
    } else {
        $error = 'Error: ' . htmlspecialchars($err);
    }
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

// Get recent invoice IDs (created in last 24 hours)
$recentInvoiceIds = [];
$stmt = $pdo->prepare('SELECT id FROM invoices WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)');
$stmt->execute();
$recentInvoiceIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>მენეჯერის პანელი - ავტო სერვისი</title>
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Georgian Fonts -->
    <link rel="stylesheet" href="https://web-fonts.ge/bpg-arial/" />
    <link rel="stylesheet" href="https://web-fonts.ge/bpg-arial-caps/" />
    
    <style>
        body { font-family: 'BPG Arial', 'BPG Arial Caps'; }
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
        .invoice-highlight {
            background-color: #fef3c7;
            border-left: 4px solid #fbbf24;
            transition: background-color 0.3s ease;
        }
        .invoice-highlight:hover {
            background-color: #fde68a !important;
        }
        .invoice-fina {
            background-color: #dbeafe; /* light blue */
            border-left: 4px solid #3b82f6;
        }
        .invoice-fina:hover {
            background-color: #bfdbfe !important;
        }
        .invoice-viewed {
            background-color: #d1fae5; /* light green */
            border-left: 4px solid #10b981;
        }
        .invoice-viewed:hover {
            background-color: #a7f3d0 !important;
        }
        .invoice-recent {
            background-color: #fef3c7;
            border-left: 4px solid #fbbf24;
        }
        .invoice-recent:hover {
            background-color: #fde68a !important;
        }
        .invoice-seen {
            background-color: white;
        }
        .invoice-seen:hover {
            background-color: #f9fafb !important;
        }
    </style>
</head>
<body class="bg-gray-100 h-screen overflow-hidden font-sans antialiased pb-20">
    <?php include 'partials/sidebar.php'; ?>

    <div class="h-full overflow-hidden ml-0 md:ml-64">
        <div class="h-full overflow-auto p-2 md:p-6">
        <h2 class="text-2xl font-bold mb-6">ინვოისების მართვა</h2>

        <?php if (isset($success)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        
        <!-- Filters -->
        <form method="get" class="mb-4 grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
            <div class="md:col-span-3 flex items-center justify-between">
                <div class="text-sm text-gray-600">ნაჩვენებია <span class="results-count"><?php echo $resultsCount; ?> შედეგი</span></div>
                <div class="text-sm text-green-600 flex items-center">
                    <span class="w-2 h-2 bg-green-500 rounded-full mr-2 animate-pulse"></span>
                    ცოცხალი განახლებები აქტიურია
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600">ძიება</label>
                <input type="text" name="q" value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>" placeholder="კლიენტი, ნომერი, VIN..." class="mt-1 block w-full rounded border-gray-200 p-2" />
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600">სერვისის მენეჯერი</label>
                <input type="text" name="sm" value="<?php echo htmlspecialchars($_GET['sm'] ?? ''); ?>" placeholder="მენეჯერის სახელი" class="mt-1 block w-full rounded border-gray-200 p-2" />
            </div>
            <div class="flex gap-2">
                <div class="flex-1">
                    <label class="block text-xs font-medium text-gray-600">თარიღიდან</label>
                    <input type="date" name="date_from" value="<?php echo htmlspecialchars($_GET['date_from'] ?? ''); ?>" class="mt-1 block w-full rounded border-gray-200 p-2" />
                </div>
                <div class="flex-1">
                    <label class="block text-xs font-medium text-gray-600">თარიღამდე</label>
                    <input type="date" name="date_to" value="<?php echo htmlspecialchars($_GET['date_to'] ?? ''); ?>" class="mt-1 block w-full rounded border-gray-200 p-2" />
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600">სახელმწიფო ნომერი</label>
                <input type="text" name="plate" value="<?php echo htmlspecialchars($_GET['plate'] ?? ''); ?>" placeholder="ნომერი..." class="mt-1 block w-full rounded border-gray-200 p-2" />
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600">VIN</label>
                <input type="text" name="vin" value="<?php echo htmlspecialchars($_GET['vin'] ?? ''); ?>" placeholder="VIN..." class="mt-1 block w-full rounded border-gray-200 p-2" />
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600">FINA სტატუსი</label>
                <select name="fina_status" class="mt-1 block w-full rounded border-gray-200 p-2">
                    <option value="">ყველა</option>
                    <option value="opened" <?php echo ($_GET['fina_status'] ?? '') === 'opened' ? 'selected' : ''; ?>>გახსნილია FINA-ში</option>
                    <option value="not_opened" <?php echo ($_GET['fina_status'] ?? '') === 'not_opened' ? 'selected' : ''; ?>>არ არის გახსნილი</option>
                </select>
            </div>
            <div class="flex gap-2">
                <div class="flex-1">
                    <label class="block text-xs font-medium text-gray-600">მინიმალური ჯამი</label>
                    <input type="number" step="0.01" name="min_total" value="<?php echo htmlspecialchars($_GET['min_total'] ?? ''); ?>" class="mt-1 block w-full rounded border-gray-200 p-2" />
                </div>
                <div class="flex-1">
                    <label class="block text-xs font-medium text-gray-600">მაქსიმალური ჯამი</label>
                    <input type="number" step="0.01" name="max_total" value="<?php echo htmlspecialchars($_GET['max_total'] ?? ''); ?>" class="mt-1 block w-full rounded border-gray-200 p-2" />
                </div>
            </div>
            <div class="md:col-span-3 flex gap-2">
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">გამოყენება</button>
                <a href="manager.php" class="px-4 py-2 bg-gray-200 rounded">გადატვირთვა</a>
            </div>
        </form>

        <!-- Desktop Table View -->
        <div class="hidden md:block overflow-x-auto">
            <table class="bg-white rounded-lg shadow-md w-full min-w-[700px] text-sm">
                <thead>
                    <tr class="bg-gray-200">
                        <th class="px-2 md:px-4 py-2 text-left">ID</th>
                        <th class="px-2 md:px-4 py-2 text-left">კლიენტი</th>
                        <th class="px-2 md:px-4 py-2 text-left">ტელეფონი</th>
                        <th class="px-2 md:px-4 py-2 text-left">ავტომობილი</th>
                        <th class="px-2 md:px-4 py-2 text-left">სახელმწიფო ნომერი</th>
                        <th class="px-2 md:px-4 py-2 text-left">VIN</th>
                        <th class="px-2 md:px-4 py-2 text-left">გარბენი</th>
                        <th class="px-2 md:px-4 py-2 text-left">სერვისის მენეჯერი</th>
                        <th class="px-2 md:px-4 py-2 text-right">ჯამი</th>
                        <th class="px-2 md:px-4 py-2 text-left">შექმნის თარიღი</th>
                        <th class="px-2 md:px-4 py-2 text-center">FINA</th>
                        <th class="px-2 md:px-4 py-2 text-center">მოქმედებები</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoices as $invoice): ?>
                    <tr class="hover:bg-gray-50 <?php echo (!empty($invoice['opened_in_fina'])) ? 'invoice-fina' : ((!empty($invoice['is_new'])) ? 'invoice-new invoice-recent' : 'invoice-viewed'); ?>" data-invoice-id="<?php echo $invoice['id']; ?>" data-created-at="<?php echo $invoice['created_at']; ?>">
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
                            <div class="flex flex-col sm:flex-row gap-1 justify-center">
                                <a href="<?php echo ($_SESSION['role'] === 'manager') ? 'mobile_invoice.php' : 'create.php'; ?>?edit_id=<?php echo $invoice['id']; ?>" class="text-purple-600 hover:underline text-xs md:text-sm px-2 py-1 rounded hover:bg-purple-50 transition">რედაქტირება</a>
                                <a href="view_invoice.php?id=<?php echo $invoice['id']; ?>" class="text-blue-500 hover:underline text-xs md:text-sm px-2 py-1 rounded hover:bg-blue-50 transition view-link">ნახვა</a>
                                <a href="print_invoice.php?id=<?php echo $invoice['id']; ?>" target="_blank" class="text-green-600 hover:underline text-xs md:text-sm px-2 py-1 rounded hover:bg-green-50 transition">ბეჭდვა</a>
                                <form method="post" style="display:inline-block" onsubmit="return confirm('დარწმუნებული ხართ, რომ გსურთ ამ ინვოისის წაშლა?');">
                                    <input type="hidden" name="id" value="<?php echo $invoice['id']; ?>">
                                    <button type="submit" name="delete_invoice" class="text-red-600 hover:underline text-xs md:text-sm px-2 py-1 rounded hover:bg-red-50 transition">წაშლა</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Mobile Card View -->
        <div class="md:hidden space-y-4">
            <?php foreach ($invoices as $invoice): ?>
            <div class="bg-white rounded-lg shadow-md p-4 <?php echo (!empty($invoice['opened_in_fina'])) ? 'invoice-fina' : ((!empty($invoice['is_new'])) ? 'invoice-new invoice-recent' : 'invoice-viewed'); ?>" data-invoice-id="<?php echo $invoice['id']; ?>" data-created-at="<?php echo $invoice['created_at']; ?>">
                <div class="flex justify-between items-start mb-3">
                    <div>
                        <h3 class="font-semibold text-lg">#<?php echo $invoice['id']; ?></h3>
                        <p class="text-sm text-gray-600"><?php echo $invoice['created_at']; ?></p>
                    </div>
                    <div class="text-right">
                        <p class="font-bold text-lg"><?php echo $invoice['grand_total']; ?> ₾</p>
                        <div class="flex items-center mt-1">
                            <input type="checkbox"
                                   class="fina-checkbox w-4 h-4 text-green-600 bg-gray-100 border-gray-300 rounded focus:ring-green-500 mr-2"
                                   data-invoice-id="<?php echo $invoice['id']; ?>"
                                   <?php echo (!empty($invoice['opened_in_fina'])) ? 'checked' : ''; ?>>
                            <span class="text-xs text-gray-600">FINA</span>
                        </div>
                    </div>
                </div>

                <div class="space-y-2 mb-4">
                    <div class="flex justify-between">
                        <span class="text-sm font-medium text-gray-700">კლიენტი:</span>
                        <span class="text-sm"><?php echo htmlspecialchars($invoice['customer_name']); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm font-medium text-gray-700">ტელეფონი:</span>
                        <span class="text-sm"><?php echo htmlspecialchars($invoice['phone']); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm font-medium text-gray-700">ავტომობილი:</span>
                        <span class="text-sm"><?php echo htmlspecialchars($invoice['car_mark']); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm font-medium text-gray-700">სახელმწიფო ნომერი:</span>
                        <span class="text-sm"><?php echo htmlspecialchars($invoice['plate_number']); ?></span>
                    </div>
                    <?php if (!empty($invoice['vin'])): ?>
                    <div class="flex justify-between">
                        <span class="text-sm font-medium text-gray-700">VIN:</span>
                        <span class="text-sm"><?php echo htmlspecialchars($invoice['vin']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($invoice['mileage'])): ?>
                    <div class="flex justify-between">
                        <span class="text-sm font-medium text-gray-700">გარბენი:</span>
                        <span class="text-sm"><?php echo htmlspecialchars($invoice['mileage']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($invoice['sm_username'])): ?>
                    <div class="flex justify-between">
                        <span class="text-sm font-medium text-gray-700">სერვისის მენეჯერი:</span>
                        <span class="text-sm"><?php echo htmlspecialchars($invoice['sm_username']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="flex flex-wrap gap-2 pt-3 border-t border-gray-200">
                    <a href="<?php echo ($_SESSION['role'] === 'manager') ? 'mobile_invoice.php' : 'create.php'; ?>?edit_id=<?php echo $invoice['id']; ?>" class="flex-1 min-w-[80px] bg-purple-600 hover:bg-purple-700 text-white text-center py-2 px-3 rounded-md text-sm font-medium transition">რედაქტირება</a>
                    <a href="view_invoice.php?id=<?php echo $invoice['id']; ?>" class="flex-1 min-w-[80px] bg-blue-600 hover:bg-blue-700 text-white text-center py-2 px-3 rounded-md text-sm font-medium transition view-link">ნახვა</a>
                    <a href="print_invoice.php?id=<?php echo $invoice['id']; ?>" target="_blank" class="flex-1 min-w-[80px] bg-green-600 hover:bg-green-700 text-white text-center py-2 px-3 rounded-md text-sm font-medium transition">ბეჭდვა</a>
                    <form method="post" style="flex: 1; min-width: 80px;" onsubmit="return confirm('დარწმუნებული ხართ, რომ გსურთ ამ ინვოისის წაშლა?');">
                        <input type="hidden" name="id" value="<?php echo $invoice['id']; ?>">
                        <button type="submit" name="delete_invoice" class="w-full bg-red-600 hover:bg-red-700 text-white py-2 px-3 rounded-md text-sm font-medium transition">წაშლა</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if ($resultsCount === 0): ?>
            <div class="mt-4 text-sm text-gray-500">No invoices match the current filters.</div>
        <?php endif; ?>
    </div>
    </div>

    <script>
        // User role for determining edit URL
        const userRole = '<?php echo $_SESSION['role'] ?? 'admin'; ?>';

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

        function playNotificationSound() {
            // Check if notifications are muted
            const MUTE_KEY = 'autoshop_notif_muted';
            if (localStorage.getItem(MUTE_KEY) === '1') {
                console.log('Notifications muted, skipping sound');
                return;
            }

            // Try to use the existing audio element from sidebar
            try {
                const audioEl = document.getElementById('notifAudio');
                if (audioEl) {
                    audioEl.currentTime = 0;
                    const p = audioEl.play();
                    if (p && typeof p.then === 'function') {
                        p.catch(() => {
                            // If play() is rejected (autoplay policy), fall back to WebAudio
                            playWebAudioBeep();
                        });
                    }
                    return;
                }
            } catch (e) {
                console.warn('Audio element play error:', e);
            }

            // Fallback to WebAudio
            playWebAudioBeep();
        }

        function playWebAudioBeep() {
            try {
                const AudioCtx = window.AudioContext || window.webkitAudioContext;
                const ctx = new AudioCtx();
                const now = ctx.currentTime;
                // Two short tones for a pleasant notification
                const tones = [880, 660];
                tones.forEach((freq, i) => {
                    const o = ctx.createOscillator();
                    const g = ctx.createGain();
                    o.type = 'sine';
                    o.frequency.value = freq;
                    o.connect(g);
                    g.connect(ctx.destination);
                    g.gain.setValueAtTime(0.0001, now + i * 0.12);
                    g.gain.exponentialRampToValueAtTime(0.15, now + i * 0.12 + 0.02);
                    g.gain.exponentialRampToValueAtTime(0.0001, now + i * 0.12 + 0.10);
                    o.start(now + i * 0.12);
                    o.stop(now + i * 0.12 + 0.11);
                });
            } catch (e) {
                console.warn('WebAudio fallback failed:', e);
                // Final fallback: small embedded WAV via Audio object
                try {
                    const fallback = new Audio();
                    fallback.src = 'data:audio/wav;base64,UklGRiQAAABXQVZFZm10IBAAAAABAAEAESsAACJWAAACABAAZGF0YQAAAAA=';
                    fallback.play().catch(() => {});
                } catch (err) {
                    console.warn('Audio fallback failed:', err);
                }
            }
        }

        function flashPageBackground() {
            const body = document.body;
            const originalBackground = body.style.backgroundColor;
            body.style.backgroundColor = '#fef3c7'; // Light yellow flash
            setTimeout(() => {
                body.style.backgroundColor = originalBackground || '';
            }, 300);
        }

        function initializeLiveUpdates() {
            console.log('Initializing live updates...');
            let lastTimestamp = null;
            let pollingInterval = 5000; // 5 seconds for reasonable update frequency
            let pollingTimer = null;
            let inFlight = false;
            let lastPollTime = 0;
            let newInvoiceIds = new Set(); // Track new invoices that haven't been viewed

            function fetchLatestTimestamp() {
                console.log('Fetching latest timestamp...');
                return fetch('api_live_invoices.php', { cache: 'no-store' })
                    .then(response => {
                        console.log('fetchLatestTimestamp response:', response.status);
                        return response.json();
                    })
                    .then(data => {
                        console.log('fetchLatestTimestamp data:', data);
                        if (data && data.success) {
                            lastTimestamp = data.latest_timestamp || null;
                            console.log('Set lastTimestamp to:', lastTimestamp);
                        }
                        return data;
                    })
                    .catch(e => {
                        console.warn('fetchLatestTimestamp error:', e);
                        throw e;
                    });
            }

            function pollForUpdates() {
                // Don't poll if page is not visible
                if (document.hidden) {
                    return;
                }

                // Prevent too frequent polling
                const now = Date.now();
                if (now - lastPollTime < 1000) { // Minimum 1 second between polls
                    return;
                }

                if (inFlight) {
                    console.log('Poll already in flight, skipping');
                    return;
                }

                lastPollTime = now;
                console.log('Polling for updates, lastTimestamp:', lastTimestamp);
                inFlight = true;

                const url = 'api_live_invoices.php' + (lastTimestamp ? ('?last_timestamp=' + encodeURIComponent(lastTimestamp)) : '');
                console.log('Polling URL:', url);

                fetch(url, { cache: 'no-store' })
                    .then(response => {
                        console.log('pollForUpdates response:', response.status);
                        return response.json();
                    })
                    .then(data => {
                        console.log('pollForUpdates data:', data);
                        if (data && data.success && data.new_count > 0) {
                            console.log('Found', data.new_count, 'updated invoices');
                            console.log('Updated invoices:', data.invoices.map(inv => inv.id));
                            handleInvoiceUpdates(data.invoices);
                            lastTimestamp = data.latest_timestamp;
                        } else {
                            console.log('No updated invoices found (count:', data.new_count, ')');
                        }
                    })
                    .catch(e => {
                        console.warn('pollForUpdates error:', e);
                        // Don't rethrow - continue polling
                    })
                    .finally(() => {
                        inFlight = false;
                    });
            }

            function handleInvoiceUpdates(updatedInvoices) {
                let hasUpdates = false;
            let addedNow = []; // Track invoices added during this poll

                // Process each updated invoice
                updatedInvoices.forEach(invoice => {
                    // Check if this invoice exists in desktop table
                    const existingRow = document.querySelector(`tr[data-invoice-id="${invoice.id}"]`);
                    // Check if this invoice exists in mobile cards
                    const existingCard = document.querySelector(`.md\\:hidden > div[data-invoice-id="${invoice.id}"]`);

                    if (existingRow) {
                        // Update existing desktop row
                        hasUpdates = true;
                        const newRow = createInvoiceRow(invoice);
                        // Remove the blinking if it was new
                        newRow.classList.remove('invoice-new');
                        // Replace the existing row
                        existingRow.parentNode.replaceChild(newRow, existingRow);
                        // Re-setup event listeners
                        setupRowEventListeners(newRow);
                    }

                    if (existingCard) {
                        // Update existing mobile card
                        hasUpdates = true;
                        const newCard = createInvoiceCard(invoice);
                        // Remove the blinking if it was new
                        newCard.classList.remove('invoice-new');
                        // Replace the existing card
                        existingCard.parentNode.replaceChild(newCard, existingCard);
                        // Re-setup event listeners
                        setupCardEventListeners(newCard);
                    }

                    if (!existingRow && !existingCard) {
                        // This is a new invoice - add to both desktop and mobile views
                        hasUpdates = true;

                        // Add to desktop table
                        const tableBody = document.querySelector('tbody');
                        if (tableBody) {
                            const newRow = createInvoiceRow(invoice);
                            newRow.classList.add('invoice-new'); // Start with blinking
                            newInvoiceIds.add(invoice.id);
                            addedNow.push(invoice.id);

                            // Insert at the top
                            if (tableBody.firstChild) {
                                tableBody.insertBefore(newRow, tableBody.firstChild);
                            } else {
                                tableBody.appendChild(newRow);
                            }
                        }

                        // Add to mobile cards
                        const mobileContainer = document.querySelector('.md\\:hidden.space-y-4');
                        if (mobileContainer) {
                            const newCard = createInvoiceCard(invoice);
                            newCard.classList.add('invoice-new'); // Start with blinking
                            newInvoiceIds.add(invoice.id);
                            addedNow.push(invoice.id);

                            // Insert at the top
                            if (mobileContainer.firstChild) {
                                mobileContainer.insertBefore(newCard, mobileContainer.firstChild);
                            } else {
                                mobileContainer.appendChild(newCard);
                            }
                        }
                    }
                });

                // Update results count display
                updateResultsCount();

                // Play notification sound only for invoices added during this poll
                if (addedNow.length > 0) {
                    playNotificationSound();
                    // Visual flash effect
                    flashPageBackground();
                    // Auto-scroll to top if there are new invoices
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            }

            function createInvoiceRow(invoice) {
                const row = document.createElement('tr');
                row.className = 'hover:bg-gray-50';
                if (invoice.opened_in_fina) {
                    row.classList.add('invoice-fina');
                } else if (invoice.is_new) {
                    row.classList.add('invoice-new');
                    row.classList.add('invoice-recent');
                } else {
                    row.classList.add('invoice-viewed');
                }
                row.setAttribute('data-invoice-id', invoice.id);
                // Preserve original created_at for this invoice so it doesn't change on updates (e.g., FINA toggles)
                row.setAttribute('data-created-at', invoice.created_at || '');

                row.innerHTML = `
                    <td class="px-2 md:px-4 py-2">${invoice.id}</td>
                    <td class="px-2 md:px-4 py-2 truncate max-w-[150px]">${escapeHtml(invoice.customer_name || '')}</td>
                    <td class="px-2 md:px-4 py-2 truncate max-w-[120px]">${escapeHtml(invoice.phone || '')}</td>
                    <td class="px-2 md:px-4 py-2 truncate max-w-[120px]">${escapeHtml(invoice.car_mark || '')}</td>
                    <td class="px-2 md:px-4 py-2 truncate max-w-[120px]">${escapeHtml(invoice.plate_number || '')}</td>
                    <td class="px-2 md:px-4 py-2 truncate max-w-[140px]">${escapeHtml(invoice.vin || '')}</td>
                    <td class="px-2 md:px-4 py-2 truncate max-w-[120px]">${escapeHtml(invoice.mileage || '')}</td>
                    <td class="px-2 md:px-4 py-2 truncate max-w-[140px]">${escapeHtml(invoice.sm_username || '')}</td>
                    <td class="px-2 md:px-4 py-2 text-right">${invoice.grand_total || 0} ₾</td>
                    <td class="px-2 md:px-4 py-2 truncate max-w-[140px]">${invoice.created_at}</td>
                    <td class="px-2 md:px-4 py-2 text-center">
                        <input type="checkbox" class="fina-checkbox w-4 h-4 text-green-600 bg-gray-100 border-gray-300 rounded focus:ring-green-500" data-invoice-id="${invoice.id}" ${invoice.opened_in_fina ? 'checked' : ''}>
                    </td>
                    <td class="px-2 md:px-4 py-2 text-center">
                        <a href="${userRole === 'manager' ? 'mobile_invoice.php' : 'create.php'}?edit_id=${invoice.id}" class="text-purple-600 hover:underline mr-2 text-xs md:text-sm">Edit</a>
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

            function createInvoiceCard(invoice) {
                const card = document.createElement('div');
                card.className = 'bg-white rounded-lg shadow-md p-4';
                if (invoice.opened_in_fina) {
                    card.classList.add('invoice-fina');
                } else if (invoice.is_new) {
                    card.classList.add('invoice-new');
                    card.classList.add('invoice-recent');
                } else {
                    card.classList.add('invoice-viewed');
                }
                card.setAttribute('data-invoice-id', invoice.id);
                card.setAttribute('data-created-at', invoice.created_at || '');

                card.innerHTML = `
                    <div class="flex justify-between items-start mb-3">
                        <div>
                            <h3 class="font-semibold text-lg">#${invoice.id}</h3>
                            <p class="text-sm text-gray-600">${invoice.created_at}</p>
                        </div>
                        <div class="text-right">
                            <p class="font-bold text-lg">${invoice.grand_total || 0} ₾</p>
                            <div class="flex items-center mt-1">
                                <input type="checkbox" class="fina-checkbox w-4 h-4 text-green-600 bg-gray-100 border-gray-300 rounded focus:ring-green-500 mr-2" data-invoice-id="${invoice.id}" ${invoice.opened_in_fina ? 'checked' : ''}>
                                <span class="text-xs text-gray-600">FINA</span>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-2 mb-4">
                        <div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-700">კლიენტი:</span>
                            <span class="text-sm">${escapeHtml(invoice.customer_name || '')}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-700">ტელეფონი:</span>
                            <span class="text-sm">${escapeHtml(invoice.phone || '')}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-700">ავტომობილი:</span>
                            <span class="text-sm">${escapeHtml(invoice.car_mark || '')}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-700">სახელმწიფო ნომერი:</span>
                            <span class="text-sm">${escapeHtml(invoice.plate_number || '')}</span>
                        </div>
                        ${invoice.vin ? `<div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-700">VIN:</span>
                            <span class="text-sm">${escapeHtml(invoice.vin)}</span>
                        </div>` : ''}
                        ${invoice.mileage ? `<div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-700">გარბენი:</span>
                            <span class="text-sm">${escapeHtml(invoice.mileage)}</span>
                        </div>` : ''}
                        ${invoice.sm_username ? `<div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-700">სერვისის მენეჯერი:</span>
                            <span class="text-sm">${escapeHtml(invoice.sm_username)}</span>
                        </div>` : ''}
                    </div>

                    <div class="flex flex-wrap gap-2 pt-3 border-t border-gray-200">
                        <a href="${userRole === 'manager' ? 'mobile_invoice.php' : 'create.php'}?edit_id=${invoice.id}" class="flex-1 min-w-[80px] bg-purple-600 hover:bg-purple-700 text-white text-center py-2 px-3 rounded-md text-sm font-medium transition">რედაქტირება</a>
                        <a href="view_invoice.php?id=${invoice.id}" class="flex-1 min-w-[80px] bg-blue-600 hover:bg-blue-700 text-white text-center py-2 px-3 rounded-md text-sm font-medium transition view-link">ნახვა</a>
                        <a href="print_invoice.php?id=${invoice.id}" target="_blank" class="flex-1 min-w-[80px] bg-green-600 hover:bg-green-700 text-white text-center py-2 px-3 rounded-md text-sm font-medium transition">ბეჭდვა</a>
                        <form method="post" style="flex: 1; min-width: 80px;" onsubmit="return confirm('დარწმუნებული ხართ, რომ გსურთ ამ ინვოისის წაშლა?');">
                            <input type="hidden" name="id" value="${invoice.id}">
                            <button type="submit" name="delete_invoice" class="w-full bg-red-600 hover:bg-red-700 text-white py-2 px-3 rounded-md text-sm font-medium transition">წაშლა</button>
                        </form>
                    </div>
                `;

                // Add event listeners for the new card
                setupCardEventListeners(card);

                return card;
            }

            function setupCardEventListeners(card) {
                const invoiceId = card.getAttribute('data-invoice-id');

                // Stop blinking and mark as seen when View link is clicked
                const viewLink = card.querySelector('.view-link');
                if (viewLink) {
                    viewLink.addEventListener('click', function() {
                        const invoiceId = parseInt(card.getAttribute('data-invoice-id'));
                        markInvoiceAsSeen(invoiceId, card);
                    });
                }

                // Also stop blinking on card click (but not on buttons/links)
                card.addEventListener('click', function(e) {
                    // Don't trigger if clicking on links/buttons
                    if (e.target.tagName === 'A' || e.target.tagName === 'BUTTON' || e.target.type === 'checkbox') {
                        return;
                    }
                    const invoiceId = parseInt(card.getAttribute('data-invoice-id'));
                    markInvoiceAsSeen(invoiceId, card);
                });

                // Setup FINA checkbox for new cards
                const finaCheckbox = card.querySelector('.fina-checkbox');
                if (finaCheckbox) {
                    finaCheckbox.addEventListener('change', function() {
                        const invoiceId = this.dataset.invoiceId;
                        const isChecked = this.checked;

                        // Update card styling immediately
                        if (isChecked) {
                            card.classList.add('invoice-fina');
                            card.classList.remove('invoice-new', 'invoice-recent', 'invoice-viewed');
                        } else {
                            card.classList.remove('invoice-fina');
                            if (!card.classList.contains('invoice-new')) {
                                card.classList.add('invoice-viewed');
                            }
                        }

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
                                // Revert card styling
                                if (!isChecked) {
                                    card.classList.add('invoice-fina');
                                } else {
                                    card.classList.remove('invoice-fina');
                                }
                                alert('Failed to update FINA status: ' + (data.error || 'Unknown error'));
                            }
                        })
                        .catch(error => {
                            this.checked = !isChecked;
                            // Revert card styling
                            if (!isChecked) {
                                card.classList.add('invoice-fina');
                            } else {
                                card.classList.remove('invoice-fina');
                            }
                            alert('Error updating FINA status: ' + error.message);
                        })
                        .finally(() => {
                            this.disabled = false;
                            this.style.opacity = '';
                        });
                    });
                }
            }

            function setupRowEventListeners(row) {
                const invoiceId = row.getAttribute('data-invoice-id');

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

                        // Update row styling immediately
                        if (isChecked) {
                            row.classList.add('invoice-fina');
                            row.classList.remove('invoice-new', 'invoice-recent', 'invoice-viewed');
                        } else {
                            row.classList.remove('invoice-fina');
                            if (!row.classList.contains('invoice-new')) {
                                row.classList.add('invoice-viewed');
                            }
                        }

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
                                // Revert row styling
                                if (!isChecked) {
                                    row.classList.add('invoice-fina');
                                } else {
                                    row.classList.remove('invoice-fina');
                                }
                                alert('Failed to update FINA status: ' + (data.error || 'Unknown error'));
                            }
                        })
                        .catch(error => {
                            this.checked = !isChecked;
                            // Revert row styling
                            if (!isChecked) {
                                row.classList.add('invoice-fina');
                            } else {
                                row.classList.remove('invoice-fina');
                            }
                            alert('Error updating FINA status: ' + error.message);
                        })
                        .finally(() => {
                            this.disabled = false;
                            this.style.opacity = '';
                        });
                    });
                }
            }

            function markInvoiceAsSeen(invoiceId, element) {
                if (newInvoiceIds.has(invoiceId)) {
                    newInvoiceIds.delete(invoiceId);
                    element.classList.remove('invoice-new', 'invoice-recent');
                    element.classList.add('invoice-highlight');
                    // Remove highlight after 30 seconds and add seen class
                    setTimeout(() => {
                        element.classList.remove('invoice-highlight');
                        if (element.classList.contains('invoice-fina')) {
                            // keep fina
                        } else {
                            element.classList.add('invoice-viewed');
                        }
                    }, 30000);
                } else {
                    // Even if not in newInvoiceIds, still mark as seen for cross-tab communication
                    element.classList.remove('invoice-new', 'invoice-recent');
                    element.classList.add('invoice-highlight');
                    setTimeout(() => {
                        element.classList.remove('invoice-highlight');
                        if (element.classList.contains('invoice-fina')) {
                            // keep
                        } else {
                            element.classList.add('invoice-viewed');
                        }
                    }, 30000);
                }

                // Update sessionStorage for cross-tab communication
                try {
                    const seenInvoices = JSON.parse(sessionStorage.getItem('seen_invoices') || '[]');
                    if (!seenInvoices.includes(invoiceId)) {
                        seenInvoices.push(invoiceId);
                        sessionStorage.setItem('seen_invoices', JSON.stringify(seenInvoices));
                        // Trigger storage event for cross-tab communication
                        window.dispatchEvent(new StorageEvent('storage', {
                            key: 'seen_invoices',
                            newValue: JSON.stringify(seenInvoices),
                            storageArea: sessionStorage
                        }));
                    }
                } catch (e) {
                    console.warn('Could not update seen invoices in sessionStorage', e);
                }
            }

            // Listen for cross-tab communication (when invoice is viewed in another tab)
            window.addEventListener('storage', function(e) {
                if (e.key === 'seen_invoices' && e.newValue) {
                    try {
                        const seenInvoices = JSON.parse(e.newValue);
                        seenInvoices.forEach(invoiceId => {
                            // Mark desktop row as seen
                            const row = document.querySelector(`tr[data-invoice-id="${invoiceId}"]`);
                            if (row) {
                                markInvoiceAsSeen(invoiceId, row);
                            }

                            // Mark mobile card as seen
                            const card = document.querySelector(`.md\\:hidden > div[data-invoice-id="${invoiceId}"]`);
                            if (card) {
                                markInvoiceAsSeen(invoiceId, card);
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
                            // Mark desktop row as new
                            const row = document.querySelector(`tr[data-invoice-id="${invoiceId}"]`);
                            if (row) {
                                row.classList.add('invoice-new');
                                newInvoiceIds.add(invoiceId);
                            }

                            // Mark mobile card as new
                            const card = document.querySelector(`.md\\:hidden > div[data-invoice-id="${invoiceId}"]`);
                            if (card) {
                                card.classList.add('invoice-new');
                                newInvoiceIds.add(invoiceId);
                            }
                        } else {
                            // Mark as seen if already viewed
                            const row = document.querySelector(`tr[data-invoice-id="${invoiceId}"]`);
                            if (row) {
                                row.classList.add('invoice-seen');
                            }

                            const card = document.querySelector(`.md\\:hidden > div[data-invoice-id="${invoiceId}"]`);
                            if (card) {
                                card.classList.add('invoice-seen');
                            }
                        }
                    });
                } catch (e) {
                    console.warn('Error initializing recent invoices', e);
                }
            }

            // Add event listeners to all existing rows and cards
            function setupExistingEventListeners() {
                // Setup desktop table rows
                document.querySelectorAll('tbody tr[data-invoice-id]').forEach(row => {
                    setupRowEventListeners(row);

                    // Check if this invoice has been seen and apply styling
                    const invoiceId = parseInt(row.getAttribute('data-invoice-id'));
                    try {
                        const seenInvoices = JSON.parse(sessionStorage.getItem('seen_invoices') || '[]');
                        if (seenInvoices.includes(invoiceId) && !row.classList.contains('invoice-recent') && !row.classList.contains('invoice-new')) {
                            row.classList.add('invoice-seen');
                        }
                    } catch (e) {
                        // Ignore sessionStorage errors
                    }
                });

                // Setup mobile cards
                document.querySelectorAll('.md\\:hidden > div[data-invoice-id]').forEach(card => {
                    setupCardEventListeners(card);

                    // Check if this invoice has been seen and apply styling
                    const invoiceId = parseInt(card.getAttribute('data-invoice-id'));
                    try {
                        const seenInvoices = JSON.parse(sessionStorage.getItem('seen_invoices') || '[]');
                        if (seenInvoices.includes(invoiceId) && !card.classList.contains('invoice-recent') && !card.classList.contains('invoice-new')) {
                            card.classList.add('invoice-seen');
                        }
                    } catch (e) {
                        // Ignore sessionStorage errors
                    }
                });
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
            console.log('Starting live updates initialization...');
            initializeRecentInvoices(); // Mark recent invoices as new
            setupExistingEventListeners(); // Add event listeners to existing rows and cards
            fetchLatestTimestamp().then(() => {
                console.log('Initial timestamp fetched, starting polling...');
                pollForUpdates();
                pollingTimer = setInterval(pollForUpdates, pollingInterval);
                console.log('Polling started with interval:', pollingInterval);
            }).catch(e => {
                console.error('Failed to initialize live updates:', e);
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