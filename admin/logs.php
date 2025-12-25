<?php
require '../config.php';

if (!isset($_SESSION['user_id']) || !currentUserCan('view_logs')) {
    header('Location: ../login.php');
    exit;
}

// Fetch logs
$stmt = $pdo->query("SELECT al.id, al.action, al.details, al.ip, al.created_at, u.username as actor FROM audit_logs al LEFT JOIN users u ON u.id = al.user_id ORDER BY al.created_at DESC LIMIT 200");
$logs = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>აუდიტის ლოგები - ადმინისტრატორი</title>
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Georgian Fonts -->
    <link rel="stylesheet" href="https://web-fonts.ge/bpg-arial/" />
    <link rel="stylesheet" href="https://web-fonts.ge/bpg-arial-caps/" />
    
    <style>
        body { font-family: 'BPG Arial', 'BPG Arial Caps'; }
        .fade-in { animation: fadeIn 0.3s ease-in; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    </style>
</head>
<body class="bg-gray-100 p-4 md:p-6 min-h-screen overflow-x-hidden font-sans antialiased">
    <?php include __DIR__ . '/../partials/sidebar.php'; ?>
    <div class="container mx-auto p-4 md:p-6 ml-0 md:ml-64">
        <a href="../create.php" class="text-blue-500 hover:underline mb-4 inline-block">&larr; უკან</a>

        <div class="bg-white p-4 md:p-6 rounded-lg shadow-md">
            <h2 class="text-2xl font-bold mb-4">ბოლო აუდიტის ლოგები</h2>

            <div class="overflow-x-auto">
                <table class="w-full text-xs sm:text-sm min-w-[600px]">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-2 md:px-4 py-2 text-left">როდის</th>
                            <th class="px-2 md:px-4 py-2 text-left">მოქმედება</th>
                            <th class="px-2 md:px-4 py-2 text-left">დეტალები</th>
                            <th class="px-2 md:px-4 py-2 text-left">მომხმარებელი</th>
                            <th class="px-2 md:px-4 py-2 text-left">IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <tr class="border-t hover:bg-gray-50">
                            <td class="px-2 md:px-4 py-2 truncate max-w-[140px]"><?php echo $log['created_at']; ?></td>
                            <td class="px-2 md:px-4 py-2 truncate max-w-[120px]"><?php echo htmlspecialchars($log['action']); ?></td>
                            <td class="px-2 md:px-4 py-2 truncate max-w-[200px]"><?php echo htmlspecialchars($log['details']); ?></td>
                            <td class="px-2 md:px-4 py-2 truncate max-w-[100px]"><?php echo htmlspecialchars($log['actor']); ?></td>
                            <td class="px-2 md:px-4 py-2 truncate max-w-[120px]"><?php echo htmlspecialchars($log['ip']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>