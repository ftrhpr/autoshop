<?php
require '../config.php';

if (!isset($_SESSION['user_id']) || !currentUserCan('manage_customers')) {
    header('Location: ../login.php');
    exit;
}

// Check customers table exists
$tbl = $pdo->query("SHOW TABLES LIKE 'customers'")->fetch();
if (!$tbl) {
    echo '<div style="max-width:800px;margin:40px auto;padding:20px;background:#fff;border:1px solid #eee;border-radius:8px;">
            <h2 style="margin-top:0">Customers table not found</h2>
            <p>The database table <code>customers</code> does not exist. Run the migration to create it:</p>
            <p><a href="migrate_customers.php" style="display:inline-block;padding:8px 12px;background:#2563eb;color:white;border-radius:6px;text-decoration:none;">Run migration</a>
            <small style="display:block;margin-top:8px;color:#666">This will create the <code>customers</code> table and add <code>customer_id</code> to <code>invoices</code>. Only admins may run it.</small></p>
          </div>';
    exit;
}

// Handle create / update / delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_customer'])) {
        $full = trim($_POST['full_name']);
        $phone = trim($_POST['phone']);
        $email = trim($_POST['email']);
        $plate = strtoupper(trim($_POST['plate_number']));
        $car = trim($_POST['car_mark']);
        $notes = trim($_POST['notes']);

        $stmt = $pdo->prepare('INSERT INTO customers (full_name, phone, email, plate_number, car_mark, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$full, $phone, $email, $plate, $car, $notes, $_SESSION['user_id']]);

        // Audit
        $stmt = $pdo->prepare('INSERT INTO audit_logs (user_id, action, details, ip) VALUES (?, ?, ?, ?)');
        $stmt->execute([$_SESSION['user_id'], 'create_customer', "plate={$plate}, name={$full}", $_SERVER['REMOTE_ADDR'] ?? '']);

        $success = 'Customer created';
    }

    if (isset($_POST['update_customer'])) {
        $id = (int)$_POST['id'];
        $full = trim($_POST['full_name']);
        $phone = trim($_POST['phone']);
        $email = trim($_POST['email']);
        $plate = strtoupper(trim($_POST['plate_number']));
        $car = trim($_POST['car_mark']);
        $notes = trim($_POST['notes']);

        $stmt = $pdo->prepare('UPDATE customers SET full_name=?, phone=?, email=?, plate_number=?, car_mark=?, notes=? WHERE id=?');
        $stmt->execute([$full, $phone, $email, $plate, $car, $notes, $id]);

        $stmt = $pdo->prepare('INSERT INTO audit_logs (user_id, action, details, ip) VALUES (?, ?, ?, ?)');
        $stmt->execute([$_SESSION['user_id'], 'update_customer', "id={$id}, plate={$plate}", $_SERVER['REMOTE_ADDR'] ?? '']);

        $success = 'Customer updated';
    }

    if (isset($_POST['delete_customer'])) {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare('DELETE FROM customers WHERE id=?');
        $stmt->execute([$id]);

        $stmt = $pdo->prepare('INSERT INTO audit_logs (user_id, action, details, ip) VALUES (?, ?, ?, ?)');
        $stmt->execute([$_SESSION['user_id'], 'delete_customer', "id={$id}", $_SERVER['REMOTE_ADDR'] ?? '']);

        $success = 'Customer deleted';
    }
}

// List + search
$search = strtoupper(trim($_GET['search'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

if ($search) {
    $stmt = $pdo->prepare('SELECT * FROM customers WHERE plate_number LIKE ? OR full_name LIKE ? ORDER BY created_at DESC LIMIT ? OFFSET ?');
    $stmt->bindValue(1, "%$search%", PDO::PARAM_STR);
    $stmt->bindValue(2, "%$search%", PDO::PARAM_STR);
    $stmt->bindValue(3, (int)$perPage, PDO::PARAM_INT);
    $stmt->bindValue(4, (int)$offset, PDO::PARAM_INT);
    $stmt->execute();

    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM customers WHERE plate_number LIKE ? OR full_name LIKE ?');
    $countStmt->execute(["%$search%","%$search%"]);
    $total = (int)$countStmt->fetchColumn();
} else {
    $stmt = $pdo->prepare('SELECT * FROM customers ORDER BY created_at DESC LIMIT ? OFFSET ?');
    $stmt->bindValue(1, (int)$perPage, PDO::PARAM_INT);
    $stmt->bindValue(2, (int)$offset, PDO::PARAM_INT);
    $stmt->execute();
    $total = (int)$pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn();
}

$customers = $stmt->fetchAll();
$totalPages = (int)ceil($total / $perPage);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customers - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-6 overflow-x-hidden">
    <?php include __DIR__ . '/../partials/sidebar.php'; ?>
    <div class="w-full ml-0 md:ml-64 p-4 md:p-6">
        <!-- Mobile menu button -->
        <button id="openSidebar" class="md:hidden fixed top-4 left-4 z-50 bg-slate-800 text-white p-2 rounded-md shadow-lg">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </button>
        <a href="index.php" class="text-blue-500 hover:underline mb-4 inline-block">&larr; Back</a>

        <div class="bg-white p-8 rounded-xl shadow-xl mb-6">
            <h2 class="text-3xl font-bold mb-6 text-gray-800 flex items-center">
                <svg class="w-8 h-8 mr-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87M16 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                </svg>
                Customers
            </h2>
            <?php if (isset($success)): ?><div class="text-green-600 mb-3"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
            <?php if (isset($_SESSION['import_summary'])): ?>
                <?php $s = $_SESSION['import_summary']; unset($_SESSION['import_summary']); ?>
                <?php if (isset($s['error'])): ?>
                    <div class="text-red-600 mb-3"><?php echo htmlspecialchars($s['error']); ?></div>
                <?php else: ?>
                    <div class="bg-green-50 border-l-4 border-green-400 p-3 mb-3">
                        <div class="font-semibold">Import Summary</div>
                        <div>Inserted: <?php echo (int)($s['inserted'] ?? 0); ?>, Updated: <?php echo (int)($s['updated'] ?? 0); ?>, Failed: <?php echo (int)($s['failed'] ?? 0); ?></div>
                        <?php if (!empty($s['failures'])): ?>
                            <details class="mt-2 text-sm text-red-700">
                                <summary>View failures (<?php echo count($s['failures']); ?>)</summary>
                                <ul class="list-disc pl-4 mt-2">
                                    <?php foreach ($s['failures'] as $f): ?>
                                        <li><?php echo htmlspecialchars($f); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </details>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <form method="get" class="mb-6 flex flex-col sm:flex-row gap-4">
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Search Customers</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by plate or name" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                </div>
                <div class="flex items-end">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-md font-medium transition flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        Search
                    </button>
                </div>
            </form>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <form method="post" class="bg-gradient-to-r from-blue-50 to-indigo-50 p-6 rounded-lg shadow-lg border border-blue-200">
                        <h3 class="font-bold text-lg mb-4 text-blue-800 flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                            </svg>
                            Create Customer
                        </h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                                <input type="text" name="full_name" placeholder="Enter full name" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                                <input type="text" name="phone" placeholder="Enter phone number" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                <input type="email" name="email" placeholder="Enter email" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Plate Number</label>
                                <input type="text" name="plate_number" placeholder="Enter plate number" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Car Mark</label>
                                <input type="text" name="car_mark" placeholder="Enter car mark" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                                <textarea name="notes" placeholder="Enter notes" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition" rows="3"></textarea>
                            </div>
                        </div>
                        <button type="submit" name="create_customer" class="mt-4 bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-md font-medium transition flex items-center">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Create Customer
                        </button>
                    </form>

                    <form method="post" action="import_customers.php" enctype="multipart/form-data" class="bg-gradient-to-r from-green-50 to-emerald-50 p-6 rounded-lg shadow-lg border border-green-200 mt-6">
                        <h4 class="font-bold text-lg mb-4 text-green-800 flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"/>
                            </svg>
                            Import Customers (CSV)
                        </h4>
                        <p class="text-sm text-gray-600 mb-4">Download the template and fill it. Required: full_name, phone. Optional: plate_number, email, car_mark, notes, last_service_at. Import will insert new customers or update existing ones by plate number or phone. <strong>Ensure the CSV file is saved in UTF-8 encoding to support Georgian characters.</strong></p>
                        <a href="customers_import_template.csv" class="inline-flex items-center px-4 py-2 bg-green-100 hover:bg-green-200 text-green-800 rounded-md text-sm font-medium transition mb-4">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                            </svg>
                            Download CSV template
                        </a>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Select CSV File</label>
                            <input type="file" name="csv_file" accept=".csv" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-green-50 file:text-green-700 hover:file:bg-green-100">
                        </div>
                        <button type="submit" class="mt-4 bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-md font-medium transition flex items-center">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"/>
                            </svg>
                            Upload & Import
                        </button>
                    </form>

                    <form method="post" action="import_customers_raw.php" class="bg-gradient-to-r from-purple-50 to-pink-50 p-6 rounded-lg shadow-lg border border-purple-200 mt-6">
                        <h4 class="font-bold text-lg mb-4 text-purple-800 flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            Import Customers (Raw Text)
                        </h4>
                        <p class="text-sm text-gray-600 mb-4">Paste names and phones separately (one per line). Import will pair them by line and insert new customers or update existing ones by phone.</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Names (one per line)</label>
                                <textarea name="names" rows="6" placeholder="John Doe&#10;Jane Smith" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition"></textarea>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Phones (one per line)</label>
                                <textarea name="phones" rows="6" placeholder="+995555000000&#10;+995555111111" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition"></textarea>
                            </div>
                        </div>
                        <button type="submit" class="mt-4 bg-purple-600 hover:bg-purple-700 text-white px-6 py-2 rounded-md font-medium transition flex items-center">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"/>
                            </svg>
                            Import Raw Data
                        </button>
                    </form>
                </div>

                <div>
                    <div class="overflow-x-auto overflow-y-auto bg-white rounded-lg shadow-lg border border-gray-200" style="max-height: 70vh;">
                        <table class="w-full text-xs sm:text-sm min-w-full" style="table-layout: fixed;">
                            <thead class="bg-gradient-to-r from-gray-100 to-gray-200">
                                <tr>
                                    <th class="px-1 py-3 sm:px-2 sm:py-4 text-left font-semibold text-gray-700 w-1/4">Plate</th>
                                    <th class="px-1 py-3 sm:px-2 sm:py-4 text-left font-semibold text-gray-700 w-1/4">Name</th>
                                    <th class="px-1 py-3 sm:px-2 sm:py-4 text-left font-semibold text-gray-700 w-1/4">Phone</th>
                                    <th class="px-1 py-3 sm:px-2 sm:py-4 font-semibold text-gray-700 w-1/4">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($customers as $c): ?>
                                <tr class="border-t border-gray-200 hover:bg-blue-50 transition">
                                    <td class="px-1 py-3 sm:px-2 sm:py-4"><?php echo htmlspecialchars($c['plate_number']); ?></td>
                                    <td class="px-1 py-3 sm:px-2 sm:py-4"><?php echo htmlspecialchars($c['full_name']); ?></td>
                                    <td class="px-1 py-3 sm:px-2 sm:py-4"><?php echo htmlspecialchars($c['phone']); ?></td>
                                    <td class="px-1 py-3 sm:px-2 sm:py-4 flex flex-col gap-1 sm:flex-row sm:gap-2">
                                        <button onclick="prefill(<?php echo $c['id']; ?>)" class="inline-flex items-center justify-center px-2 py-1 sm:px-3 sm:py-2 bg-blue-100 hover:bg-blue-200 text-blue-800 rounded-md text-xs font-medium transition flex-1 sm:flex-none">
                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                            Edit
                                        </button>
                                        <form method="post" style="display:inline-block" onsubmit="return confirm('Delete?');" class="flex-1 sm:flex-none">
                                            <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
                                            <button type="submit" name="delete_customer" class="inline-flex items-center justify-center w-full px-2 py-1 sm:px-3 sm:py-2 bg-red-100 hover:bg-red-200 text-red-800 rounded-md text-xs font-medium transition">
                                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                </svg>
                                                Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                    <div class="mt-6 flex flex-wrap gap-2 items-center justify-center">
                        <?php
                        $searchParam = $search ? '&search=' . urlencode($search) : '';
                        $prevPage = $page - 1;
                        $nextPage = $page + 1;
                        ?>
                        <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $prevPage; ?><?php echo $searchParam; ?>" class="inline-flex items-center px-3 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                            Prev
                        </a>
                        <?php endif; ?>

                        <?php if ($totalPages <= 7): ?>
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <a href="?page=<?php echo $i; ?><?php echo $searchParam; ?>" class="inline-flex items-center px-3 py-2 border rounded-md text-sm font-medium transition <?php echo $i === $page ? 'bg-blue-600 text-white border-blue-600' : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'; ?>"><?php echo $i; ?></a>
                            <?php endfor; ?>
                        <?php else: ?>
                            <a href="?page=1<?php echo $searchParam; ?>" class="inline-flex items-center px-3 py-2 border rounded-md text-sm font-medium transition <?php echo 1 === $page ? 'bg-blue-600 text-white border-blue-600' : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'; ?>">1</a>
                            <?php if ($page > 4): ?>
                            <span class="px-2 text-gray-500">...</span>
                            <?php endif; ?>
                            <?php for ($i = max(2, $page - 2); $i <= min($totalPages - 1, $page + 2); $i++): ?>
                            <a href="?page=<?php echo $i; ?><?php echo $searchParam; ?>" class="inline-flex items-center px-3 py-2 border rounded-md text-sm font-medium transition <?php echo $i === $page ? 'bg-blue-600 text-white border-blue-600' : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'; ?>"><?php echo $i; ?></a>
                            <?php endfor; ?>
                            <?php if ($page < $totalPages - 3): ?>
                            <span class="px-2 text-gray-500">...</span>
                            <?php endif; ?>
                            <a href="?page=<?php echo $totalPages; ?><?php echo $searchParam; ?>" class="inline-flex items-center px-3 py-2 border rounded-md text-sm font-medium transition <?php echo $totalPages === $page ? 'bg-blue-600 text-white border-blue-600' : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'; ?>"><?php echo $totalPages; ?></a>
                        <?php endif; ?>

                        <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $nextPage; ?><?php echo $searchParam; ?>" class="inline-flex items-center px-3 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                            Next
                            <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <script>
                function prefill(id) {
                    fetch('api_customers.php?id=' + id)
                        .then(r => r.json())
                        .then(data => {
                            if (!data) return;
                            // Replace create form with update form
                            const form = document.querySelector('form[method="post"]');
                            form.innerHTML = `
                                <h3 class="font-semibold mb-2">Update Customer</h3>
                                <input type="hidden" name="id" value="${data.id}">
                                <input type="text" name="full_name" value="${data.full_name || ''}" placeholder="Full Name" class="w-full px-2 py-2 border rounded mb-2">
                                <input type="text" name="phone" value="${data.phone || ''}" placeholder="Phone" class="w-full px-2 py-2 border rounded mb-2">
                                <input type="text" name="email" value="${data.email || ''}" placeholder="Email" class="w-full px-2 py-2 border rounded mb-2">
                                <input type="text" name="plate_number" value="${data.plate_number || ''}" placeholder="Plate Number" class="w-full px-2 py-2 border rounded mb-2">
                                <input type="text" name="car_mark" value="${data.car_mark || ''}" placeholder="Car Mark" class="w-full px-2 py-2 border rounded mb-2">
                                <textarea name="notes" placeholder="Notes" class="w-full px-2 py-2 border rounded mb-2">${data.notes || ''}</textarea>
                                <button type="submit" name="update_customer" class="mt-2 bg-blue-600 text-white px-4 py-2 rounded">Save</button>
                            `;
                        });
                }

                // Sidebar toggle
                document.getElementById('openSidebar').addEventListener('click', function() {
                    document.getElementById('site-sidebar').classList.remove('-translate-x-full');
                });
                document.getElementById('closeSidebar').addEventListener('click', function() {
                    document.getElementById('site-sidebar').classList.add('-translate-x-full');
                });
            </script>
        </div>
    </div>
</body>
</html>