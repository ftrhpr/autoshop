<?php
require '../config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Check if user is admin or manager
$userRole = $_SESSION['role'] ?? 'user';
if ($userRole !== 'admin' && $userRole !== 'manager') {
    die('Access denied');
}

$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_brand'])) {
        $name = trim($_POST['brand_name']);
        $description = trim($_POST['brand_description']);

        if ($name) {
            try {
                $stmt = $pdo->prepare("INSERT INTO oil_brands (name, description, created_by) VALUES (?, ?, ?)");
                $stmt->execute([$name, $description, $_SESSION['user_id']]);
                $message = "Oil brand added successfully.";
            } catch (PDOException $e) {
                $error = "Error adding brand: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['add_package'])) {
        $brand_id = (int)$_POST['brand_id'];
        $viscosity_id = (int)$_POST['viscosity_id'];
        $package_type = $_POST['package_type'];
        $amount = $package_type === 'canned' ? (float)$_POST['amount'] : null;
        $price = (float)$_POST['price'];

        if ($brand_id && $viscosity_id && $price > 0) {
            try {
                $stmt = $pdo->prepare("INSERT INTO oil_packages (brand_id, viscosity_id, package_type, amount, price, created_by) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$brand_id, $viscosity_id, $package_type, $amount, $price, $_SESSION['user_id']]);
                $message = "Oil package added successfully.";
            } catch (PDOException $e) {
                $error = "Error adding package: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['delete_brand'])) {
        $brand_id = (int)$_POST['brand_id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM oil_brands WHERE id = ?");
            $stmt->execute([$brand_id]);
            $message = "Oil brand deleted successfully.";
        } catch (PDOException $e) {
            $error = "Error deleting brand: " . $e->getMessage();
        }
    } elseif (isset($_POST['delete_package'])) {
        $package_id = (int)$_POST['package_id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM oil_packages WHERE id = ?");
            $stmt->execute([$package_id]);
            $message = "Oil package deleted successfully.";
        } catch (PDOException $e) {
            $error = "Error deleting package: " . $e->getMessage();
        }
    }
}

// Fetch data
$brands = $pdo->query("SELECT * FROM oil_brands ORDER BY name")->fetchAll();
$viscosities = $pdo->query("SELECT * FROM oil_viscosities ORDER BY viscosity")->fetchAll();

$packages = $pdo->query("
    SELECT p.*, b.name as brand_name, v.viscosity
    FROM oil_packages p
    JOIN oil_brands b ON p.brand_id = b.id
    JOIN oil_viscosities v ON p.viscosity_id = v.id
    ORDER BY b.name, v.viscosity, p.package_type, p.amount
")->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Oil Configuration - AutoShop</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen overflow-auto font-sans antialiased pb-20">
    <?php include __DIR__ . '/../partials/sidebar.php'; ?>
    <div class="min-h-screen ml-0 md:ml-64 pt-4 pl-4">
        <!-- Header -->
        <header class="flex-shrink-0 p-4 md:p-6">
            <nav aria-label="Breadcrumb" class="mb-4">
                <ol class="flex items-center space-x-2 text-sm text-gray-500">
                    <li><a href="index.php" class="hover:text-blue-600 transition">Dashboard</a></li>
                    <li><svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg></li>
                    <li aria-current="page">Oil Configuration</li>
                </ol>
            </nav>
            <h1 class="text-4xl font-bold text-gray-900 flex items-center">
                <svg class="w-10 h-10 mr-4 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                </svg>
                Oil Configuration
            </h1>
        </header>

        <!-- Main Content -->
        <main class="flex-1 p-4 md:p-6">
            <div class="bg-white p-4 md:p-6 rounded-xl shadow-xl mx-4 md:mx-6 mb-4">
                <?php if ($message): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Add Brand -->
                    <div class="bg-white p-6 rounded-lg shadow-md">
                        <h2 class="text-xl font-semibold mb-4">Add Oil Brand</h2>
                        <form method="POST">
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Brand Name</label>
                                <input type="text" name="brand_name" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                                <textarea name="brand_description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500"></textarea>
                            </div>
                            <button type="submit" name="add_brand" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md">
                                Add Brand
                            </button>
                        </form>
                    </div>

                    <!-- Add Package -->
                    <div class="bg-white p-6 rounded-lg shadow-md">
                        <h2 class="text-xl font-semibold mb-4">Add Oil Package</h2>
                        <form method="POST">
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Brand</label>
                                <select name="brand_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                                    <option value="">Select Brand</option>
                                    <?php foreach ($brands as $brand): ?>
                                        <option value="<?php echo $brand['id']; ?>"><?php echo htmlspecialchars($brand['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Viscosity</label>
                                <select name="viscosity_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                                    <option value="">Select Viscosity</option>
                                    <?php foreach ($viscosities as $visc): ?>
                                        <option value="<?php echo $visc['id']; ?>"><?php echo htmlspecialchars($visc['viscosity']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Package Type</label>
                                <select name="package_type" id="package_type" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                                    <option value="5L">5 Liter</option>
                                    <option value="4L">4 Liter</option>
                                    <option value="1L">1 Liter</option>
                                    <option value="canned">Canned (Custom Amount)</option>
                                </select>
                            </div>
                            <div class="mb-4" id="amount_field" style="display: none;">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Amount (Liters)</label>
                                <input type="number" step="0.1" name="amount" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Price (₾)</label>
                                <input type="number" step="0.01" name="price" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                            </div>
                            <button type="submit" name="add_package" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md">
                                Add Package
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Existing Brands -->
                <div class="mt-8 bg-white p-6 rounded-lg shadow-md">
                    <h2 class="text-xl font-semibold mb-4">Oil Brands</h2>
                    <div class="overflow-x-auto">
                        <table class="w-full table-auto">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="px-4 py-2 text-left">Name</th>
                                    <th class="px-4 py-2 text-left">Description</th>
                                    <th class="px-4 py-2 text-left">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($brands as $brand): ?>
                                    <tr class="border-t">
                                        <td class="px-4 py-2"><?php echo htmlspecialchars($brand['name']); ?></td>
                                        <td class="px-4 py-2"><?php echo htmlspecialchars($brand['description'] ?? ''); ?></td>
                                        <td class="px-4 py-2">
                                            <form method="POST" class="inline" onsubmit="return confirm('Delete this brand?')">
                                                <input type="hidden" name="brand_id" value="<?php echo $brand['id']; ?>">
                                                <button type="submit" name="delete_brand" class="text-red-600 hover:text-red-800">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Existing Packages -->
                <div class="mt-8 bg-white p-6 rounded-lg shadow-md">
                    <h2 class="text-xl font-semibold mb-4">Oil Packages</h2>
                    <div class="overflow-x-auto">
                        <table class="w-full table-auto">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="px-4 py-2 text-left">Brand</th>
                                    <th class="px-4 py-2 text-left">Viscosity</th>
                                    <th class="px-4 py-2 text-left">Package</th>
                                    <th class="px-4 py-2 text-left">Price</th>
                                    <th class="px-4 py-2 text-left">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($packages as $package): ?>
                                    <tr class="border-t">
                                        <td class="px-4 py-2"><?php echo htmlspecialchars($package['brand_name']); ?></td>
                                        <td class="px-4 py-2"><?php echo htmlspecialchars($package['viscosity']); ?></td>
                                        <td class="px-4 py-2">
                                            <?php
                                            if ($package['package_type'] === 'canned') {
                                                echo htmlspecialchars($package['amount']) . ' L (Canned)';
                                            } else {
                                                echo htmlspecialchars($package['package_type']);
                                            }
                                            ?>
                                        </td>
                                        <td class="px-4 py-2"><?php echo number_format($package['price'], 2); ?> ₾</td>
                                        <td class="px-4 py-2">
                                            <form method="POST" class="inline" onsubmit="return confirm('Delete this package?')">
                                                <input type="hidden" name="package_id" value="<?php echo $package['id']; ?>">
                                                <button type="submit" name="delete_package" class="text-red-600 hover:text-red-800">
                                                    <i class="fas fa-trash"></i> Delete
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
        </main>
    </div>

    <script>
        // Show/hide amount field for canned packages
        document.getElementById('package_type').addEventListener('change', function() {
            const amountField = document.getElementById('amount_field');
            if (this.value === 'canned') {
                amountField.style.display = 'block';
                amountField.querySelector('input').required = true;
            } else {
                amountField.style.display = 'none';
                amountField.querySelector('input').required = false;
            }
        });
    </script>
</body>
</html>