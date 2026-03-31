<?php
session_start();
require_once 'config.php';

// If no recovery email in session, redirect back to forgot_password
if (!isset($_SESSION['recovery_email'])) {
    header("Location: forgot_password.php");
    exit;
}

$email = $_SESSION['recovery_email'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entered_code = trim($_POST['otp_code'] ?? '');
    
    $stmt = $pdo->prepare("SELECT id, reset_code, reset_expiry FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user) {
        $now = date('Y-m-d H:i:s');
        if ($user['reset_code'] === $entered_code && $user['reset_expiry'] > $now) {
            // Success! 
            $_SESSION['verified_recovery_user_id'] = $user['id'];
            // Clear the code and expiry
            $clear = $pdo->prepare("UPDATE users SET reset_code = NULL, reset_expiry = NULL WHERE id = ?");
            $clear->execute([$user['id']]);
            
            header("Location: reset_password.php");
            exit;
        } else {
            if ($user['reset_expiry'] <= $now) {
                $error = "This code has expired. Please request a new one.";
            } else {
                $error = "Wrong security code. Please check and try again.";
            }
        }
    } else {
        header("Location: forgot_password.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AutoDamg | Verify Code</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/verify_code.css">
</head>
<body>
    <div class="auth-page">
        <div class="login-card">
            <div class="login-header">
                <a href="index.php" class="nav-logo verify-logo">
                    <span class="logo-icon"><i class="fas fa-car-crash"></i></span> AutoDamg
                </a>
                <h2>Check your email</h2>
                <p>We've sent a 6-digit security code to <strong><?php echo htmlspecialchars($email); ?></strong>. Please enter it below.</p>
            </div>

            <?php display_flash_messages(); ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo $error; ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="verify_code.php">
                <div class="form-group">
                    <label>Enter 6-digit Code</label>
                    <div class="input-wrapper">
                        <i class="fas fa-key"></i>
                        <input type="text" name="otp_code" class="form-control otp-input" maxlength="6" pattern="\d{6}" placeholder="000000" required autofocus autocomplete="one-time-code">
                    </div>
                </div>

                <button type="submit" class="submit-btn">
                    <span>Continue</span>
                    <i class="fas fa-arrow-right"></i>
                </button>
            </form>

            <div class="footer-text">
                <p>Didn't receive a code? <a href="forgot_password.php">Resend Code</a></p>
                <div class="back-to-login-wrapper">
                    <a href="login.php" class="back-to-login-link">
                        <i class="fas fa-chevron-left back-to-login-icon"></i> Back to Login
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
