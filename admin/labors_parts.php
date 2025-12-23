<?php
require '../config.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'manager'])) {
    header('Location: ../login.php');
    exit;
}

// Handle add/edit/delete for labors and parts
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $type = $_POST['type'] ?? '';
    $action = $_POST['action'] ?? '';

    if ($type === 'labor') {
        $table = 'labors';
    } elseif ($type === 'part') {
        $table = 'parts';
    } else {
        $error = 'Invalid type';
    }

    if (!isset($error)) {
        if ($action === 'add' || $action === 'edit') {
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $default_price = (float)($_POST['default_price'] ?? 0);

            if (empty($name)) {
                $error = 'Name is required';
            } else {
                try {
                    if ($action === 'add') {
                        $stmt = $pdo->prepare("INSERT INTO $table (name, description, default_price, created_by) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$name, $description, $default_price, $_SESSION['user_id']]);
                        $success = ucfirst($type) . ' added successfully';
                    } elseif ($action === 'edit') {
                        $id = (int)$_POST['id'];
                        $stmt = $pdo->prepare("UPDATE $table SET name = ?, description = ?, default_price = ? WHERE id = ?");
                        $stmt->execute([$name, $description, $default_price, $id]);
                        $success = ucfirst($type) . ' updated successfully';
                    }
                } catch (Exception $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        } elseif ($action === 'delete') {
            $id = (int)$_POST['id'];
            try {
                $stmt = $pdo->prepare("DELETE FROM $table WHERE id = ?");
                $stmt->execute([$id]);
                $success = ucfirst($type) . ' deleted successfully';
            } catch (Exception $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Fetch labors and parts
$labors = [];
$parts = [];
try {
    $labors = $pdo->query("SELECT * FROM labors ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $parts = $pdo->query("SELECT * FROM parts ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // For testing without database, use dummy data
    $labors = [
        ['id' => 1, 'name' => 'Oil Change', 'description' => 'Engine oil change service', 'default_price' => 50.00],
        ['id' => 2, 'name' => 'Brake Service', 'description' => 'Brake pad replacement', 'default_price' => 120.00],
    ];
    $parts = [
        ['id' => 1, 'name' => 'Brake Pads', 'description' => 'Front brake pads', 'default_price' => 80.00],
        ['id' => 2, 'name' => 'Oil Filter', 'description' => 'Engine oil filter', 'default_price' => 15.00],
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Labors & Parts - Auto Shop</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 50; align-items: center; justify-content: center; }
        .modal.show { display: flex; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen font-sans antialiased">
    <?php include '../partials/sidebar.php'; ?>

    <div class="ml-0 md:ml-64 p-4 md:p-6 pt-4">
        <div class="max-w-7xl mx-auto">
            <h1 class="text-3xl font-bold mb-6">Labors & Parts Management</h1>

            <?php if (isset($error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($success)): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <!-- Labors Section -->
            <div class="mb-8">
                <h2 class="text-2xl font-bold mb-4">Labors</h2>
                
                <div class="bg-white shadow rounded-lg mb-6">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Add New Labor</h3>
                        <form method="post" class="space-y-4">
                            <input type="hidden" name="type" value="labor">
                            <input type="hidden" name="action" value="add">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Name *</label>
                                    <input type="text" name="name" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Description</label>
                                    <input type="text" name="description" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Default Price (₾)</label>
                                    <input type="number" name="default_price" step="0.01" min="0" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                </div>
                            </div>
                            <div class="flex justify-end">
                                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">Add Labor</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="bg-white shadow overflow-hidden rounded-lg">
                    <div class="px-4 py-5 sm:px-6">
                        <h3 class="text-lg font-medium text-gray-900">Existing Labors</h3>
                    </div>
                    <div class="border-t border-gray-200">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Default Price</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($labors as $labor): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($labor['name']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($labor['description'] ?? ''); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $labor['default_price'] > 0 ? number_format($labor['default_price'], 2) . ' ₾' : '-'; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <button type="button" class="edit-btn text-indigo-600 hover:text-indigo-900 mr-4" data-type="labor" data-id="<?php echo $labor['id']; ?>" data-name="<?php echo htmlspecialchars($labor['name']); ?>" data-description="<?php echo htmlspecialchars($labor['description'] ?? ''); ?>" data-price="<?php echo $labor['default_price']; ?>">Edit</button>
                                        <form method="post" class="inline" onsubmit="return confirm('Delete this labor?');">
                                            <input type="hidden" name="type" value="labor">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $labor['id']; ?>">
                                            <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($labors)): ?>
                                <tr>
                                    <td colspan="4" class="px-6 py-4 text-center text-gray-500">No labors found</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Parts Section -->
            <div class="mb-8">
                <h2 class="text-2xl font-bold mb-4">Parts</h2>
                
                <div class="bg-white shadow rounded-lg mb-6">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Add New Part</h3>
                        <form method="post" class="space-y-4">
                            <input type="hidden" name="type" value="part">
                            <input type="hidden" name="action" value="add">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Name *</label>
                                    <input type="text" name="name" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Description</label>
                                    <input type="text" name="description" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Default Price (₾)</label>
                                    <input type="number" name="default_price" step="0.01" min="0" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                </div>
                            </div>
                            <div class="flex justify-end">
                                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">Add Part</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="bg-white shadow overflow-hidden rounded-lg">
                    <div class="px-4 py-5 sm:px-6">
                        <h3 class="text-lg font-medium text-gray-900">Existing Parts</h3>
                    </div>
                    <div class="border-t border-gray-200">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Default Price</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($parts as $part): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($part['name']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($part['description'] ?? ''); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $part['default_price'] > 0 ? number_format($part['default_price'], 2) . ' ₾' : '-'; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <button type="button" class="edit-btn text-indigo-600 hover:text-indigo-900 mr-4" data-type="part" data-id="<?php echo $part['id']; ?>" data-name="<?php echo htmlspecialchars($part['name']); ?>" data-description="<?php echo htmlspecialchars($part['description'] ?? ''); ?>" data-price="<?php echo $part['default_price']; ?>">Edit</button>
                                        <form method="post" class="inline" onsubmit="return confirm('Delete this part?');">
                                            <input type="hidden" name="type" value="part">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $part['id']; ?>">
                                            <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($parts)): ?>
                                <tr>
                                    <td colspan="4" class="px-6 py-4 text-center text-gray-500">No parts found</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="edit-modal" class="modal">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900" id="modal-title">Edit Item</h3>
            </div>
            <form method="post" class="p-6">
                <input type="hidden" name="type" id="modal-type">
                <input type="hidden" name="action" id="modal-action" value="edit">
                <input type="hidden" name="id" id="modal-id">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Name *</label>
                    <input type="text" name="name" id="modal-name" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Description</label>
                    <textarea name="description" id="modal-description" rows="3" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"></textarea>
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700">Default Price (₾)</label>
                    <input type="number" name="default_price" id="modal-price" step="0.01" min="0" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" id="modal-cancel" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 border border-gray-300 rounded-md hover:bg-gray-200">Cancel</button>
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700">Update</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Edit button functionality
            document.querySelectorAll('.edit-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const type = this.getAttribute('data-type');
                    const id = this.getAttribute('data-id');
                    const name = this.getAttribute('data-name');
                    const description = this.getAttribute('data-description');
                    const price = this.getAttribute('data-price');

                    document.getElementById('modal-title').textContent = 'Edit ' + (type === 'labor' ? 'Labor' : 'Part');
                    document.getElementById('modal-type').value = type;
                    document.getElementById('modal-id').value = id;
                    document.getElementById('modal-name').value = name;
                    document.getElementById('modal-description').value = description;
                    document.getElementById('modal-price').value = price;

                    document.getElementById('edit-modal').classList.add('show');
                });
            });

            // Modal close functionality
            document.getElementById('modal-cancel').addEventListener('click', function() {
                document.getElementById('edit-modal').classList.remove('show');
            });

            document.getElementById('edit-modal').addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('show');
                }
            });
        });
    </script>
</body>
</html>