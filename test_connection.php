<?php
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Connection Test</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen font-sans antialiased p-8">
    <div class="container mx-auto max-w-2xl bg-white rounded-lg shadow-md p-6">
        <h1 class="text-2xl font-bold mb-6">Database Connection Status</h1>

        <div class="space-y-4">
            <!-- MySQL Connection Test -->
            <div class="p-4 rounded-lg <?php echo $pdo ? 'bg-green-100 border-green-400' : 'bg-red-100 border-red-400'; ?>">
                <h2 class="font-semibold <?php echo $pdo ? 'text-green-800' : 'text-red-800'; ?>">MySQL Connection</h2>
                <?php
                if ($pdo) {
                    try {
                        $stmt = $pdo->query("SELECT 1");
                        echo "<p class='text-green-700'>Successfully connected to MySQL.</p>";
                    } catch (PDOException $e) {
                        echo "<p class='text-red-700'>Connection established, but query failed: " . htmlspecialchars($e->getMessage()) . "</p>";
                    }
                } else {
                    echo "<p class='text-red-700'>Failed to connect to MySQL.</p>";
                }
                ?>
            </div>

            <!-- SQL Server Connection Test -->
            <div class="p-4 rounded-lg <?php echo $sql_server_conn ? 'bg-green-100 border-green-400' : 'bg-red-100 border-red-400'; ?>">
                <h2 class="font-semibold <?php echo $sql_server_conn ? 'text-green-800' : 'text-red-800'; ?>">SQL Server Connection</h2>
                <?php
                if ($sql_server_conn) {
                    echo "<p class='text-green-700'>Successfully connected to SQL Server.</p>";
                } else {
                    echo "<p class='text-red-700'>Failed to connect to SQL Server.</p>";
                    if (function_exists('sqlsrv_errors')) {
                        echo "<pre class='text-sm mt-2'>" . print_r(sqlsrv_errors(), true) . "</pre>";
                    } else {
                        echo "<p class='text-red-700 mt-2'>The SQLSRV driver for PHP might not be installed or enabled.</p>";
                    }
                }
                ?>
            </div>
        </div>
    </div>
</body>
</html>
