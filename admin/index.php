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

// Customer Analytics
$totalCustomers = (int)$pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn();
$newCustomersThisMonth = (int)$pdo->query("SELECT COUNT(*) FROM customers WHERE DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')")->fetchColumn();
$avgRevenuePerCustomer = $totalCustomers > 0 ? $totalRevenue / $totalCustomers : 0;

// Top customers by revenue
$topCustomers = $pdo->query("
    SELECT c.full_name, c.phone, c.plate_number, c.car_mark, 
           COUNT(i.id) as total_invoices, 
           IFNULL(SUM(i.grand_total), 0) as total_spent,
           MAX(i.created_at) as last_visit
    FROM customers c 
    LEFT JOIN invoices i ON c.id = i.customer_id 
    GROUP BY c.id, c.full_name, c.phone, c.plate_number, c.car_mark 
    ORDER BY total_spent DESC 
    LIMIT 10
")->fetchAll();

// Customer retention (customers with multiple visits)
$repeatCustomers = (int)$pdo->query("
    SELECT COUNT(*) FROM (
        SELECT customer_id, COUNT(*) as visit_count 
        FROM invoices 
        WHERE customer_id IS NOT NULL 
        GROUP BY customer_id 
        HAVING visit_count > 1
    ) as repeat_customers
")->fetchColumn();

// Popular car brands
$popularCarBrands = $pdo->query("
    SELECT car_mark, COUNT(*) as count 
    FROM customers 
    WHERE car_mark IS NOT NULL AND car_mark != '' 
    GROUP BY car_mark 
    ORDER BY count DESC 
    LIMIT 10
")->fetchAll();

// Customer acquisition over time (last 6 months)
$customerAcquisition = $pdo->prepare("
    SELECT DATE_FORMAT(created_at, '%Y-%m') as period, COUNT(*) as new_customers 
    FROM customers 
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) 
    GROUP BY period 
    ORDER BY period
");
$customerAcquisition->execute();
$customerChartData = $customerAcquisition->fetchAll();

// Chart data (last 6 months)
$stmt = $pdo->prepare("SELECT DATE_FORMAT(created_at, '%Y-%m') as period, COUNT(*) as invoices_count, IFNULL(SUM(grand_total),0) as revenue FROM invoices WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY period ORDER BY period");
$stmt->execute();
$chartRows = $stmt->fetchAll();

// Create a map of periods for merging data
$periods = [];
$chartLabels = [];
$chartInvoices = [];
$chartRevenue = [];
$chartCustomers = [];

// Initialize with invoice data
foreach ($chartRows as $r) {
    $periods[$r['period']] = [
        'invoices' => (int)$r['invoices_count'],
        'revenue' => (float)$r['revenue'],
        'customers' => 0
    ];
}

// Add customer acquisition data
foreach ($customerChartData as $c) {
    if (!isset($periods[$c['period']])) {
        $periods[$c['period']] = [
            'invoices' => 0,
            'revenue' => 0,
            'customers' => 0
        ];
    }
    $periods[$c['period']]['customers'] = (int)$c['new_customers'];
}

// Sort periods and create chart arrays
ksort($periods);
foreach ($periods as $period => $data) {
    $chartLabels[] = $period;
    $chartInvoices[] = $data['invoices'];
    $chartRevenue[] = $data['revenue'];
    $chartCustomers[] = $data['customers'];
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
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ადმინისტრატორის პანელი - ავტო სერვისი</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Georgian:wght@300;400;500;600;700&display=swap" rel="stylesheet>
    <link rel="stylesheet" href="https://web-fonts.ge/bpg-arial/" />
    <link rel="stylesheet" href="https://web-fonts.ge/bpg-arial-caps/" />
    <style>
        body { font-family: 'Noto Sans Georgian', 'BPG Arial', 'BPG Arial Caps', sans-serif; }
        .fade-in { animation: fadeIn 0.3s ease-in; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    </style>
</head>
<body class="bg-gray-100 min-h-screen overflow-auto font-sans antialiased pb-20">
    <?php include __DIR__ . '/../partials/sidebar.php'; ?>


    <div class="min-h-full overflow-auto ml-0 md:ml-64 pt-4 pl-4">
        <div class="h-full overflow-auto p-4 md:p-6">
        <!-- Analytics cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-white p-4 rounded shadow flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">მომხმარებლები</p>
                    <p class="text-2xl font-bold"><?php echo number_format($totalUsers); ?></p>
                </div>
                <div class="text-green-500 font-bold text-xl">●</div>
            </div>
            <div class="bg-white p-4 rounded shadow flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">ინვოისები</p>
                    <p class="text-2xl font-bold"><?php echo number_format($totalInvoices); ?></p>
                </div>
                <div class="text-blue-500 font-bold text-xl">●</div>
            </div>
            <div class="bg-white p-4 rounded shadow flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">შემოსავალი</p>
                    <p class="text-2xl font-bold"><?php echo number_format($totalRevenue, 2); ?> ₾</p>
                </div>
                <div class="text-yellow-500 font-bold text-xl">●</div>
            </div>
        </div>

        <!-- Customer Analytics cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-white p-4 rounded shadow flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">სულ კლიენტები</p>
                    <p class="text-2xl font-bold"><?php echo number_format($totalCustomers); ?></p>
                </div>
                <div class="text-purple-500 font-bold text-xl">●</div>
            </div>
            <div class="bg-white p-4 rounded shadow flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">ახალი კლიენტები (ამ თვეში)</p>
                    <p class="text-2xl font-bold"><?php echo number_format($newCustomersThisMonth); ?></p>
                </div>
                <div class="text-indigo-500 font-bold text-xl">●</div>
            </div>
            <div class="bg-white p-4 rounded shadow flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">საშუალო შემოსავალი კლიენტზე</p>
                    <p class="text-2xl font-bold"><?php echo number_format($avgRevenuePerCustomer, 2); ?> ₾</p>
                </div>
                <div class="text-pink-500 font-bold text-xl">●</div>
            </div>
        </div>

        <!-- Chart -->
        <div class="bg-white p-4 rounded shadow mb-6">
            <div class="flex justify-between items-center mb-2">
                <h3 class="text-lg font-bold">ბიზნესის მიმოხილვა (ბოლო 6 თვე)</h3>
                <div>
                    <a href="permissions.php" class="px-3 py-2 bg-gray-200 rounded">როლები და უფლებები</a>
                </div>
            </div>
            <canvas id="revenueChart" height="100"></canvas>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            const labels = <?php echo json_encode($chartLabels); ?>;
            const revenueData = <?php echo json_encode($chartRevenue); ?>;
            const invoicesData = <?php echo json_encode($chartInvoices); ?>;
            const customersData = <?php echo json_encode($chartCustomers); ?>;

            const ctx = document.getElementById('revenueChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        { label: 'Revenue ₾', data: revenueData, backgroundColor: 'rgba(234,179,8,0.9)', yAxisID: 'y' },
                        { label: 'Invoices', data: invoicesData, backgroundColor: 'rgba(59,130,246,0.8)', yAxisID: 'y' },
                        { label: 'New Customers', data: customersData, backgroundColor: 'rgba(139,92,246,0.7)', yAxisID: 'y1' }
                    ]
                },
                options: {
                    responsive: true,
                    interaction: { mode: 'index', intersect: false },
                    scales: {
                        y: { beginAtZero: true, position: 'left' },
                        y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false } }
                    }
                }
            });
        </script>

        <!-- Customer Analytics Sections -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <!-- Top Customers by Revenue -->
            <div class="bg-white p-4 rounded shadow">
                <h3 class="text-lg font-bold mb-4">ტოპ კლიენტები შემოსავლით</h3>
                <div class="space-y-3">
                    <?php foreach ($topCustomers as $index => $customer): ?>
                        <div class="flex justify-between items-center p-3 bg-gray-50 rounded">
                            <div>
                                <p class="font-semibold"><?php echo htmlspecialchars($customer['full_name'] ?: 'N/A'); ?></p>
                                <p class="text-sm text-gray-600"><?php echo htmlspecialchars($customer['plate_number']); ?> • <?php echo htmlspecialchars($customer['car_mark'] ?: 'N/A'); ?></p>
                                <p class="text-xs text-gray-500"><?php echo $customer['total_invoices']; ?> visits • Last: <?php echo $customer['last_visit'] ? date('M j, Y', strtotime($customer['last_visit'])) : 'N/A'; ?></p>
                            </div>
                            <div class="text-right">
                                <p class="font-bold text-green-600"><?php echo number_format($customer['total_spent'], 2); ?> ₾</p>
                                <p class="text-xs text-gray-500">#<?php echo $index + 1; ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($topCustomers)): ?>
                        <p class="text-gray-500 text-center py-4">No customer data available</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Popular Car Brands -->
            <div class="bg-white p-4 rounded shadow">
                <h3 class="text-lg font-bold mb-4">პოპულარული ავტომობილების მარკები</h3>
                <div class="space-y-3">
                    <?php foreach ($popularCarBrands as $brand): ?>
                        <div class="flex justify-between items-center p-3 bg-gray-50 rounded">
                            <span class="font-semibold"><?php echo htmlspecialchars($brand['car_mark']); ?></span>
                            <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-sm"><?php echo $brand['count']; ?> კლიენტი</span>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($popularCarBrands)): ?>
                        <p class="text-gray-500 text-center py-4">ავტომობილების მარკების მონაცემები არ არის ხელმისაწვდომი</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Customer Retention Stats -->
        <div class="bg-white p-4 rounded shadow mb-6">
            <h3 class="text-lg font-bold mb-4">კლიენტების დაბრუნება</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="text-center">
                    <p class="text-3xl font-bold text-green-600"><?php echo number_format($repeatCustomers); ?></p>
                    <p class="text-sm text-gray-600">მეორედ მომსახურე კლიენტები</p>
                    <p class="text-xs text-gray-500">კლიენტები 2+ ვიზიტით</p>
                </div>
                <div class="text-center">
                    <p class="text-3xl font-bold text-blue-600"><?php echo $totalCustomers > 0 ? round(($repeatCustomers / $totalCustomers) * 100, 1) : 0; ?>%</p>
                    <p class="text-sm text-gray-600">დაბრუნების მაჩვენებელი</p>
                    <p class="text-xs text-gray-500">მეორედ მომსახურე კლიენტები / სულ კლიენტები</p>
                </div>
                <div class="text-center">
                    <p class="text-3xl font-bold text-purple-600"><?php echo $totalCustomers > 0 ? round($totalInvoices / $totalCustomers, 1) : 0; ?></p>
                    <p class="text-sm text-gray-600">საშუალო ვიზიტები კლიენტზე</p>
                    <p class="text-xs text-gray-500">სულ ინვოისები / სულ კლიენტები</p>
                </div>
            </div>
        </div>

        <div class="flex flex-col md:flex-row gap-6">
            <div class="md:w-1/2">
                <div class="bg-white p-4 rounded-lg shadow-md mb-4">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-bold">მომხმარებლები</h3>
                        <form method="get" class="flex items-center gap-2">
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="მოძებნეთ მომხმარებლის სახელი" class="px-3 py-2 border rounded">
                            <button type="submit" class="px-3 py-2 bg-gray-200 rounded">ძიება</button>
                        </form>
                    </div>

                    <?php if (isset($success)) echo "<p class='text-green-500 mb-4'>$success</p>"; ?>

                    <form method="post" class="bg-gray-50 p-4 rounded mb-4">
                        <h4 class="font-semibold mb-2">ახალი მომხმარებლის შექმნა</h4>
                        <div class="grid grid-cols-3 gap-2">
                            <input type="text" name="username" placeholder="მომხმარებლის სახელი" class="px-2 py-2 border rounded" required>
                            <input type="password" name="password" placeholder="პაროლი" class="px-2 py-2 border rounded" required>
                            <select name="role" class="px-2 py-2 border rounded" required>
                                <option value="user">მომხმარებელი</option>
                                <option value="manager">მენეჯერი</option>
                                <option value="admin">ადმინისტრატორი</option>
                            </select>
                        </div>
                        <button type="submit" name="create_user" class="mt-3 bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-500">შექმნა</button>
                    </form>

                    <div class="overflow-x-auto">
                        <table class="w-full text-xs sm:text-sm min-w-[400px]">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="text-left px-2 md:px-4 py-2">ID</th>
                                    <th class="text-left px-2 md:px-4 py-2">მომხმარებლის სახელი</th>
                                    <th class="text-left px-2 md:px-4 py-2">როლი</th>
                                    <th class="text-left px-2 md:px-4 py-2">შექმნილია</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                <tr class="border-t hover:bg-gray-50">
                                    <td class="px-2 md:px-4 py-2"><?php echo $user['id']; ?></td>
                                    <td class="px-2 md:px-4 py-2 truncate max-w-[120px]"><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td class="px-2 md:px-4 py-2"><?php echo $user['role']; ?></td>
                                    <td class="px-2 md:px-4 py-2 truncate max-w-[140px]"><?php echo $user['created_at']; ?></td>
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
                        <h3 class="text-lg font-bold">ბოლო ინვოისები</h3>
                        <div class="flex items-center gap-2">
                            <a href="export_invoices.php" class="px-3 py-2 bg-gray-200 rounded">CSV ექსპორტი</a>
                            <a href="logs.php" class="px-3 py-2 bg-gray-200 rounded">ლოგების ნახვა</a>
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
    </div>
</body>
</html>