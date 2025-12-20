<?php
require '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// Fetch logs
$stmt = $pdo->query("SELECT al.id, al.action, al.details, al.ip, al.created_at, u.username as actor FROM audit_logs al LEFT JOIN users u ON u.id = al.user_id ORDER BY al.created_at DESC LIMIT 200");
$logs = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Audit Logs - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-6">
    <div class="container mx-auto">
        <a href="index.php" class="text-blue-500 hover:underline mb-4 inline-block">&larr; Back</a>

        <div class="bg-white p-6 rounded-lg shadow-md">
            <h2 class="text-2xl font-bold mb-4">Recent Audit Logs</h2>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-2 py-2">When</th>
                            <th class="px-2 py-2">Action</th>
                            <th class="px-2 py-2">Details</th>
                            <th class="px-2 py-2">Actor</th>
                            <th class="px-2 py-2">IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <tr class="border-t">
                            <td class="px-2 py-2"><?php echo $log['created_at']; ?></td>
                            <td class="px-2 py-2"><?php echo htmlspecialchars($log['action']); ?></td>
                            <td class="px-2 py-2"><?php echo htmlspecialchars($log['details']); ?></td>
                            <td class="px-2 py-2"><?php echo htmlspecialchars($log['actor']); ?></td>
                            <td class="px-2 py-2"><?php echo htmlspecialchars($log['ip']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>