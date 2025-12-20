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
    <title>Customers - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-6">
    <?php include __DIR__ . '/../partials/sidebar.php'; ?>
    <div class="container mx-auto ml-0 md:ml-64">
        <a href="index.php" class="text-blue-500 hover:underline mb-4 inline-block">&larr; Back</a>

        <div class="bg-white p-6 rounded-lg shadow-md mb-6">
            <h2 class="text-2xl font-bold mb-4">Customers</h2>
            <?php if (isset($success)): ?><div class="text-green-600 mb-3"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

            <form method="get" class="mb-4 flex gap-2">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search plate or name" class="px-3 py-2 border rounded w-full">
                <button type="submit" class="px-3 py-2 bg-gray-200 rounded">Search</button>
            </form>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <form method="post" class="bg-gray-50 p-4 rounded">
                        <h3 class="font-semibold mb-2">Create Customer</h3>
                        <input type="text" name="full_name" placeholder="Full Name" class="w-full px-2 py-2 border rounded mb-2">
                        <input type="text" name="phone" placeholder="Phone" class="w-full px-2 py-2 border rounded mb-2">
                        <input type="text" name="email" placeholder="Email" class="w-full px-2 py-2 border rounded mb-2">
                        <input type="text" name="plate_number" placeholder="Plate Number" class="w-full px-2 py-2 border rounded mb-2">
                        <input type="text" name="car_mark" placeholder="Car Mark" class="w-full px-2 py-2 border rounded mb-2">
                        <textarea name="notes" placeholder="Notes" class="w-full px-2 py-2 border rounded mb-2"></textarea>
                        <button type="submit" name="create_customer" class="mt-2 bg-blue-600 text-white px-4 py-2 rounded">Create</button>
                    </form>
                </div>

                <div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="px-2 py-2 text-left">Plate</th>
                                    <th class="px-2 py-2 text-left">Name</th>
                                    <th class="px-2 py-2 text-left">Phone</th>
                                    <th class="px-2 py-2">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($customers as $c): ?>
                                <tr class="border-t">
                                    <td class="px-2 py-2"><?php echo htmlspecialchars($c['plate_number']); ?></td>
                                    <td class="px-2 py-2"><?php echo htmlspecialchars($c['full_name']); ?></td>
                                    <td class="px-2 py-2"><?php echo htmlspecialchars($c['phone']); ?></td>
                                    <td class="px-2 py-2">
                                        <button onclick="prefill(<?php echo $c['id']; ?>)" class="px-2 py-1 bg-gray-200 rounded">Edit</button>
                                        <form method="post" style="display:inline-block" onsubmit="return confirm('Delete?');">
                                            <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
                                            <button type="submit" name="delete_customer" class="px-2 py-1 bg-red-200 rounded">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="mt-4 flex gap-2">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?search=<?php echo urlencode($search); ?>&page=<?php echo $i; ?>" class="px-3 py-1 rounded <?php echo $i === $page ? 'bg-blue-600 text-white' : 'bg-gray-100'; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>
                    </div>
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
            </script>
        </div>
    </div>
</body>
</html>