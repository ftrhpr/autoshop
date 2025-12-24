<?php
require '../config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Check if user is admin or manager
$stmt = $pdo->prepare('SELECT role FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
if (!$user || !in_array($user['role'], ['admin', 'manager'])) {
    die('Access denied');
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action == 'add_brand') {
        $name = trim($_POST['brand_name'] ?? '');
        if ($name) {
            try {
                $stmt = $pdo->prepare('INSERT INTO oil_brands (name) VALUES (?)');
                $stmt->execute([$name]);
                $message = 'Brand added successfully';
            } catch (Exception $e) {
                $error = 'Error adding brand: ' . $e->getMessage();
            }
        }
    } elseif ($action == 'add_viscosity') {
        $viscosity = trim($_POST['viscosity'] ?? '');
        $description = trim($_POST['description'] ?? '');
        if ($viscosity) {
            try {
                $stmt = $pdo->prepare('INSERT INTO oil_viscosities (viscosity, description) VALUES (?, ?)');
                $stmt->execute([$viscosity, $description]);
                $message = 'Viscosity added successfully';
            } catch (Exception $e) {
                $error = 'Error adding viscosity: ' . $e->getMessage();
            }
        }
    } elseif ($action == 'add_price') {
        $brand_id = (int)($_POST['brand_id'] ?? 0);
        $viscosity_id = (int)($_POST['viscosity_id'] ?? 0);
        $package_type = $_POST['package_type'] ?? '';
        $price = (float)($_POST['price'] ?? 0);

        if ($brand_id && $viscosity_id && $package_type && $price > 0) {
            try {
                $stmt = $pdo->prepare('INSERT INTO oil_prices (brand_id, viscosity_id, package_type, price, created_by) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE price = ?, created_by = ?');
                $stmt->execute([$brand_id, $viscosity_id, $package_type, $price, $_SESSION['user_id'], $price, $_SESSION['user_id']]);
                $message = 'Price updated successfully';
            } catch (Exception $e) {
                $error = 'Error updating price: ' . $e->getMessage();
            }
        }
    } elseif ($action == 'delete_price') {
        $id = (int)($_POST['price_id'] ?? 0);
        if ($id) {
            try {
                $stmt = $pdo->prepare('DELETE FROM oil_prices WHERE id = ?');
                $stmt->execute([$id]);
                $message = 'Price deleted successfully';
            } catch (Exception $e) {
                $error = 'Error deleting price: ' . $e->getMessage();
            }
        }
    }
}

// Get all brands
$brands = $pdo->query('SELECT * FROM oil_brands ORDER BY name')->fetchAll();

// Get all viscosities
$viscosities = $pdo->query('SELECT * FROM oil_viscosities ORDER BY viscosity')->fetchAll();

// Get all prices with brand and viscosity names
$prices = $pdo->query('
    SELECT p.*, b.name as brand_name, v.viscosity as viscosity_name
    FROM oil_prices p
    JOIN oil_brands b ON p.brand_id = b.id
    JOIN oil_viscosities v ON p.viscosity_id = v.id
    ORDER BY b.name, v.viscosity, p.package_type
')->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Oil Price Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="min-h-screen py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white shadow-lg rounded-lg overflow-hidden">
                <div class="px-6 py-4 bg-blue-600 text-white">
                    <h1 class="text-2xl font-bold">Oil Price Management</h1>
                    <p class="text-blue-100">Configure engine oil prices by brand, viscosity, and package type</p>
                </div>

                <?php if ($message): ?>
                    <div class="px-6 py-4 bg-green-50 border-l-4 border-green-400">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-check-circle text-green-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-green-700"><?php echo htmlspecialchars($message); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="px-6 py-4 bg-red-50 border-l-4 border-red-400">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-circle text-red-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-red-700"><?php echo htmlspecialchars($error); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="p-6 space-y-8">
                    <!-- Add Brand -->
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h2 class="text-lg font-semibold mb-4">Add Oil Brand</h2>
                        <form method="POST" class="flex gap-4">
                            <input type="hidden" name="action" value="add_brand">
                            <input type="text" name="brand_name" placeholder="Brand name" required class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition">
                                <i class="fas fa-plus mr-2"></i>Add Brand
                            </button>
                        </form>
                    </div>

                    <!-- Add Viscosity -->
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h2 class="text-lg font-semibold mb-4">Add Oil Viscosity</h2>
                        <form method="POST" class="flex gap-4">
                            <input type="hidden" name="action" value="add_viscosity">
                            <input type="text" name="viscosity" placeholder="Viscosity (e.g., 5W-30)" required class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <input type="text" name="description" placeholder="Description (optional)" class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition">
                                <i class="fas fa-plus mr-2"></i>Add Viscosity
                            </button>
                        </form>
                    </div>

                    <!-- Add/Update Price -->
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h2 class="text-lg font-semibold mb-4">Set Oil Prices</h2>
                        <form method="POST" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                            <input type="hidden" name="action" value="add_price">
                            <select name="brand_id" required class="px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Select Brand</option>
                                <?php foreach ($brands as $brand): ?>
                                    <option value="<?php echo $brand['id']; ?>"><?php echo htmlspecialchars($brand['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select name="viscosity_id" required class="px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Select Viscosity</option>
                                <?php foreach ($viscosities as $viscosity): ?>
                                    <option value="<?php echo $viscosity['id']; ?>"><?php echo htmlspecialchars($viscosity['viscosity']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select name="package_type" required class="px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Package Type</option>
                                <option value="canned">Canned</option>
                                <option value="5lt">5 Liter</option>
                                <option value="4lt">4 Liter</option>
                                <option value="1lt">1 Liter</option>
                            </select>
                            <input type="number" name="price" step="0.01" min="0" placeholder="Price" required class="px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition">
                                <i class="fas fa-save mr-2"></i>Set Price
                            </button>
                        </form>
                    </div>

                    <!-- Current Prices -->
                    <div>
                        <h2 class="text-lg font-semibold mb-4">Current Oil Prices</h2>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Brand</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Viscosity</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Package</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($prices as $price): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($price['brand_name']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($price['viscosity_name']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($price['package_type']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo number_format($price['price'], 2); ?> ₾</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this price?')">
                                                    <input type="hidden" name="action" value="delete_price">
                                                    <input type="hidden" name="price_id" value="<?php echo $price['id']; ?>">
                                                    <button type="submit" class="text-red-600 hover:text-red-900">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
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
        </div>
    </div>
</body>
</html>