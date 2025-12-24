<?php
require '../config.php';

if (!isset($_SESSION['user_id']) || !currentUserCan('manage_users')) {
    header('Location: ../login.php');
    exit;
}

// Handle create / update / delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Create User
    if (isset($_POST['create_user'])) {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        $role = in_array($_POST['role'], ['admin', 'manager']) ? $_POST['role'] : 'manager';

        if (!$username || !$password) {
            $error = 'Username and password are required.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } else {
            // Check if username exists
            $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $error = 'Username already exists.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, role, created_by) VALUES (?, ?, ?, ?)');
                $stmt->execute([$username, $hash, $role, $_SESSION['user_id']]);

                $stmt = $pdo->prepare('INSERT INTO audit_logs (user_id, action, details, ip) VALUES (?, ?, ?, ?)');
                $stmt->execute([$_SESSION['user_id'], 'create_user', "username={$username}, role={$role}", $_SERVER['REMOTE_ADDR'] ?? '']);

                $success = 'User created.';
            }
        }
    }

    // Update User
    if (isset($_POST['update_user'])) {
        $user_id = (int)$_POST['user_id'];
        $role = in_array($_POST['role'], ['admin', 'manager']) ? $_POST['role'] : 'manager';

        if ($user_id === $_SESSION['user_id']) {
            $error = 'You cannot change your own role.';
        } else {
            $stmt = $pdo->prepare('UPDATE users SET role = ? WHERE id = ?');
            $stmt->execute([$role, $user_id]);

            $stmt = $pdo->prepare('INSERT INTO audit_logs (user_id, action, details, ip) VALUES (?, ?, ?, ?)');
            $stmt->execute([$_SESSION['user_id'], 'update_user', "user_id={$user_id}, role={$role}", $_SERVER['REMOTE_ADDR'] ?? '']);

            $success = 'User updated.';
        }
    }

    // Delete User
    if (isset($_POST['delete_user'])) {
        $user_id = (int)$_POST['user_id'];

        if ($user_id === $_SESSION['user_id']) {
            $error = 'You cannot delete your own account.';
        } else {
            // Check if last admin
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE role = ?');
            $stmt->execute(['admin']);
            $adminCount = $stmt->fetchColumn();
            $stmt = $pdo->prepare('SELECT role FROM users WHERE id = ?');
            $stmt->execute([$user_id]);
            $userRole = $stmt->fetchColumn();

            if ($userRole === 'admin' && $adminCount <= 1) {
                $error = 'Cannot delete the last admin.';
            } else {
                $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
                $stmt->execute([$user_id]);

                $stmt = $pdo->prepare('INSERT INTO audit_logs (user_id, action, details, ip) VALUES (?, ?, ?, ?)');
                $stmt->execute([$_SESSION['user_id'], 'delete_user', "user_id={$user_id}", $_SERVER['REMOTE_ADDR'] ?? '']);

                $success = 'User deleted.';
            }
        }
    }
}

// Fetch users
$stmt = $pdo->query('SELECT id, username, role, created_at FROM users ORDER BY created_at DESC');
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>მომხმარებლების მართვა - ავტო სერვისი</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Georgian:wght@300;400;500;600;700&display=swap" rel="stylesheet>
    <link rel="stylesheet" href="https://web-fonts.ge/bpg-arial/" />
    <link rel="stylesheet" href="https://web-fonts.ge/bpg-arial-caps/" />
    <style>
        body { font-family: 'Noto Sans Georgian', 'BPG Arial', 'BPG Arial Caps', sans-serif; }
        .fade-in { animation: fadeIn 0.3s ease-in; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <?php include '../partials/sidebar.php'; ?>

    <div class="min-h-full overflow-auto ml-0 md:ml-64 pt-4 pl-4">
        <div class="p-6">
            <h1 class="text-3xl font-bold mb-6">მომხმარებლების მართვა</h1>

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

            <!-- Create User Form -->
            <div class="bg-white p-6 rounded shadow mb-6">
                <h2 class="text-xl font-semibold mb-4">ახალი მომხმარებლის შექმნა</h2>
                <form method="post" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <input type="text" name="username" placeholder="მომხმარებლის სახელი" required class="border p-2 rounded">
                    <input type="password" name="password" placeholder="პაროლი" required class="border p-2 rounded">
                    <select name="role" class="border p-2 rounded">
                        <option value="manager">მენეჯერი</option>
                        <option value="admin">ადმინისტრატორი</option>
                    </select>
                    <button type="submit" name="create_user" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">მომხმარებლის შექმნა</button>
                </form>
            </div>

            <!-- Users List -->
            <div class="bg-white p-6 rounded shadow">
                <h2 class="text-xl font-semibold mb-4">არსებული მომხმარებლები</h2>
                <div class="overflow-x-auto">
                    <table class="w-full table-auto">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-4 py-2 text-left">მომხმარებლის სახელი</th>
                                <th class="px-4 py-2 text-left">როლი</th>
                                <th class="px-4 py-2 text-left">შექმნილია</th>
                                <th class="px-4 py-2 text-left">მოქმედებები</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr class="border-t">
                                    <td class="px-4 py-2"><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td class="px-4 py-2"><?php echo htmlspecialchars($user['role']); ?></td>
                                    <td class="px-4 py-2"><?php echo htmlspecialchars($user['created_at']); ?></td>
                                    <td class="px-4 py-2">
                                        <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                                            <form method="post" class="inline" onsubmit="return confirm('დარწმუნებული ხართ?');">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <select name="role" onchange="this.form.submit()" class="border p-1 rounded">
                                                    <option value="manager" <?php echo $user['role'] === 'manager' ? 'selected' : ''; ?>>მენეჯერი</option>
                                                    <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>ადმინისტრატორი</option>
                                                </select>
                                                <input type="hidden" name="update_user" value="1">
                                            </form>
                                            <form method="post" class="inline ml-2" onsubmit="return confirm('წავშალოთ ეს მომხმარებელი?');">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" name="delete_user" class="bg-red-500 text-white px-2 py-1 rounded hover:bg-red-600">წაშლა</button>
                                            </form>
                                        <?php else: ?>
                                            <em>მიმდინარე მომხმარებელი</em>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>