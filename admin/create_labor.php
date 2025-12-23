<?php
require '../config.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin','manager'])) {
    header('Location: ../login.php');
    exit;
}

$success = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = (float)($_POST['default_price'] ?? 0);

    if ($name === '') {
        $error = 'Name is required';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO labors (name, description, default_price, created_by) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $description, $price, $_SESSION['user_id']]);
            $success = 'Labor created successfully';
        } catch (Exception $e) {
            error_log('create_labor.php - Insert error: ' . $e->getMessage());
            $error = 'Unable to save labor: ' . $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Create Labor — Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen p-6 font-sans">
    <?php include '../partials/sidebar.php'; ?>
    <div class="max-w-3xl mx-auto">
        <h1 class="text-2xl font-bold mb-4">Create Labor</h1>
        <?php if ($success): ?>
            <div class="mb-4 p-3 bg-green-100 text-green-800 rounded"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="mb-4 p-3 bg-red-100 text-red-800 rounded"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="post" class="space-y-4 bg-white p-4 rounded shadow">
            <div>
                <label class="block text-sm font-medium">Name *</label>
                <input type="text" name="name" required class="mt-1 w-full border p-2 rounded" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
            </div>
            <div>
                <label class="block text-sm font-medium">Description</label>
                <input type="text" name="description" class="mt-1 w-full border p-2 rounded" value="<?php echo htmlspecialchars($_POST['description'] ?? ''); ?>">
            </div>
            <div>
                <label class="block text-sm font-medium">Default Price (₾)</label>
                <input type="number" step="0.01" name="default_price" class="mt-1 w-full border p-2 rounded" value="<?php echo htmlspecialchars($_POST['default_price'] ?? '0.00'); ?>">
            </div>
            <div class="flex items-center space-x-3">
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">Create Labor</button>
                <a href="labors_parts_pro.php" class="text-sm text-gray-600">Back to management</a>
            </div>
        </form>
    </div>
</body>
</html>