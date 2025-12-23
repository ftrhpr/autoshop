<?php
require '../config.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'manager'])) {
    header('Location: ../login.php');
    exit;
}

// Handle add/edit/delete for labors
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Check if tables exist
    $tablesExist = true;
    try {
        $pdo->query("SELECT 1 FROM labors LIMIT 1");
        $pdo->query("SELECT 1 FROM parts LIMIT 1");
    } catch (Exception $e) {
        $tablesExist = false;
        $error = "Database tables not found. Please run the migration script: <code>migrate_add_labors_parts.php</code>";
    }

    if ($tablesExist) {
        $type = $_POST['type'] ?? '';
        $action = $_POST['action'] ?? '';

        if ($type === 'labor') {
            $table = 'labors';
        } elseif ($type === 'part') {
            $table = 'parts';
        } else {
            $error = 'Invalid type';
        }

        if (!isset($error) && ($action === 'add' || $action === 'edit')) {
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
        } elseif (!isset($error) && $action === 'delete') {
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
    $labors = $pdo->query("SELECT * FROM labors ORDER BY name")->fetchAll();
    $parts = $pdo->query("SELECT * FROM parts ORDER BY name")->fetchAll();
} catch (Exception $e) {
    // Tables don't exist, show empty arrays
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
        .tab-active { background-color: #3b82f6; color: white; }
        .modal { display: none; }
        .modal.show { display: flex; }
    </style>
</head>
<body class="bg-gray-100 h-screen overflow-hidden font-sans antialiased pb-20">
    <?php include '../partials/sidebar.php'; ?>

    <div class="h-full overflow-hidden ml-0 md:ml-64 pt-4 pl-4">
        <div class="h-full overflow-auto p-4 md:p-6">
            <h1 class="text-3xl font-bold mb-6">Labors & Parts Management</h1>

            <?php if (isset($success)): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Tabs -->
            <div class="mb-6">
                <div class="border-b border-gray-200">
                    <nav class="-mb-px flex space-x-8">
                        <button class="tab-button tab-active py-2 px-1 border-b-2 font-medium text-sm" data-tab="labors">
                            Labors (<?php echo count($labors); ?>)
                        </button>
                        <button class="tab-button py-2 px-1 border-b-2 font-medium text-sm text-gray-500 hover:text-gray-700" data-tab="parts">
                            Parts (<?php echo count($parts); ?>)
                        </button>
                    </nav>
                </div>
            </div>

            <!-- Labors Tab -->
            <div id="labors-tab" class="tab-content">
                <div class="mb-6">
                    <h3 class="text-lg font-semibold mb-4">Add New Labor</h3>
                    <form method="post" class="bg-gray-50 p-4 rounded-lg">
                        <input type="hidden" name="type" value="labor">
                        <input type="hidden" name="action" value="add">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2">Name *</label>
                                <input type="text" name="name" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                            </div>
                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2">Description</label>
                                <input type="text" name="description" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            </div>
                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2">Default Price (₾)</label>
                                <input type="number" name="default_price" step="0.01" min="0" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            </div>
                        </div>
                        <div class="mt-4">
                            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Add Labor</button>
                        </div>
                    </form>
                </div>

                <div class="bg-white shadow overflow-hidden sm:rounded-md">
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
                                    <button class="text-indigo-600 hover:text-indigo-900 edit-btn" data-type="labor" data-id="<?php echo $labor['id']; ?>" data-name="<?php echo htmlspecialchars($labor['name']); ?>" data-description="<?php echo htmlspecialchars($labor['description'] ?? ''); ?>" data-price="<?php echo $labor['default_price']; ?>">Edit</button>
                                    <form method="post" style="display:inline;" onsubmit="return confirm('Delete this labor?');">
                                        <input type="hidden" name="type" value="labor">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $labor['id']; ?>">
                                        <button type="submit" class="text-red-600 hover:text-red-900 ml-4">Delete</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Parts Tab -->
            <div id="parts-tab" class="tab-content hidden">
                <div class="mb-6">
                    <h3 class="text-lg font-semibold mb-4">Add New Part</h3>
                    <form method="post" class="bg-gray-50 p-4 rounded-lg">
                        <input type="hidden" name="type" value="part">
                        <input type="hidden" name="action" value="add">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2">Name *</label>
                                <input type="text" name="name" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                            </div>
                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2">Description</label>
                                <input type="text" name="description" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            </div>
                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2">Default Price (₾)</label>
                                <input type="number" name="default_price" step="0.01" min="0" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            </div>
                        </div>
                        <div class="mt-4">
                            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Add Part</button>
                        </div>
                    </form>
                </div>

                <div class="bg-white shadow overflow-hidden sm:rounded-md">
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
                                    <button class="text-indigo-600 hover:text-indigo-900 edit-btn" data-type="part" data-id="<?php echo $part['id']; ?>" data-name="<?php echo htmlspecialchars($part['name']); ?>" data-description="<?php echo htmlspecialchars($part['description'] ?? ''); ?>" data-price="<?php echo $part['default_price']; ?>">Edit</button>
                                    <form method="post" style="display:inline;" onsubmit="return confirm('Delete this part?');">
                                        <input type="hidden" name="type" value="part">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $part['id']; ?>">
                                        <button type="submit" class="text-red-600 hover:text-red-900 ml-4">Delete</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div id="modal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4" id="modal-title">Edit Labor</h3>
                <form method="post">
                    <input type="hidden" name="type" id="modal-type">
                    <input type="hidden" name="action" id="modal-action" value="edit">
                    <input type="hidden" name="id" id="modal-id">
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Name *</label>
                        <input type="text" name="name" id="modal-name" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Description</label>
                        <textarea name="description" id="modal-description" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Default Price (₾)</label>
                        <input type="number" name="default_price" id="modal-price" step="0.01" min="0" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    </div>
                    
                    <div class="flex justify-end">
                        <button type="button" class="mr-2 px-4 py-2 text-gray-500 bg-gray-200 rounded hover:bg-gray-300" onclick="closeModal()">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Tab switching
            document.querySelectorAll('.tab-button').forEach(btn => {
                btn.addEventListener('click', function() {
                    document.querySelectorAll('.tab-button').forEach(b => b.classList.remove('tab-active', 'border-blue-500', 'text-blue-600'));
                    document.querySelectorAll('.tab-button').forEach(b => b.classList.add('text-gray-500', 'hover:text-gray-700'));
                    document.querySelectorAll('.tab-content').forEach(c => c.classList.add('hidden'));
                    
                    this.classList.add('tab-active', 'border-blue-500', 'text-blue-600');
                    this.classList.remove('text-gray-500', 'hover:text-gray-700');
                    document.getElementById(this.dataset.tab + '-tab').classList.remove('hidden');
                });
            });

            // Edit buttons
            document.querySelectorAll('.edit-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const type = this.dataset.type;
                    document.getElementById('modal-title').textContent = 'Edit ' + (type === 'labor' ? 'Labor' : 'Part');
                    document.getElementById('modal-type').value = type;
                    document.getElementById('modal-action').value = 'edit';
                    document.getElementById('modal-id').value = this.dataset.id;
                    document.getElementById('modal-name').value = this.dataset.name;
                    document.getElementById('modal-description').value = this.dataset.description;
                    document.getElementById('modal-price').value = this.dataset.price;
                    document.getElementById('modal').classList.add('show');
                });
            });

            // Close modal on outside click
            document.getElementById('modal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeModal();
                }
            });
        });

        function closeModal() {
            document.getElementById('modal').classList.remove('show');
        }
    </script>
</body>
</html>