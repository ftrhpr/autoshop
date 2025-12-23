<?php
require '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// Fetch all permissions
$permsStmt = $pdo->query('SELECT * FROM permissions ORDER BY id');
$permissions = $permsStmt->fetchAll();

// Roles to manage
$roles = ['admin', 'manager', 'user'];

// Get current mappings
$rolePerms = [];
$stmt = $pdo->prepare('SELECT permission_id FROM role_permissions WHERE role = ?');
foreach ($roles as $r) {
    $stmt->execute([$r]);
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $rolePerms[$r] = $ids ?: [];
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Roles & Permissions - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-4 md:p-6 min-h-screen overflow-x-hidden font-sans antialiased">
    <?php include __DIR__ . '/../partials/sidebar.php'; ?>
    <div class="container mx-auto ml-0 md:ml-64">
        <a href="index.php" class="text-blue-500 hover:underline mb-4 inline-block">&larr; Back</a>

        <div class="bg-white p-4 md:p-6 rounded-lg shadow-md">
            <h2 class="text-2xl font-bold mb-4">Roles & Permissions</h2>
            <form method="post" action="save_permissions.php">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <?php foreach ($roles as $r): ?>
                    <div class="border rounded p-4">
                        <h3 class="font-semibold mb-3 text-lg"><?php echo strtoupper($r); ?></h3>
                        <?php foreach ($permissions as $p): ?>
                            <label class="flex items-center gap-2 mb-2 block">
                                <input type="checkbox" name="perm_<?php echo $r; ?>[]" value="<?php echo $p['id']; ?>" <?php echo in_array($p['id'], $rolePerms[$r]) ? 'checked' : ''; ?>>
                                <span class="font-medium"><?php echo htmlspecialchars($p['label']); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button type="submit" class="mt-4 bg-blue-600 text-white px-4 py-2 rounded">Save Permissions</button>
            </form>
        </div>
    </div>
</body>
</html>