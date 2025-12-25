<?php
require '../config.php';

if (!isset($_SESSION['user_id']) || !currentUserCan('manage_customers')) { // Using same permission as customers for now
    header('Location: ../login.php');
    exit;
}

// Check if vehicle tables exist
$tablesExist = true;
$requiredTables = ['vehicle_types', 'vehicle_makes', 'vehicle_models'];
foreach ($requiredTables as $table) {
    $tbl = $pdo->query("SHOW TABLES LIKE '$table'")->fetch();
    if (!$tbl) {
        $tablesExist = false;
        break;
    }
}

if (!$tablesExist) {
    echo '<div style="max-width:800px;margin:40px auto;padding:20px;background:#fff;border:1px solid #eee;border-radius:8px;">
            <h2 style="margin-top:0">Vehicle database tables not found</h2>
            <p>The required tables do not exist. Run the population script to create and populate them:</p>
            <p><a href="../populate_vehicle_db.php" style="display:inline-block;padding:8px 12px;background:#2563eb;color:white;border-radius:6px;text-decoration:none;">Run population script</a>
            <small style="display:block;margin-top:8px;color:#666">This will create the vehicle tables and populate them with data from the Car2DB API.</small></p>
          </div>';
    exit;
}

// List + search
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50; // More items per page since it's reference data
$offset = ($page - 1) * $perPage;

$sort = $_GET['sort'] ?? 'type_name';
$order = strtoupper($_GET['order'] ?? 'ASC');

// Validate sort
$validSorts = ['type_name', 'make_name', 'model_name'];
if (!in_array($sort, $validSorts)) $sort = 'type_name';
if (!in_array($order, ['ASC', 'DESC'])) $order = 'ASC';

$orderBy = "$sort $order";

$query = "
    SELECT 
        vt.name as type_name,
        vm.name as make_name,
        vmo.name as model_name,
        vt.id as type_id,
        vm.id as make_id,
        vmo.id as model_id
    FROM vehicle_models vmo
    JOIN vehicle_makes vm ON vmo.make_id = vm.id
    JOIN vehicle_types vt ON vm.type_id = vt.id
";

$whereClause = "";
$params = [];

if ($search) {
    $whereClause = " WHERE vt.name LIKE ? OR vm.name LIKE ? OR vmo.name LIKE ?";
    $params = ["%$search%", "%$search%", "%$search%"];
}

$query .= $whereClause . " ORDER BY $orderBy LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total count
$countQuery = "SELECT COUNT(*) FROM vehicle_models vmo JOIN vehicle_makes vm ON vmo.make_id = vm.id JOIN vehicle_types vt ON vm.type_id = vt.id" . $whereClause;
$countStmt = $pdo->prepare($countQuery);
$countStmt->execute(array_slice($params, 0, -2)); // Remove LIMIT and OFFSET params
$total = (int)$countStmt->fetchColumn();
$totalPages = ceil($total / $perPage);

// Get summary stats
$stats = $pdo->query("
    SELECT 
        COUNT(DISTINCT vt.id) as types_count,
        COUNT(DISTINCT vm.id) as makes_count,
        COUNT(DISTINCT vmo.id) as models_count
    FROM vehicle_types vt
    LEFT JOIN vehicle_makes vm ON vm.type_id = vt.id
    LEFT JOIN vehicle_models vmo ON vmo.make_id = vm.id
")->fetch(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="ka" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicle Database - Auto Shop Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://web-fonts.ge/bpg-arial/" />
    <link rel="stylesheet" href="https://web-fonts.ge/bpg-arial-caps/" />
    <style>
        body { font-family: 'BPG Arial', 'BPG Arial Caps', sans-serif; }
        .sort-link { transition: all 0.2s; }
        .sort-link:hover { background-color: #f3f4f6; }
        .sort-link.active { background-color: #dbeafe; font-weight: 600; }
    </style>
</head>
<body class="h-full bg-gray-50">
    <?php include '../partials/sidebar.php'; ?>

    <div class="md:pl-64 flex flex-col flex-1">
        <main class="flex-1">
            <div class="py-6">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
                    <nav class="flex mb-4" aria-label="Breadcrumb">
                        <ol class="inline-flex items-center space-x-1 md:space-x-3">
                            <li class="inline-flex items-center">
                                <a href="../create.php" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-blue-600">
                                    <svg class="w-3 h-3 mr-2.5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                        <path d="m19.707 9.293-2-2-7-7a1 1 0 0 0-1.414 0l-7 7-2 2A1 1 0 0 0 1 10h2v8a1 1 0 0 0 1 1h4a1 1 0 0 0 1-1v-4a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v4a1 1 0 0 0 1 1h4a1 1 0 0 0 1-1v-8h2a1 1 0 0 0 .707-1.707Z"/>
                                    </svg>
                                    ადმინისტრირება
                                </a>
                            </li>
                            <li>
                                <div class="flex items-center">
                                    <svg class="w-3 h-3 text-gray-400 mx-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7"/>
                                    </svg>
                                    <span class="ml-1 text-sm font-medium text-gray-500 md:ml-2">ავტომობილების მონაცემთა ბაზა</span>
                                </div>
                            </li>
                        </ol>
                    </nav>
                    <header class="mb-8">
                        <h1 class="text-4xl font-bold text-gray-900 flex items-center">
                            <svg class="w-10 h-10 mr-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                            </svg>
                            ავტომობილების მონაცემთა ბაზა
                        </h1>
                        <p class="mt-2 text-gray-600">მართეთ ავტომობილების ტიპები, მარკები და მოდელები Car2DB API-დან.</p>
                    </header>

                    <div class="h-full overflow-hidden">
                        <a href="../create.php" class="text-blue-500 hover:underline p-4 md:p-6 inline-block">&larr; უკან</a>

                        <!-- Stats Cards -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6 px-4 md:px-6">
                            <div class="bg-white overflow-hidden shadow rounded-lg">
                                <div class="p-5">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0">
                                            <svg class="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                            </svg>
                                        </div>
                                        <div class="ml-5 w-0 flex-1">
                                            <dl>
                                                <dt class="text-sm font-medium text-gray-500 truncate">ტიპები</dt>
                                                <dd class="text-lg font-medium text-gray-900"><?php echo number_format($stats['types_count']); ?></dd>
                                            </dl>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-white overflow-hidden shadow rounded-lg">
                                <div class="p-5">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0">
                                            <svg class="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                            </svg>
                                        </div>
                                        <div class="ml-5 w-0 flex-1">
                                            <dl>
                                                <dt class="text-sm font-medium text-gray-500 truncate">მარკები</dt>
                                                <dd class="text-lg font-medium text-gray-900"><?php echo number_format($stats['makes_count']); ?></dd>
                                            </dl>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-white overflow-hidden shadow rounded-lg">
                                <div class="p-5">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0">
                                            <svg class="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                            </svg>
                                        </div>
                                        <div class="ml-5 w-0 flex-1">
                                            <dl>
                                                <dt class="text-sm font-medium text-gray-500 truncate">მოდელები</dt>
                                                <dd class="text-lg font-medium text-gray-900"><?php echo number_format($stats['models_count']); ?></dd>
                                            </dl>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Search and Filters -->
                        <div class="bg-white p-4 md:p-6 rounded-xl shadow-xl mx-4 md:mx-6 mb-4">
                            <div class="flex flex-col sm:flex-row gap-4 items-center justify-between mb-6">
                                <h2 class="text-2xl font-bold text-gray-800">ავტომობილების სია</h2>
                                <div class="flex flex-col sm:flex-row gap-4 w-full sm:w-auto">
                                    <form method="get" class="flex gap-2 w-full sm:w-auto">
                                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="მოძებნეთ ტიპი, მარკა ან მოდელი..." class="flex-1 sm:w-64 px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <!-- Table -->
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'type_name', 'order' => $sort === 'type_name' && $order === 'ASC' ? 'DESC' : 'ASC'])); ?>" class="sort-link <?php echo $sort === 'type_name' ? 'active' : ''; ?> inline-flex items-center px-2 py-1 rounded">
                                                    ტიპი
                                                    <?php if ($sort === 'type_name'): ?>
                                                        <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?php echo $order === 'ASC' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7'; ?>"/>
                                                        </svg>
                                                    <?php endif; ?>
                                                </a>
                                            </th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'make_name', 'order' => $sort === 'make_name' && $order === 'ASC' ? 'DESC' : 'ASC'])); ?>" class="sort-link <?php echo $sort === 'make_name' ? 'active' : ''; ?> inline-flex items-center px-2 py-1 rounded">
                                                    მარკა
                                                    <?php if ($sort === 'make_name'): ?>
                                                        <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?php echo $order === 'ASC' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7'; ?>"/>
                                                        </svg>
                                                    <?php endif; ?>
                                                </a>
                                            </th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'model_name', 'order' => $sort === 'model_name' && $order === 'ASC' ? 'DESC' : 'ASC'])); ?>" class="sort-link <?php echo $sort === 'model_name' ? 'active' : ''; ?> inline-flex items-center px-2 py-1 rounded">
                                                    მოდელი
                                                    <?php if ($sort === 'model_name'): ?>
                                                        <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?php echo $order === 'ASC' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7'; ?>"/>
                                                        </svg>
                                                    <?php endif; ?>
                                                </a>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php if (empty($vehicles)): ?>
                                        <tr>
                                            <td colspan="3" class="px-6 py-4 text-center text-gray-500">
                                                <?php if ($search): ?>
                                                    არ მოიძებნა ავტომობილები საძიებო კრიტერიუმით "<?php echo htmlspecialchars($search); ?>"
                                                <?php else: ?>
                                                    ავტომობილების მონაცემები არ არის ჩატვირთული. გაუშვით პოპულაციის სკრიპტი.
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php else: ?>
                                        <?php foreach ($vehicles as $vehicle): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($vehicle['type_name']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo htmlspecialchars($vehicle['make_name']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo htmlspecialchars($vehicle['model_name']); ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <?php if ($totalPages > 1): ?>
                            <nav class="mt-6 flex flex-wrap gap-2 items-center justify-center" aria-label="Pagination">
                                <?php
                                $searchParam = $search ? '&search=' . urlencode($search) : '';
                                $sortParam = '&sort=' . urlencode($sort) . '&order=' . urlencode($order);
                                $prevPage = $page - 1;
                                $nextPage = $page + 1;
                                ?>
                                <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $prevPage; ?><?php echo $searchParam . $sortParam; ?>" class="inline-flex items-center px-3 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 transition" aria-label="Go to previous page">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                                    </svg>
                                    წინა
                                </a>
                                <?php endif; ?>

                                <?php if ($totalPages <= 7): ?>
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <a href="?page=<?php echo $i; ?><?php echo $searchParam . $sortParam; ?>" class="inline-flex items-center px-3 py-2 border rounded-md text-sm font-medium transition <?php echo $i === $page ? 'bg-blue-600 text-white border-blue-600' : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'; ?>" aria-label="Go to page <?php echo $i; ?>" <?php echo $i === $page ? 'aria-current="page"' : ''; ?>><?php echo $i; ?></a>
                                    <?php endfor; ?>
                                <?php else: ?>
                                    <a href="?page=1<?php echo $searchParam . $sortParam; ?>" class="inline-flex items-center px-3 py-2 border rounded-md text-sm font-medium transition <?php echo 1 === $page ? 'bg-blue-600 text-white border-blue-600' : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'; ?>" aria-label="Go to page 1" <?php echo 1 === $page ? 'aria-current="page"' : ''; ?>>1</a>
                                    <?php if ($page > 4): ?>
                                    <span class="px-2 text-gray-500" aria-hidden="true">...</span>
                                    <?php endif; ?>
                                    <?php
                                    $start = max(2, $page - 1);
                                    $end = min($totalPages - 1, $page + 1);
                                    for ($i = $start; $i <= $end; $i++):
                                    ?>
                                    <a href="?page=<?php echo $i; ?><?php echo $searchParam . $sortParam; ?>" class="inline-flex items-center px-3 py-2 border rounded-md text-sm font-medium transition <?php echo $i === $page ? 'bg-blue-600 text-white border-blue-600' : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'; ?>" aria-label="Go to page <?php echo $i; ?>" <?php echo $i === $page ? 'aria-current="page"' : ''; ?>><?php echo $i; ?></a>
                                    <?php endfor; ?>
                                    <?php if ($page < $totalPages - 3): ?>
                                    <span class="px-2 text-gray-500" aria-hidden="true">...</span>
                                    <?php endif; ?>
                                    <a href="?page=<?php echo $totalPages; ?><?php echo $searchParam . $sortParam; ?>" class="inline-flex items-center px-3 py-2 border rounded-md text-sm font-medium transition <?php echo $totalPages === $page ? 'bg-blue-600 text-white border-blue-600' : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'; ?>" aria-label="Go to page <?php echo $totalPages; ?>" <?php echo $totalPages === $page ? 'aria-current="page"' : ''; ?>><?php echo $totalPages; ?></a>
                                <?php endif; ?>

                                <?php if ($page < $totalPages): ?>
                                <a href="?page=<?php echo $nextPage; ?><?php echo $searchParam . $sortParam; ?>" class="inline-flex items-center px-3 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 transition" aria-label="Go to next page">
                                    შემდეგი
                                    <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </a>
                                <?php endif; ?>
                            </nav>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>