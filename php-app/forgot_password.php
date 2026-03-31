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
        // Generate a 6-digit OTP code
        $otp = sprintf("%06d", mt_rand(1, 999999));
        $expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        
        try {
            // Ensure necessary columns exist for OTP flow
            $pdo->exec("ALTER TABLE users ADD COLUMN reset_code VARCHAR(6) DEFAULT NULL;");
            $pdo->exec("ALTER TABLE users ADD COLUMN reset_expiry DATETIME DEFAULT NULL;");
        } catch(PDOException $e) {
            // Columns likely already exist
        }
        
        $update = $pdo->prepare("UPDATE users SET reset_code = ?, reset_expiry = ? WHERE id = ?");
        $update->execute([$otp, $expiry, $user['id']]);
        
        // Save user email in session for the verification step
        $_SESSION['recovery_email'] = $email;
        
        // In local env, display the code clearly
        set_flash_message('success', "Security Code: $otp");
        set_flash_message('success', "Since this is a local environment, we've displayed the 6-digit code above. In production, this would be sent to $email.");
        
        header("Location: verify_code.php");
        exit;
    } else {
        set_flash_message('danger', "We couldn't find an account with that email address.");
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

        <div class="login-card">
            <div class="login-header">
                <a href="index.php" class="nav-logo login-logo">
                    <span class="logo-icon"><i class="fas fa-car-crash"></i></span> AutoDamg
                </a>
                <h2>Password Recovery</h2>
                <p>Lost your way? No worries. Enter your email and we'll send you a link to get back in.</p>
            </div>

            <?php display_flash_messages(); ?>

            <form method="POST" action="forgot_password.php">
                <div class="form-group">
                    <label>Email Address</label>
                    <div class="input-wrapper">
                        <i class="fas fa-envelope"></i>
                        <input type="email" name="email" class="form-control" placeholder="e.g. alex@example.com" required>
                    </div>
                </div>

                <button type="submit" class="submit-btn">
                    <span>Send Recovery Link</span>
                    <i class="fas fa-arrow-right"></i>
                </button>
            </form>

            <div class="footer-text">
                <p>Remembered? <a href="login.php">Back to Login</a></p>
                <div class="exit-home-wrapper">
                    <a href="index.php" class="exit-home-link">
                        <i class="fas fa-chevron-left exit-home-icon"></i> Exit to Home
                    </a>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
