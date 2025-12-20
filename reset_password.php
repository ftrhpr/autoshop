<?php
require 'config.php';

$token = $_GET['token'] ?? $_POST['token'] ?? null;
$state = '';

if (!$token) {
    die('Invalid request');
}

$token_hash = hash('sha256', $token);

// Fetch token record
$stmt = $pdo->prepare('SELECT pr.id, pr.user_id, pr.expires_at, pr.used, u.username FROM password_resets pr JOIN users u ON u.id = pr.user_id WHERE pr.token_hash = ? LIMIT 1');
$stmt->execute([$token_hash]);
$record = $stmt->fetch();

if (!$record) {
    $state = 'invalid';
} elseif ($record['used']) {
    $state = 'used';
} elseif (strtotime($record['expires_at']) < time()) {
    $state = 'expired';
} else {
    $state = 'valid';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $state === 'valid') {
    $password = $_POST['password'] ?? '';
    if (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
        $stmt->execute([$hash, $record['user_id']]);

        // Mark token used
        $stmt = $pdo->prepare('UPDATE password_resets SET used = 1 WHERE id = ?');
        $stmt->execute([$record['id']]);

        // Optionally, log user in or redirect
        $success = 'Password updated successfully. You may now log in.';
        $state = 'done';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Reset Password - Auto Shop</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="bg-white p-8 rounded-lg shadow-md w-96">
        <h2 class="text-2xl font-bold mb-4">Reset Password</h2>

        <?php if ($state === 'invalid'): ?>
            <div class="text-red-600">Invalid reset token.</div>
        <?php elseif ($state === 'used'): ?>
            <div class="text-gray-700">This reset link has already been used.</div>
        <?php elseif ($state === 'expired'): ?>
            <div class="text-gray-700">This reset link has expired. Please generate a new one.</div>
        <?php elseif ($state === 'done'): ?>
            <div class="text-green-600"><?php echo htmlspecialchars($success); ?></div>
            <p class="mt-4"><a href="login.php" class="text-blue-500 hover:underline">Go to Login</a></p>
        <?php elseif ($state === 'valid'): ?>
            <?php if (isset($error)): ?><div class="text-red-600 mb-2"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
            <form method="post">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                <label class="block text-gray-700 mb-2">New Password</label>
                <input type="password" name="password" required class="w-full px-3 py-2 border rounded mb-4">
                <button type="submit" class="w-full bg-green-600 text-white py-2 rounded hover:bg-green-500">Reset Password</button>
            </form>
        <?php endif; ?>

        <p class="mt-4 text-xs text-gray-500">If you didn't request this, you can safely ignore this message.</p>
    </div>
</body>
</html>