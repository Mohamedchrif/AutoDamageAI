<?php
session_start();
require_once 'config.php';

if (!isset($_GET['token'])) {
    die("Invalid request.");
}
$token = $_GET['token'];

try {
    $pdo->exec("ALTER TABLE users ADD COLUMN reset_token VARCHAR(255) DEFAULT NULL;");
} catch(PDOException $e) {}

$stmt = $pdo->prepare("SELECT id FROM users WHERE reset_token = ?");
$stmt->execute([$token]);
$user = $stmt->fetch();

if (!$user) {
    die("Invalid or expired reset token.");
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    $pass = $_POST['password'];
    $confirm = $_POST['confirm'];
    
    if ($pass === $confirm) {
        if (strlen($pass) >= 8) {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $update = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL WHERE id = ?");
            if ($update->execute([$hash, $user['id']])) {
                set_flash_message('success', "Password successfully reset! You can now login securely.");
                header("Location: login.php");
                exit;
            } else {
                $error = "System error occurred.";
            }
        } else {
            $error = "Password must be at least 8 characters long.";
        }
    } else {
        $error = "Passwords do not match.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AutoDamg | Reset Password</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/reset_password.css">
</head>
<body>
    <div class="auth-page">
        <!-- Floating Decoration Elements -->
        <div class="decoration circle-1"></div>
        <div class="decoration circle-2"></div>
        
        <div class="auth-card">
            <div class="auth-header">
                <a href="index.php" class="auth-logo">
                    <i class="fas fa-car-alt"></i>
                    <span>AutoDamg</span>
                </a>
                <h1 class="auth-title">New Password</h1>
                <p class="auth-subtitle">Great! We've verified your link. Now, choose a strong password to keep your account secure.</p>
            </div>

            <?php if(!empty($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="reset_password.php?token=<?= htmlspecialchars($token) ?>" class="auth-form">
                <div class="form-group">
                    <label class="form-label">New Password</label>
                    <div class="input-with-icon">
                        <i class="fas fa-lock icon"></i>
                        <input type="password" name="password" class="form-input" placeholder="Min. 8 characters" minlength="8" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Confirm Password</label>
                    <div class="input-with-icon">
                        <i class="fas fa-shield-alt icon"></i>
                        <input type="password" name="confirm" class="form-input" placeholder="Repeat password" minlength="8" required>
                    </div>
                </div>

                <button type="submit" class="auth-btn">
                    <span>Reset Securely</span>
                    <i class="fas fa-check-circle"></i>
                </button>
            </form>
        </div>
    </div>
    <script src="js/reset_password.js"></script>
</body>
</html>
