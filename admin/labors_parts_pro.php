<?php
require '../config.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin','manager'])) {
    header('Location: ../login.php');
    exit;
}

// Create CSRF token
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(16));

$errors = [];
$success = null;

// sanitize helpers
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

// Handle POST actions (add/edit/delete) with CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $errors[] = 'Invalid CSRF token';
    } else {
        $action = $_POST['action'] ?? '';
        $type = $_POST['type'] ?? '';
        $table = $type === 'part' ? 'parts' : 'labors';

        try {
            if ($action === 'add') {
                $name = trim($_POST['name'] ?? '');
                $desc = trim($_POST['description'] ?? '');
                $price = (float)($_POST['default_price'] ?? 0);
                $vehicle = trim($_POST['vehicle_make_model'] ?? '');
                if ($name === '') throw new Exception('Name is required');
                $stmt = $pdo->prepare("INSERT INTO {$table} (name, description, default_price, created_by, vehicle_make_model) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$name, $desc, $price, $_SESSION['user_id'], $vehicle ?: NULL]);
                $success = ucfirst($type) . ' created';
            } elseif ($action === 'edit') {
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) throw new Exception('Invalid id');
                $name = trim($_POST['name'] ?? '');
                $desc = trim($_POST['description'] ?? '');
                $price = (float)($_POST['default_price'] ?? 0);
                $vehicle = trim($_POST['vehicle_make_model'] ?? '');
                if ($name === '') throw new Exception('Name is required');
                $stmt = $pdo->prepare("UPDATE {$table} SET name = ?, description = ?, default_price = ?, vehicle_make_model = ? WHERE id = ?");
                $stmt->execute([$name, $desc, $price, $vehicle ?: NULL, $id]);
                $success = ucfirst($type) . ' updated';
            } elseif ($action === 'delete') {
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) throw new Exception('Invalid id');
                $stmt = $pdo->prepare("DELETE FROM {$table} WHERE id = ?");
                $stmt->execute([$id]);
                $success = ucfirst($type) . ' deleted';
            }
            // Oil-specific actions
            elseif ($action === 'add_brand') {
                $name = trim($_POST['brand_name'] ?? '');
                if ($name === '') throw new Exception('Brand name is required');
                $stmt = $pdo->prepare("INSERT INTO oil_brands (name) VALUES (?)");
                $stmt->execute([$name]);
                $success = 'Oil brand added';
            } elseif ($action === 'add_viscosity') {
                $viscosity = trim($_POST['viscosity'] ?? '');
                $description = trim($_POST['description'] ?? '');
                if ($viscosity === '') throw new Exception('Viscosity is required');
                $stmt = $pdo->prepare("INSERT INTO oil_viscosities (viscosity, description) VALUES (?, ?)");
                $stmt->execute([$viscosity, $description]);
                $success = 'Oil viscosity added';
            } elseif ($action === 'add_price') {
                $brand_id = (int)($_POST['brand_id'] ?? 0);
                $viscosity_id = (int)($_POST['viscosity_id'] ?? 0);
                $package_type = $_POST['package_type'] ?? '';
                $price = (float)($_POST['price'] ?? 0);
                if ($brand_id <= 0 || $viscosity_id <= 0 || !$package_type || $price <= 0) {
                    throw new Exception('All fields are required');
                }
                $stmt = $pdo->prepare("INSERT INTO oil_prices (brand_id, viscosity_id, package_type, price, created_by) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE price = VALUES(price)");
                $stmt->execute([$brand_id, $viscosity_id, $package_type, $price, $_SESSION['user_id']]);
                $success = 'Oil price set';
            } elseif ($action === 'delete_brand') {
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) throw new Exception('Invalid brand id');
                $stmt = $pdo->prepare("DELETE FROM oil_brands WHERE id = ?");
                $stmt->execute([$id]);
                $success = 'Oil brand deleted';
            } elseif ($action === 'delete_viscosity') {
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) throw new Exception('Invalid viscosity id');
                $stmt = $pdo->prepare("DELETE FROM oil_viscosities WHERE id = ?");
                $stmt->execute([$id]);
                $success = 'Oil viscosity deleted';
            } elseif ($action === 'delete_price') {
                $brand_id = (int)($_POST['brand_id'] ?? 0);
                $viscosity_id = (int)($_POST['viscosity_id'] ?? 0);
                $package_type = $_POST['package_type'] ?? '';
                if ($brand_id <= 0 || $viscosity_id <= 0 || !$package_type) {
                    throw new Exception('Invalid price parameters');
                }
                $stmt = $pdo->prepare("DELETE FROM oil_prices WHERE brand_id = ? AND viscosity_id = ? AND package_type = ?");
                $stmt->execute([$brand_id, $viscosity_id, $package_type]);
                $success = 'Oil price deleted';
            }
        } catch (Exception $e) {
            error_log('labors_parts_pro.php - action error: ' . $e->getMessage());
            $errors[] = $e->getMessage();
        }
    }
}

// Listing params
$type = in_array($_GET['type'] ?? 'part', ['part','labor','oil']) ? $_GET['type'] : 'part';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$q = trim($_GET['q'] ?? '');
$offset = ($page - 1) * $perPage;

$table = $type === 'part' ? 'parts' : 'labors';
$where = '';
$params = [];
if ($q !== '') {
    $where = "WHERE name LIKE :q OR description LIKE :q";
    $params[':q'] = "%{$q}%";
}

// Count total
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} {$where}");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

// Fetch current page
$sql = "SELECT id, name, description, default_price, vehicle_make_model, created_at FROM {$table} {$where} ORDER BY name LIMIT :offset, :limit";
$stmt = $pdo->prepare($sql);
foreach ($params as $k=>$v) $stmt->bindValue($k, $v, PDO::PARAM_STR);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch vehicle-specific prices for items shown on this page to display alongside the default price
$pricesByItem = [];
if (!empty($rows)) {
    $ids = array_column($rows, 'id');
    // Build placeholders for IN clause
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $priceSql = "SELECT item_id, vehicle_make_model, price FROM item_prices WHERE item_type = ? AND item_id IN ($placeholders) ORDER BY vehicle_make_model";
    $pstmt = $pdo->prepare($priceSql);
    $paramsExec = array_merge([$type === 'part' ? 'part' : 'labor'], $ids);
    $pstmt->execute($paramsExec);
    $priceRows = $pstmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($priceRows as $pr) {
        $pricesByItem[(int)$pr['item_id']][] = $pr;
    }
}

// Fetch oil data if type is oil
$oilBrands = [];
$oilViscosities = [];
$oilPrices = [];
if ($type === 'oil') {
    $oilBrands = $pdo->query("SELECT * FROM oil_brands ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $oilViscosities = $pdo->query("SELECT * FROM oil_viscosities ORDER BY viscosity")->fetchAll(PDO::FETCH_ASSOC);

    // Fetch oil prices with brand and viscosity names
    $oilPrices = $pdo->query("
        SELECT op.*, ob.name as brand_name, ov.viscosity as viscosity_name, ov.description as viscosity_description
        FROM oil_prices op
        JOIN oil_brands ob ON op.brand_id = ob.id
        JOIN oil_viscosities ov ON op.viscosity_id = ov.id
        ORDER BY ob.name, ov.viscosity, op.package_type
    ")->fetchAll(PDO::FETCH_ASSOC);
}

// helper for pagination links
function pageUrl($type,$p,$q){ return "labors_parts_pro.php?type={$type}&page={$p}" . ($q?('&q='.urlencode($q)):''); }

?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Labors & Parts — PRO</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen font-sans">
<?php include '../partials/sidebar.php'; ?>
<div class="ml-0 md:ml-64 p-6 max-w-5xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold">Labors & Parts — PRO</h1>
        <div class="flex items-center space-x-3">

            <a href="labors_parts_pro.php?type=part" class="px-3 py-2 bg-blue-600 text-white rounded">Parts</a>
            <a href="labors_parts_pro.php?type=labor" class="px-3 py-2 bg-blue-600 text-white rounded">Labors</a>
            <a href="labors_parts_pro.php?type=oil" class="px-3 py-2 bg-green-600 text-white rounded">Oils</a>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="mb-4 p-3 bg-green-100 text-green-800 rounded"><?php echo h($success); ?></div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
        <div class="mb-4 p-3 bg-red-100 text-red-800 rounded"><ul><?php foreach($errors as $err) echo '<li>'.h($err).'</li>'; ?></ul></div>
    <?php endif; ?>

    <form method="get" class="mb-4 flex items-center space-x-2">
        <input type="hidden" name="type" value="<?php echo h($type); ?>">
        <input type="text" name="q" placeholder="Search" value="<?php echo h($q); ?>" class="border p-2 rounded w-full">
        <button class="px-3 py-2 bg-gray-200 rounded">Search</button>
    </form>

    <div class="mb-4 flex items-center justify-between">
        <div class="text-sm text-gray-600">
            <?php if ($type === 'oil'): ?>
                Managing <?php echo count($oilBrands); ?> brands, <?php echo count($oilViscosities); ?> viscosities, <?php echo count($oilPrices); ?> prices
            <?php else: ?>
                Showing <?php echo count($rows); ?> of <?php echo $total; ?> <?php echo $type === 'part' ? 'parts' : 'labors'; ?>
            <?php endif; ?>
        </div>
        <div class="space-x-2">
            <a href="?type=<?php echo $type; ?>" class="px-3 py-2 bg-gray-100 rounded">Refresh</a>
            <?php if ($type !== 'oil'): ?>
            <a href="api_labors_parts.php?action=export&type=<?php echo $type === 'part' ? 'parts' : 'labors'; ?>" class="px-3 py-2 bg-gray-100 rounded">Export CSV</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($type !== 'oil'): ?>
    <!-- Quick Add -->
    <form method="post" class="mb-4 grid grid-cols-1 md:grid-cols-4 gap-2 items-end">
        <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="type" value="<?php echo h($type); ?>">
        <div>
            <label class="block text-xs text-gray-600">Name</label>
            <input type="text" name="name" class="border p-2 rounded w-full" required>
        </div>
        <div>
            <label class="block text-xs text-gray-600">Description</label>
            <input type="text" name="description" class="border p-2 rounded w-full">
        </div>
        <div>
            <label class="block text-xs text-gray-600">Price</label>
            <input type="number" step="0.01" name="default_price" class="border p-2 rounded w-full">
        </div>
        <div>
            <label class="block text-xs text-gray-600">Vehicle (optional)</label>
            <input type="text" name="vehicle_make_model" placeholder="e.g. Toyota Corolla" class="border p-2 rounded w-full">
            <div class="mt-2 text-right">
                <button class="px-3 py-2 bg-blue-600 text-white rounded">Add</button>
            </div>
        </div>
    </form>
    <?php endif; ?>

<?php if ($type !== 'oil'): ?>
    <!-- Parts/Labors Table -->

    <div class="bg-white rounded shadow overflow-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="p-2 text-left">Name</th>
                    <th class="p-2 text-left">Description</th>
                    <th class="p-2 text-left">Vehicle</th>
                    <th class="p-2 text-left">Price</th>
                    <th class="p-2">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                <tr><td colspan="4" class="p-4 text-center text-gray-500">No records</td></tr>
                <?php else: foreach ($rows as $r): ?>
                <tr>
                    <td class="p-2 font-medium"><?php echo h($r['name']); ?></td>
                    <td class="p-2"><?php echo h($r['description']); ?></td>
                    <td class="p-2"><?php echo h($r['vehicle_make_model'] ?? ''); ?></td>
                    <td class="p-2">
                        <?php echo number_format($r['default_price'], 2); ?>
                        <?php if (!empty($pricesByItem[(int)$r['id']])): ?>
                            <div class="mt-2">
                                <?php foreach ($pricesByItem[(int)$r['id']] as $pp): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-gray-100 text-gray-800 mr-1"><?php echo htmlspecialchars($pp['vehicle_make_model']); ?>: <?php echo number_format($pp['price'], 2); ?> ₾</span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td class="p-2 text-right">
                        <form method="post" class="inline" onsubmit="return confirm('Delete?');">
                            <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="type" value="<?php echo h($type); ?>">
                            <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                            <button class="text-red-600">Delete</button>
                        </form>
                        <button class="ml-3 text-indigo-600" onclick="openEdit(<?php echo (int)$r['id']; ?>, '<?php echo h(addslashes($r['name'])); ?>', '<?php echo h(addslashes($r['description'])); ?>', '<?php echo h($r['default_price']); ?>', '<?php echo h(addslashes($r['vehicle_make_model'] ?? '')); ?>')">Edit</button>
                        <button class="ml-3 text-blue-600" onclick="openManagePrices(<?php echo (int)$r['id']; ?>, '<?php echo h(addslashes($r['name'])); ?>', '<?php echo h($type); ?>')">Manage Prices</button>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?php // pagination ?>
    <?php $pages = (int)ceil($total / $perPage); if ($pages > 1): ?>
    <div class="mt-4 flex items-center space-x-2">
        <?php for ($p=1;$p<=$pages;$p++): ?>
            <a href="<?php echo pageUrl($type,$p,$q); ?>" class="px-3 py-1 <?php echo $p==$page? 'bg-blue-600 text-white rounded' : 'bg-gray-100 rounded'; ?>"><?php echo $p; ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>

<?php elseif ($type === 'oil'): ?>
    <!-- Oil Management Section -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Oil Brands -->
        <div class="bg-white rounded shadow p-4">
            <h3 class="text-lg font-semibold mb-4 text-green-700">Oil Brands</h3>

            <!-- Add Brand Form -->
            <form method="post" class="mb-4">
                <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="action" value="add_brand">
                <div class="flex gap-2">
                    <input type="text" name="brand_name" placeholder="Brand name" required class="flex-1 border p-2 rounded text-sm">
                    <button class="px-3 py-2 bg-green-600 text-white rounded text-sm">Add</button>
                </div>
            </form>

            <!-- Brands List -->
            <div class="space-y-2 max-h-64 overflow-y-auto">
                <?php foreach ($oilBrands as $brand): ?>
                <div class="flex items-center justify-between p-2 bg-gray-50 rounded">
                    <span class="text-sm font-medium"><?php echo h($brand['name']); ?></span>
                    <form method="post" class="inline" onsubmit="return confirm('Delete brand?');">
                        <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="action" value="delete_brand">
                        <input type="hidden" name="id" value="<?php echo (int)$brand['id']; ?>">
                        <button class="text-red-600 text-sm">×</button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Oil Viscosities -->
        <div class="bg-white rounded shadow p-4">
            <h3 class="text-lg font-semibold mb-4 text-blue-700">Oil Viscosities</h3>

            <!-- Add Viscosity Form -->
            <form method="post" class="mb-4">
                <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="action" value="add_viscosity">
                <div class="space-y-2">
                    <input type="text" name="viscosity" placeholder="Viscosity (e.g. 5W-30)" required class="w-full border p-2 rounded text-sm">
                    <input type="text" name="description" placeholder="Description (optional)" class="w-full border p-2 rounded text-sm">
                    <button class="w-full px-3 py-2 bg-blue-600 text-white rounded text-sm">Add Viscosity</button>
                </div>
            </form>

            <!-- Viscosities List -->
            <div class="space-y-2 max-h-64 overflow-y-auto">
                <?php foreach ($oilViscosities as $viscosity): ?>
                <div class="p-2 bg-gray-50 rounded">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium"><?php echo h($viscosity['viscosity']); ?></span>
                        <form method="post" class="inline" onsubmit="return confirm('Delete viscosity?');">
                            <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                            <input type="hidden" name="action" value="delete_viscosity">
                            <input type="hidden" name="id" value="<?php echo (int)$viscosity['id']; ?>">
                            <button class="text-red-600 text-sm">×</button>
                        </form>
                    </div>
                    <?php if ($viscosity['description']): ?>
                    <div class="text-xs text-gray-600 mt-1"><?php echo h($viscosity['description']); ?></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Oil Prices -->
        <div class="bg-white rounded shadow p-4">
            <h3 class="text-lg font-semibold mb-4 text-purple-700">Oil Prices</h3>

            <!-- Add Price Form -->
            <form method="post" class="mb-4">
                <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="action" value="add_price">
                <div class="space-y-2">
                    <select name="brand_id" required class="w-full border p-2 rounded text-sm">
                        <option value="">Select Brand</option>
                        <?php foreach ($oilBrands as $brand): ?>
                        <option value="<?php echo (int)$brand['id']; ?>"><?php echo h($brand['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="viscosity_id" required class="w-full border p-2 rounded text-sm">
                        <option value="">Select Viscosity</option>
                        <?php foreach ($oilViscosities as $viscosity): ?>
                        <option value="<?php echo (int)$viscosity['id']; ?>"><?php echo h($viscosity['viscosity']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="package_type" required class="w-full border p-2 rounded text-sm">
                        <option value="">Select Package</option>
                        <option value="canned">Canned</option>
                        <option value="1lt">1 Liter</option>
                        <option value="4lt">4 Liter</option>
                        <option value="5lt">5 Liter</option>
                    </select>
                    <input type="number" name="price" step="0.01" placeholder="Price (₾)" required class="w-full border p-2 rounded text-sm">
                    <button class="w-full px-3 py-2 bg-purple-600 text-white rounded text-sm">Set Price</button>
                </div>
            </form>

            <!-- Prices List -->
            <div class="space-y-2 max-h-64 overflow-y-auto">
                <?php foreach ($oilPrices as $price): ?>
                <div class="p-2 bg-gray-50 rounded">
                    <div class="flex items-center justify-between">
                        <div class="text-sm">
                            <span class="font-medium"><?php echo h($price['brand_name']); ?></span>
                            <span class="text-gray-600"><?php echo h($price['viscosity_name']); ?></span>
                            <span class="text-xs bg-gray-200 px-1 rounded"><?php echo h($price['package_type']); ?></span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-medium"><?php echo number_format($price['price'], 2); ?> ₾</span>
                            <form method="post" class="inline" onsubmit="return confirm('Delete price?');">
                                <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                                <input type="hidden" name="action" value="delete_price">
                                <input type="hidden" name="brand_id" value="<?php echo (int)$price['brand_id']; ?>">
                                <input type="hidden" name="viscosity_id" value="<?php echo (int)$price['viscosity_id']; ?>">
                                <input type="hidden" name="package_type" value="<?php echo h($price['package_type']); ?>">
                                <button class="text-red-600 text-sm">×</button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

<?php endif; ?>

    <!-- Add / Edit modal -->
    <div id="edit-modal" class="modal" style="display:none; position:fixed; inset:0; align-items:center; justify-content:center; background:rgba(0,0,0,0.45); z-index:60;">
        <div class="bg-white w-full max-w-lg rounded p-4">
            <h3 id="modal-title" class="font-semibold mb-2">Edit</h3>
            <form method="post" id="edit-form">
                <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="type" value="<?php echo h($type); ?>">
                <input type="hidden" name="id" id="edit-id">
                <div class="mb-2">
                    <label class="block text-sm">Name</label>
                    <input type="text" name="name" id="edit-name" required class="border p-2 w-full rounded">
                </div>
                <div class="mb-2">
                    <label class="block text-sm">Description</label>
                    <input type="text" name="description" id="edit-description" class="border p-2 w-full rounded">
                </div>
                <div class="mb-2">
                    <label class="block text-sm">Price</label>
                    <input type="number" name="default_price" id="edit-price" step="0.01" class="border p-2 w-full rounded">
                </div>
                <div class="mb-2">
                    <label class="block text-sm">Vehicle Make/Model (optional) <span class="text-xs text-gray-500"> <button type="button" title="Format: 'Make Model' - e.g. 'Toyota Corolla'" class="ml-1 text-gray-400 hover:text-gray-600">ℹ️</button></span></label>
                    <input type="text" name="vehicle_make_model" id="edit-vehicle" class="border p-2 w-full rounded" placeholder="e.g. Toyota Corolla">
                </div>
                <div class="flex justify-end space-x-2">
                    <button type="button" onclick="document.getElementById('edit-modal').style.display='none'" class="px-3 py-2 bg-gray-200 rounded">Cancel</button>
                    <button type="submit" class="px-3 py-2 bg-blue-600 text-white rounded">Save</button>
                </div>
            </form>
        </div>
    </div>

</div>

<!-- Manage Prices Modal -->
<div id="manage-prices-modal" style="display:none; position:fixed; inset:0; align-items:center; justify-content:center; background:rgba(0,0,0,0.45); z-index:70;">
    <div class="bg-white w-full max-w-2xl rounded p-4">
        <div class="flex items-center justify-between mb-3">
            <h3 id="prices-title" class="font-semibold">Manage Prices</h3>
            <button onclick="document.getElementById('manage-prices-modal').style.display='none'" class="text-gray-500">✕</button>
        </div>
        <div id="prices-content">
            <div class="text-center text-gray-500 py-6">Loading...</div>
        </div>
        <div class="mt-3 text-right">
            <button class="px-3 py-2 bg-gray-200 rounded" onclick="document.getElementById('manage-prices-modal').style.display='none'">Close</button>
        </div>
    </div>
</div>

<script>
function openEdit(id,name,desc,price,vehicle){
    document.getElementById('edit-id').value = id;
    document.getElementById('edit-name').value = name;
    document.getElementById('edit-description').value = desc;
    document.getElementById('edit-price').value = price;
    document.getElementById('edit-vehicle').value = vehicle || '';
    document.getElementById('edit-modal').style.display = 'flex';
}

function openManagePrices(id, name, itemType){
    const modal = document.getElementById('manage-prices-modal');
    modal.dataset.currentItemId = id;
    modal.dataset.currentItemType = itemType;
    document.getElementById('prices-title').textContent = 'Manage Prices — ' + name;
    modal.style.display = 'flex';
    fetchPrices(id, itemType);
}

function fetchPrices(itemId, itemType){
    const content = document.getElementById('prices-content');
    content.innerHTML = '<div class="text-center text-gray-500 py-6">Loading...</div>';
    fetch('api_labors_parts.php?action=prices&item_id=' + encodeURIComponent(itemId) + '&item_type=' + encodeURIComponent(itemType))
        .then(r => r.json())
        .then(resp => {
            if (!resp || !resp.success) throw new Error(resp && resp.message ? resp.message : 'Failed');
            renderPrices(itemId, itemType, resp.data || []);
        }).catch(e => { content.innerHTML = '<div class="text-red-500 text-center py-6">Error: '+e.message+'</div>' });
}

function renderPrices(itemId, itemType, rows){
    const content = document.getElementById('prices-content');
    let html = `
        <div class="mb-3">
            <form id="price-add-form" onsubmit="return false;" class="grid grid-cols-3 gap-2 items-end">
                <input type="text" id="price-vehicle" placeholder="Vehicle (e.g. Toyota Corolla)" class="border p-2 rounded col-span-2">
                <input type="number" id="price-value" placeholder="Price" class="border p-2 rounded">
                <div class="col-span-3 text-right mt-1"><button class="px-3 py-2 bg-blue-600 text-white rounded" onclick="addPrice(`+itemId+`, '`+itemType+`')">Add / Save</button></div>
            </form>
        </div>
        <div class="overflow-auto max-h-64">
            <table class="w-full text-sm">
                <thead class="bg-gray-50"><tr><th class="p-2 text-left">Vehicle</th><th class="p-2 text-left">Price</th><th class="p-2">Actions</th></tr></thead>
                <tbody>`;
    if (rows.length === 0) {
        html += '<tr><td colspan="3" class="p-4 text-center text-gray-500">No prices defined</td></tr>';
    } else {
        rows.forEach(r => {
            html += `<tr data-price-id="${r.id}"><td class="p-2">${r.vehicle_make_model}</td><td class="p-2">${Number(r.price).toFixed(2)} ₾</td><td class="p-2 text-right"><button class="text-indigo-600 mr-3" onclick="startEditPrice(${r.id}, '${r.vehicle_make_model.replace(/'/g, "\\'")}', ${Number(r.price)}, '${itemType}')">Edit</button><button class="text-red-600" onclick="deletePrice(${r.id}, ${itemId}, '${itemType}')">Delete</button></td></tr>`;
        });
    }
    html += `</tbody></table></div>`;
    content.innerHTML = html;
}

function addPrice(itemId, itemType){
    const vehicle = document.getElementById('price-vehicle').value.trim();
    const price = parseFloat(document.getElementById('price-value').value || 0);
    if (!vehicle || !price) return alert('Please enter vehicle and price');
    fetch('api_labors_parts.php', {
        method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({action:'price_add', item_id:itemId, item_type:itemType, vehicle_make_model:vehicle, price:price})
    }).then(r=>r.json()).then(d=>{
        if (!d || !d.success) return alert('Failed to save price: '+(d && d.message ? d.message : 'unknown'));
        // refresh modal
        fetchPrices(itemId, itemType);
        // also refresh page to show updated price badges
        setTimeout(()=>location.reload(), 600);
    }).catch(e=>alert('Error: '+e.message));
}

function startEditPrice(id, vehicle, price, itemType){
    document.getElementById('price-vehicle').value = vehicle;
    document.getElementById('price-value').value = price;
    // change add button to do edit
    const btn = document.querySelector('#price-add-form button');
    btn.onclick = function(){
        const nv = document.getElementById('price-vehicle').value.trim();
        const np = parseFloat(document.getElementById('price-value').value || 0);
        if (!nv || !np) return alert('Please enter vehicle and price');
        fetch('api_labors_parts.php', {
            method: 'POST', headers: {'Content-Type':'application/json'},
            body: JSON.stringify({action:'price_edit', id:id, vehicle_make_model:nv, price:np})
        }).then(r=>r.json()).then(d=>{ if (!d || !d.success) return alert('Failed'); const modal = document.getElementById('manage-prices-modal'); const itemId = modal.dataset.currentItemId; const itemType = modal.dataset.currentItemType; fetchPrices(itemId, itemType); setTimeout(()=>location.reload(),600); }).catch(e=>alert('Error: '+e.message));
    };
}

function deletePrice(id, itemId, itemType){
    if (!confirm('Delete price?')) return;
    fetch('api_labors_parts.php', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({action:'price_delete', id:id})}).then(r=>r.json()).then(d=>{ if (!d || !d.success) return alert('Failed'); fetchPrices(itemId, itemType); setTimeout(()=>location.reload(),600); }).catch(e=>alert('Error: '+e.message));
}
</script>
</body>
</html>