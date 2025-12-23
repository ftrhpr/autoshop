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
        } catch (Exception $e) {
            error_log('labors_parts_pro.php - action error: ' . $e->getMessage());
            $errors[] = $e->getMessage();
        }
    }
}

// Listing params
$type = in_array($_GET['type'] ?? 'part', ['part','labor']) ? $_GET['type'] : 'part';
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
        <div class="text-sm text-gray-600">Showing <?php echo count($rows); ?> of <?php echo $total; ?> <?php echo $type === 'part' ? 'parts' : 'labors'; ?></div>
        <div class="space-x-2">
            <a href="?type=<?php echo $type; ?>" class="px-3 py-2 bg-gray-100 rounded">Refresh</a>
            <a href="api_labors_parts.php?action=export&type=<?php echo $type === 'part' ? 'parts' : 'labors'; ?>" class="px-3 py-2 bg-gray-100 rounded">Export CSV</a>
        </div>
    </div>

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
                    <td class="p-2"><?php echo number_format($r['default_price'], 2); ?></td>
                    <td class="p-2 text-right">
                        <form method="post" class="inline" onsubmit="return confirm('Delete?');">
                            <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="type" value="<?php echo h($type); ?>">
                            <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                            <button class="text-red-600">Delete</button>
                        </form>
                        <button class="ml-3 text-indigo-600" onclick="openEdit(<?php echo (int)$r['id']; ?>, '<?php echo h(addslashes($r['name'])); ?>', '<?php echo h(addslashes($r['description'])); ?>', '<?php echo h($r['default_price']); ?>', '<?php echo h(addslashes($r['vehicle_make_model'] ?? '')); ?>')">Edit</button>
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
                    <label class="block text-sm">Vehicle Make/Model (optional)</label>
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

<script>
function openEdit(id,name,desc,price,vehicle){
    document.getElementById('edit-id').value = id;
    document.getElementById('edit-name').value = name;
    document.getElementById('edit-description').value = desc;
    document.getElementById('edit-price').value = price;
    document.getElementById('edit-vehicle').value = vehicle || '';
    document.getElementById('edit-modal').style.display = 'flex';
}
</script>
</body>
</html>