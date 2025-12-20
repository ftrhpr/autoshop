<?php
require '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// Handle user creation (with auditing)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_user'])) {
    $username = trim($_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];

    $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
    $stmt->execute([$username, $password, $role]);
    $success = 'User created successfully';

    // Log audit
    $actor = $_SESSION['user_id'] ?? null;
    $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, details, ip) VALUES (?, 'create_user', ?, ?)");
    $stmt->execute([$actor, "created user={$username}, role={$role}", $_SERVER['REMOTE_ADDR'] ?? '']);
}

// Analytics summary (Pro feature)
$totalUsers = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$totalInvoices = (int)$pdo->query('SELECT COUNT(*) FROM invoices')->fetchColumn();
$totalRevenue = (float)$pdo->query('SELECT IFNULL(SUM(grand_total),0) FROM invoices')->fetchColumn();

// Chart data (last 6 months)
$stmt = $pdo->prepare("SELECT DATE_FORMAT(created_at, '%Y-%m') as period, COUNT(*) as invoices_count, IFNULL(SUM(grand_total),0) as revenue FROM invoices WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY period ORDER BY period");
$stmt->execute();
$chartRows = $stmt->fetchAll();
$chartLabels = [];
$chartInvoices = [];
$chartRevenue = [];
foreach ($chartRows as $r) {
    $chartLabels[] = $r['period'];
    $chartInvoices[] = (int)$r['invoices_count'];
    $chartRevenue[] = (float)$r['revenue'];
}

// User search & pagination
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

if ($search !== '') {
    $stmt = $pdo->prepare("SELECT id, username, role, created_at FROM users WHERE username LIKE ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
    // Bind types explicitly to avoid numeric values being quoted
    $stmt->bindValue(1, "%$search%", PDO::PARAM_STR);
    $stmt->bindValue(2, (int)$perPage, PDO::PARAM_INT);
    $stmt->bindValue(3, (int)$offset, PDO::PARAM_INT);
    $stmt->execute();
    $users = $stmt->fetchAll();

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username LIKE ?");
    $countStmt->execute(["%$search%"]);
    $totalMatched = (int)$countStmt->fetchColumn();
} else {
    $stmt = $pdo->prepare("SELECT id, username, role, created_at FROM users ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $stmt->bindValue(1, (int)$perPage, PDO::PARAM_INT);
    $stmt->bindValue(2, (int)$offset, PDO::PARAM_INT);
    $stmt->execute();
    $users = $stmt->fetchAll();

    $totalMatched = $totalUsers;
}

$totalPages = (int)ceil($totalMatched / $perPage);

// Fetch invoices (recent)
$stmt = $pdo->query("SELECT * FROM invoices ORDER BY created_at DESC LIMIT 50");
$invoices = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Panel - Auto Shop</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <?php include __DIR__ . '/../partials/sidebar.php'; ?>


    <div class="container mx-auto p-6 ml-0 md:ml-64">
        <!-- Analytics cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-white p-4 rounded shadow flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Users</p>
                    <p class="text-2xl font-bold"><?php echo number_format($totalUsers); ?></p>
                </div>
                <div class="text-green-500 font-bold text-xl">●</div>
            </div>
            <div class="bg-white p-4 rounded shadow flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Invoices</p>
                    <p class="text-2xl font-bold"><?php echo number_format($totalInvoices); ?></p>
                </div>
                <div class="text-blue-500 font-bold text-xl">●</div>
            </div>
            <div class="bg-white p-4 rounded shadow flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Revenue</p>
                    <p class="text-2xl font-bold"><?php echo number_format($totalRevenue, 2); ?> ₾</p>
                </div>
                <div class="text-yellow-500 font-bold text-xl">●</div>
            </div>
        </div>

        <!-- Chart -->
        <div class="bg-white p-4 rounded shadow mb-6">
            <div class="flex justify-between items-center mb-2">
                <h3 class="text-lg font-bold">Revenue (last 6 months)</h3>
                <div>
                    <a href="permissions.php" class="px-3 py-2 bg-gray-200 rounded">Roles & Permissions</a>
                </div>
            </div>
            <canvas id="revenueChart" height="100"></canvas>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            const labels = <?php echo json_encode($chartLabels); ?>;
            const revenueData = <?php echo json_encode($chartRevenue); ?>;
            const invoicesData = <?php echo json_encode($chartInvoices); ?>;

            const ctx = document.getElementById('revenueChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        { label: 'Revenue ₾', data: revenueData, backgroundColor: 'rgba(234,179,8,0.9)' },
                        { label: 'Invoices', data: invoicesData, backgroundColor: 'rgba(59,130,246,0.8)' }
                    ]
                },
                options: {
                    responsive: true,
                    interaction: { mode: 'index', intersect: false },
                    scales: { y: { beginAtZero: true } }
                }
            });
        </script>

        <div class="flex flex-col md:flex-row gap-6">
            <div class="md:w-1/2">
                <div class="bg-white p-4 rounded-lg shadow-md mb-4">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-bold">Users</h3>
                        <form method="get" class="flex items-center gap-2">
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search username" class="px-3 py-2 border rounded">
                            <button type="submit" class="px-3 py-2 bg-gray-200 rounded">Search</button>
                        </form>
                    </div>

                    <?php if (isset($success)) echo "<p class='text-green-500 mb-4'>$success</p>"; ?>

                    <form method="post" class="bg-gray-50 p-4 rounded mb-4">
                        <h4 class="font-semibold mb-2">Create New User</h4>
                        <div class="grid grid-cols-3 gap-2">
                            <input type="text" name="username" placeholder="Username" class="px-2 py-2 border rounded" required>
                            <input type="password" name="password" placeholder="Password" class="px-2 py-2 border rounded" required>
                            <select name="role" class="px-2 py-2 border rounded" required>
                                <option value="user">User</option>
                                <option value="manager">Manager</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <button type="submit" name="create_user" class="mt-3 bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-500">Create</button>
                    </form>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="text-left px-2 py-2">ID</th>
                                    <th class="text-left px-2 py-2">Username</th>
                                    <th class="text-left px-2 py-2">Role</th>
                                    <th class="text-left px-2 py-2">Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                <tr class="border-t">
                                    <td class="px-2 py-2"><?php echo $user['id']; ?></td>
                                    <td class="px-2 py-2"><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td class="px-2 py-2"><?php echo $user['role']; ?></td>
                                    <td class="px-2 py-2"><?php echo $user['created_at']; ?></td>
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

            <div class="md:w-1/2">
                <div class="bg-white p-4 rounded-lg shadow-md mb-4">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-bold">Recent Invoices</h3>
                        <div class="flex items-center gap-2">
                            <a href="export_invoices.php" class="px-3 py-2 bg-gray-200 rounded">Export CSV</a>
                            <a href="logs.php" class="px-3 py-2 bg-gray-200 rounded">View Logs</a>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="text-left px-2 py-2">ID</th>
                                    <th class="text-left px-2 py-2">Customer</th>
                                    <th class="text-left px-2 py-2">Total</th>
                                    <th class="text-left px-2 py-2">Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($invoices as $invoice): ?>
                                <tr class="border-t">
                                    <td class="px-2 py-2"><?php echo $invoice['id']; ?></td>
                                    <td class="px-2 py-2"><?php echo htmlspecialchars($invoice['customer_name']); ?></td>
                                    <td class="px-2 py-2"><?php echo $invoice['grand_total']; ?> ₾</td>
                                    <td class="px-2 py-2"><?php echo $invoice['created_at']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>