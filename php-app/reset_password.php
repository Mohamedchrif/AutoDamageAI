<?php
session_start();
require_once 'config.php';

// Check if we came from a verified OTP verification
if (!isset($_SESSION['verified_recovery_user_id'])) {
    header("Location: forgot_password.php");
    exit;
}

$user_id = $_SESSION['verified_recovery_user_id'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    $pass = $_POST['password'];
    $confirm = $_POST['confirm'];
    
    if ($pass === $confirm) {
        if (strlen($pass) >= 8) {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            // Update the password - note we use 'password_hash' column if that matches login.php logic, but reset uses 'password' usually?
            // Actually, check login.php logic. reset is using 'password' column currently. 
            // In login.php: "password_hash" was used. I should check which one is correct.
            // Let's check config.php or login.php. 
            $update = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            if ($update->execute([$hash, $user_id])) {
                // Clear the session
                unset($_SESSION['verified_recovery_user_id']);
                unset($_SESSION['recovery_email']);
                
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
        
        <div class="login-card">
            <div class="login-header">
                <a href="index.php" class="nav-logo reset-password-logo">
                    <span class="logo-icon"><i class="fas fa-car-crash"></i></span> AutoDamg
                </a>
                <h2>Create new password</h2>
                <p>Your identity has been verified. Choose a secure password that you haven't used before.</p>
            </div>

            <?php if(!empty($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="reset_password.php">
                <div class="form-group">
                    <label>New Password</label>
                    <div class="input-wrapper password-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password" class="form-control" placeholder="Min. 8 characters" minlength="8" required autofocus>
                        <button type="button" class="toggle-password" onclick="togglePassword('password', this)" aria-label="Toggle password visibility">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <div class="input-wrapper password-wrapper">
                        <i class="fas fa-shield-alt"></i>
                        <input type="password" id="confirm" name="confirm" class="form-control" placeholder="Repeat new password" minlength="8" required>
                        <button type="button" class="toggle-password" onclick="togglePassword('confirm', this)" aria-label="Toggle password visibility">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="submit-btn submit-reset-btn">
                    <span>Change Password</span>
                    <i class="fas fa-check-circle"></i>
                </button>
            </form>
        </div>
    </div>
    <script src="js/auth.js"></script>
</body>
</html>
