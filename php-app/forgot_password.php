<?php
session_start();
require_once 'config.php';

if (is_logged_in()) {
    header("Location: dashboard.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $email = trim($_POST['email']);
    
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user) {
        $token = bin2hex(random_bytes(32));
        
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN reset_token VARCHAR(255) DEFAULT NULL;");
        } catch(PDOException $e) {}
        
        $update = $pdo->prepare("UPDATE users SET reset_token = ? WHERE id = ?");
        $update->execute([$token, $user['id']]);
        
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $path = dirname($_SERVER['PHP_SELF']);
        $ifSlash = substr($path, -1) == '/' ? '' : '/';
        $reset_link = $protocol . "://" . $host . $path . $ifSlash . "reset_password.php?token=" . $token;
        
        set_flash_message('success', "Since this is a local environment, here is your reset link:<br><br><a href='$reset_link' style='color:white; text-decoration:underline; word-break:break-all; font-weight:700;'>$reset_link</a>");
    } else {
        set_flash_message('success', "If your email is registered, you will receive a reset link shortly.");
    }
    header("Location: forgot_password.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AutoDamg | Forgot Password</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/forgot_password.css">
</head>
<body>
    <div class="auth-page">
        <!-- Floating Decoration Elements -->
        <div class="decoration circle-1"></div>
        <div class="decoration circle-2"></div>
        <div class="decoration mesh-gradient"></div>

        <div class="auth-card">
            <div class="auth-header">
                <a href="index.php" class="auth-logo">
                    <i class="fas fa-car-alt"></i>
                    <span>AutoDamg</span>
                </a>
                <h1 class="auth-title">Password Recovery</h1>
                <p class="auth-subtitle">Lost your way? No worries. Enter your email and we'll send you a magic link to get back in.</p>
            </div>

            <?php display_flash_messages(); ?>

            <form method="POST" action="forgot_password.php" class="auth-form">
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <div class="input-with-icon">
                        <i class="fas fa-envelope icon"></i>
                        <input type="email" name="email" class="form-input" placeholder="e.g. alex@example.com" required>
                    </div>
                </div>

                <button type="submit" class="auth-btn">
                    <span>Send Recovery Link</span>
                    <i class="fas fa-arrow-right"></i>
                </button>
            </form>

            <div class="auth-footer">
                <p>Remembered? <a href="login.php">Back to Login</a></p>
                <div style="margin-top: 1.5rem;">
                    <a href="index.php" style="font-size: 0.85rem; opacity: 0.8;">
                        <i class="fas fa-chevron-left" style="font-size: 0.7rem;"></i> Exit to Home
                    </a>
                </div>
            </div>
        </div>
    </div>
    <script src="js/forgot_password.js"></script>
</body>
</html>
