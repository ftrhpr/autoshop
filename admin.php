<?php
require 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Handle user creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_user'])) {
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];

    $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
    $stmt->execute([$username, $password, $role]);
    $success = 'User created successfully';
}

// Fetch users
$stmt = $pdo->query("SELECT id, username, role, created_at FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll();

// Fetch invoices
$stmt = $pdo->query("SELECT * FROM invoices ORDER BY created_at DESC LIMIT 50");
$invoices = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Panel - Auto Shop</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <nav class="bg-blue-600 text-white p-4">
        <div class="container mx-auto flex justify-between">
            <h1 class="text-xl font-bold">Admin Panel</h1>
            <div>
                <a href="index.php" class="mr-4">Invoice Generator</a>
                <a href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mx-auto p-6">
        <h2 class="text-2xl font-bold mb-6">Manage Users</h2>
        <?php if (isset($success)) echo "<p class='text-green-500 mb-4'>$success</p>"; ?>

        <form method="post" class="bg-white p-6 rounded-lg shadow-md mb-6">
            <h3 class="text-lg font-bold mb-4">Create New User</h3>
            <div class="grid grid-cols-3 gap-4">
                <input type="text" name="username" placeholder="Username" class="px-3 py-2 border rounded" required>
                <input type="password" name="password" placeholder="Password" class="px-3 py-2 border rounded" required>
                <select name="role" class="px-3 py-2 border rounded" required>
                    <option value="user">User</option>
                    <option value="manager">Manager</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <button type="submit" name="create_user" class="mt-4 bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Create User</button>
        </form>

        <table class="bg-white rounded-lg shadow-md w-full">
            <thead>
                <tr class="bg-gray-200">
                    <th class="px-4 py-2">ID</th>
                    <th class="px-4 py-2">Username</th>
                    <th class="px-4 py-2">Role</th>
                    <th class="px-4 py-2">Created At</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td class="px-4 py-2"><?php echo $user['id']; ?></td>
                    <td class="px-4 py-2"><?php echo htmlspecialchars($user['username']); ?></td>
                    <td class="px-4 py-2"><?php echo $user['role']; ?></td>
                    <td class="px-4 py-2"><?php echo $user['created_at']; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h2 class="text-2xl font-bold mt-8 mb-6">Recent Invoices</h2>
        <table class="bg-white rounded-lg shadow-md w-full">
            <thead>
                <tr class="bg-gray-200">
                    <th class="px-4 py-2">ID</th>
                    <th class="px-4 py-2">Customer</th>
                    <th class="px-4 py-2">Total</th>
                    <th class="px-4 py-2">Created At</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invoices as $invoice): ?>
                <tr>
                    <td class="px-4 py-2"><?php echo $invoice['id']; ?></td>
                    <td class="px-4 py-2"><?php echo htmlspecialchars($invoice['customer_name']); ?></td>
                    <td class="px-4 py-2"><?php echo $invoice['grand_total']; ?> ₾</td>
                    <td class="px-4 py-2"><?php echo $invoice['created_at']; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>