<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $phone = trim($_POST['phone'] ?? '');

    if (empty($username) || empty($email) || empty($password)) {
        set_flash_message('danger', 'Please fill all required fields.');
    } else {
        // Check if user exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
        $stmt->execute([$email, $username]);
        if ($stmt->fetch()) {
            set_flash_message('danger', 'Email or Username already exists.');
        } else {
            // Hash password and insert
            // Flask used scrypt. PHP's password_hash defaults to bcrypt. Note: Users created in Flask won't be able to log in without re-hashing or writing a specific check, but we are migrating the system so new accounts will work seamlessly.
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, phone, role) VALUES (?, ?, ?, ?, 'user')");
            try {
                $stmt->execute([$username, $email, $hash, $phone]);
                set_flash_message('success', 'Account created! You can now login.');
                header("Location: login.php");
                exit;
            } catch (Exception $e) {
                set_flash_message('danger', 'Error creating account.');
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AutoDamg | Create Account</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/signup.css">
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-image">
            <h1>Expert Repair Support</h1>
            <p>Our platform connects advanced AI analysis with real-world repair workflows, empowering teams to deliver faster and better results.</p>
        </div>
        <div class="auth-content">
            <div class="signup-card">
                <div class="signup-header">
                    <a href="index.php" class="nav-logo signup-logo">
                        <span class="logo-icon"><i class="fas fa-car-crash"></i></span> AutoDamg
                    </a>
                    <h2>Create Account</h2>
                    <p class="signup-subtitle">Join our professional repair network</p>
                </div>

                <?php display_flash_messages(); ?>

                <form method="POST" action="signup.php">
                    <div class="form-group">
                        <label for="username">User Name</label>
                        <div class="input-wrapper">
                            <i class="fas fa-user-circle"></i>
                            <input type="text" id="username" name="username" class="form-control" placeholder="johndoe" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <div class="input-wrapper">
                            <i class="fas fa-envelope"></i>
                            <input type="email" id="email" name="email" class="form-control" placeholder="name@company.com" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="phone">Phone Number (Optional)</label>
                        <div class="input-wrapper">
                            <i class="fas fa-phone"></i>
                            <input type="tel" id="phone" name="phone" class="form-control" placeholder="+1 234 567 890">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="input-wrapper password-wrapper">
                            <i class="fas fa-lock"></i>
                            <input type="password" id="password" name="password" class="form-control" placeholder="Create a strong password" required minlength="8">
                            <button type="button" class="toggle-password" onclick="togglePassword('password', this)" aria-label="Toggle password visibility">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <p class="terms-text">
                        By creating an account, you agree to our <a href="#" class="terms-link">Terms</a> and <a href="#" class="terms-link">Privacy</a>.
                    </p>

                    <button type="submit" class="submit-btn btn-uppercase">Get Started Now</button>
                </form>

                <div class="footer-text">
                    Already an expert? <a href="login.php">Sign In</a>
                </div>
            </div>
        </div>
    </div>
<script src="js/auth.js"></script>
</body>
</html>
