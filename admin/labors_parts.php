<?php
require '../config.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'manager'])) {
    header('Location: ../login.php');
    exit;
}

// Handle form submissions
$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $type = $_POST['type'] ?? '';
    $table = ($type === 'part') ? 'parts' : 'labors';

    try {
        if ($action === 'add') {
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $price = (float)($_POST['default_price'] ?? 0);

            if (empty($name)) {
                throw new Exception('Name is required.');
            }

            $stmt = $pdo->prepare("INSERT INTO $table (name, description, default_price, created_by) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $description, $price, $_SESSION['user_id']]);
            $message = ucfirst($type) . ' added successfully.';

        } elseif ($action === 'edit') {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $price = (float)($_POST['default_price'] ?? 0);

            if ($id <= 0 || empty($name)) {
                throw new Exception('Invalid data.');
            }

            $stmt = $pdo->prepare("UPDATE $table SET name = ?, description = ?, default_price = ? WHERE id = ?");
            $stmt->execute([$name, $description, $price, $id]);
            $message = ucfirst($type) . ' updated successfully.';

        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);

            if ($id <= 0) {
                throw new Exception('Invalid ID.');
            }

            $stmt = $pdo->prepare("DELETE FROM $table WHERE id = ?");
            $stmt->execute([$id]);
            $message = ucfirst($type) . ' deleted successfully.';
        }
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Fetch data from database
$labors = [];
$parts = [];
try {
    $labors = $pdo->query("SELECT id, name, description, default_price FROM labors ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $parts = $pdo->query("SELECT id, name, description, default_price FROM parts ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $message = 'Database error: ' . $e->getMessage();
    $messageType = 'error';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Labors & Parts Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; z-index: 50; }
        .modal.show { display: flex; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <?php include '../partials/sidebar.php'; ?>

    <div class="ml-0 md:ml-64 p-6">
        <div class="max-w-7xl mx-auto">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-3xl font-bold text-gray-900">Labors & Parts Management</h1>
                <div class="flex space-x-3">
                    <a href="create_labor.php" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Create Labor</a>
                    <a href="create_part.php" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Create Part</a>
                    <a href="labors_parts_pro.php" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">PRO</a>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="mb-4 p-4 rounded <?php echo $messageType === 'error' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Labors Section -->
                <div class="bg-white shadow rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-medium text-gray-900">Labors (<?php echo count($labors); ?>)</h2>
                    </div>
                    <div class="p-6">
                        <form method="post" class="mb-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="type" value="labor">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Name *</label>
                                <input type="text" name="name" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Description</label>
                                <input type="text" name="description" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Price (₾)</label>
                                <input type="number" name="default_price" step="0.01" min="0" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div class="md:col-span-3 flex justify-end">
                                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Add Labor</button>
                            </div>
                        </form>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($labors as $labor): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($labor['name']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($labor['description'] ?? ''); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo number_format($labor['default_price'], 2); ?> ₾</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <button type="button" class="text-indigo-600 hover:text-indigo-900 edit-btn" data-id="<?php echo $labor['id']; ?>" data-type="labor" data-name="<?php echo htmlspecialchars($labor['name']); ?>" data-description="<?php echo htmlspecialchars($labor['description'] ?? ''); ?>" data-price="<?php echo $labor['default_price']; ?>">Edit</button>
                                            <form method="post" class="inline ml-4" onsubmit="return confirm('Are you sure you want to delete this labor?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="type" value="labor">
                                                <input type="hidden" name="id" value="<?php echo $labor['id']; ?>">
                                                <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($labors)): ?>
                                    <tr>
                                        <td colspan="4" class="px-6 py-4 text-center text-gray-500">No labors found.</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Parts Section -->
                <div class="bg-white shadow rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-medium text-gray-900">Parts (<?php echo count($parts); ?>)</h2>
                    </div>
                    <div class="p-6">
                        <form method="post" class="mb-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="type" value="part">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Name *</label>
                                <input type="text" name="name" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Description</label>
                                <input type="text" name="description" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Price (₾)</label>
                                <input type="number" name="default_price" step="0.01" min="0" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div class="md:col-span-3 flex justify-end">
                                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Add Part</button>
                            </div>
                        </form>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($parts as $part): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($part['name']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($part['description'] ?? ''); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo number_format($part['default_price'], 2); ?> ₾</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <button type="button" class="text-indigo-600 hover:text-indigo-900 edit-btn" data-id="<?php echo $part['id']; ?>" data-type="part" data-name="<?php echo htmlspecialchars($part['name']); ?>" data-description="<?php echo htmlspecialchars($part['description'] ?? ''); ?>" data-price="<?php echo $part['default_price']; ?>">Edit</button>
                                            <form method="post" class="inline ml-4" onsubmit="return confirm('Are you sure you want to delete this part?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="type" value="part">
                                                <input type="hidden" name="id" value="<?php echo $part['id']; ?>">
                                                <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($parts)): ?>
                                    <tr>
                                        <td colspan="4" class="px-6 py-4 text-center text-gray-500">No parts found.</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="edit-modal" class="modal">
        <div class="bg-white rounded-lg shadow-lg max-w-md w-full mx-4">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900" id="modal-title">Edit Item</h3>
            </div>
            <form method="post" class="p-6">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit-id">
                <input type="hidden" name="type" id="edit-type">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Name *</label>
                    <input type="text" name="name" id="edit-name" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Description</label>
                    <input type="text" name="description" id="edit-description" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Price (₾)</label>
                    <input type="number" name="default_price" id="edit-price" step="0.01" min="0" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" id="cancel-edit" class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400">Cancel</button>
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Handle edit buttons
        document.querySelectorAll('.edit-btn').forEach(button => {
            button.addEventListener('click', function() {
                const id = this.dataset.id;
                const type = this.dataset.type;
                const name = this.dataset.name;
                const description = this.dataset.description;
                const price = this.dataset.price;

                document.getElementById('edit-id').value = id;
                document.getElementById('edit-type').value = type;
                document.getElementById('edit-name').value = name;
                document.getElementById('edit-description').value = description;
                document.getElementById('edit-price').value = price;
                document.getElementById('modal-title').textContent = 'Edit ' + (type === 'labor' ? 'Labor' : 'Part');

                document.getElementById('edit-modal').classList.add('show');
            });
        });

        // Handle modal cancel
        document.getElementById('cancel-edit').addEventListener('click', function() {
            document.getElementById('edit-modal').classList.remove('show');
        });

        // Close modal when clicking outside
        document.getElementById('edit-modal').addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('show');
            }
        });
    </script>
</body>
</html>