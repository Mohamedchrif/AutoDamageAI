<?php
require_once 'config.php';

if (is_logged_in()) {
    header("Location: dashboard.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT id, username, email, password_hash, role, blocked FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        if (!empty($user['blocked'])) {
            set_flash_message('danger', 'Your account has been blocked. Contact support.');
        } else {
            // Handle "remember me" check
            if (!empty($_POST['remember'])) {
                $lifetime = 60 * 60 * 24 * 30; // 30 days
                session_set_cookie_params($lifetime);
                ini_set('session.gc_maxlifetime', $lifetime);
            }

            // Login success
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['blocked'] = (bool)($user['blocked'] ?? false);
            
            header("Location: dashboard.php");
            exit;
        }
    } else {
        set_flash_message('danger', 'Please check your login details and try again.');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AutoDamg | Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/login.css">
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-image">
            <h1>Professional Damage Analysis</h1>
            <p>Our AI system analyzes vehicle damage with extreme precision, providing expert insights for repair estimates.</p>
        </div>
        <div class="auth-content">
            <div class="login-card">
                <div class="login-header">
                    <a href="index.php" class="nav-logo login-logo">
                        <span class="logo-icon"><i class="fas fa-car-crash"></i></span> AutoDamg
                    </a>
                    <h2>Welcome Back</h2>
                    <p class="login-subtitle">Sign in to access your dashboard</p>
                </div>

                <?php display_flash_messages(); ?>

                <form method="POST" action="login.php">
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <div class="input-wrapper">
                            <i class="fas fa-envelope"></i>
                            <input type="email" id="email" name="email" class="form-control" placeholder="name@company.com" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="input-wrapper password-wrapper">
                            <i class="fas fa-lock"></i>
                            <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required>
                            <button type="button" class="toggle-password" onclick="togglePassword('password', this)" aria-label="Toggle password visibility">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="remember-forgot-row">
                        <div class="remember-wrapper">
                            <input type="checkbox" id="remember" name="remember" class="remember-checkbox">
                            <label for="remember" class="remember-label">Remember me</label>
                        </div>
                        <a href="forgot_password.php" class="forgot-pw-link">Forgot password?</a>
                    </div>

                    <button type="submit" class="submit-btn">Login to Account</button>
                </form>

                <div class="footer-text">
                    Don't have an account? <a href="signup.php">Join the platform</a>
                </div>
            </div>
        </div>
    </div>
<script src="js/login.js"></script>
</body>
</html>
