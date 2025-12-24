<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        header('Location: index.php');
        exit;
    } else {
        $error = 'Invalid username or password';
    }
}
?>

<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>შესვლა - ავტო სერვისი</title>
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Georgian Fonts -->
    <link rel="stylesheet" href="https://web-fonts.ge/bpg-arial/" />
    <link rel="stylesheet" href="https://web-fonts.ge/bpg-arial-caps/" />
    
    <style>
        body { font-family: 'BPG Arial', 'BPG Arial Caps'; }
    </style>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen overflow-x-hidden font-sans antialiased">
    <div class="bg-white p-6 md:p-8 rounded-lg shadow-md w-full max-w-sm mx-4">
        <h2 class="text-2xl font-bold mb-6 text-center">შესვლა</h2>
        <?php if (isset($error)) echo "<p class='text-red-500 mb-4'>არასწორი მომხმარებლის სახელი ან პაროლი</p>"; ?>
        <form method="post">
            <div class="mb-4">
                <label class="block text-gray-700 mb-1">მომხმარებლის სახელი</label>
                <input type="text" name="username" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition" required>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 mb-1">პაროლი</label>
                <input type="password" name="password" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition" required>
            </div>
            <button type="submit" class="w-full bg-blue-500 text-white py-2 rounded hover:bg-blue-600 transition">შესვლა</button>
        </form>
    </div>
</body>
</html>