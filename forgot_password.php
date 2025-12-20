<?php
require 'config.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);

    // Look up user
    $stmt = $pdo->prepare('SELECT id, username FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    // Always show a neutral message (do not reveal whether user exists)
    $message = 'If an account exists for that username, a password reset link has been generated.';

    if ($user) {
        $token = bin2hex(random_bytes(32)); // 64 chars
        $token_hash = hash('sha256', $token);
        $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour

        $stmt = $pdo->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)');
        $stmt->execute([$user['id'], $token_hash, $expires]);

        // In production: send $token via email to user's registered email
        // For now: show the link so admin can use it directly (remove for production)
        $host = $_SERVER['HTTP_HOST'];
        $link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . "://$host" . dirname($_SERVER['REQUEST_URI']) . "/reset_password.php?token=$token";
        $message .= "\nReset link (use once): $link";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Forgot Password - Auto Shop</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="bg-white p-8 rounded-lg shadow-md w-96">
        <h2 class="text-2xl font-bold mb-4">Forgot Password</h2>
        <?php if ($message): ?>
            <div class="text-sm bg-yellow-50 border-l-4 border-yellow-400 p-3 mb-4 whitespace-pre-wrap"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <form method="post">
            <label class="block text-gray-700 mb-2">Username</label>
            <input type="text" name="username" required class="w-full px-3 py-2 border rounded mb-4">
            <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded hover:bg-blue-500">Generate Reset Link</button>
        </form>

        <p class="mt-4 text-xs text-gray-500">A reset link will be valid for one hour. In production, links should be emailed to the user's registered address.</p>
        <p class="mt-2"><a href="login.php" class="text-blue-500 hover:underline">Back to Login</a></p>
    </div>
</body>
</html>