<?php
// config.php
// Database Configuration and Session initialization

// Prevent "session already started" errors
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db_host = '127.0.0.1';
$db_name = 'autodamg_db';
$db_user = 'root';        // Change to your MySQL username
$db_pass = '';            // Change to your MySQL password
$db_charset = 'utf8mb4';

try {
    // First connect without a DB to auto-create it if missing
    $pdo_init = new PDO("mysql:host=$db_host;charset=$db_charset", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $pdo_init->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    // Now connect to autodamg_db and create tables if they don't exist
    $dsn = "mysql:host=$db_host;dbname=$db_name;charset=$db_charset";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);

    // Auto-create tables — split into separate exec() calls (PDO limitation)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(80) NOT NULL UNIQUE,
            email VARCHAR(120) NOT NULL UNIQUE,
            phone VARCHAR(30) DEFAULT NULL,
            password_hash VARCHAR(256) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS analyses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT DEFAULT NULL,
            filename VARCHAR(256) NOT NULL,
            result_json LONGTEXT NOT NULL,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB
    ");
} catch (\PDOException $e) {
    die("Database Connection failed: " . $e->getMessage());
}

/**
 * Helper to check if user is logged in
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * Redirect if not logged in
 */
function require_login() {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Get current user from DB
 */
function get_current_user_data($pdo) {
    if (!is_logged_in()) return null;
    $stmt = $pdo->prepare("SELECT id, username, email, phone, profile_picture FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

/**
 * Helper for flash messages
 */
function set_flash_message($category, $message) {
    if (!isset($_SESSION['flash_messages'])) {
        $_SESSION['flash_messages'] = [];
    }
    $_SESSION['flash_messages'][] = ['category' => $category, 'message' => $message];
}

/**
 * Display flash messages
 */
function display_flash_messages() {
    if (isset($_SESSION['flash_messages']) && !empty($_SESSION['flash_messages'])) {
        echo '<div class="flash-messages">';
        foreach ($_SESSION['flash_messages'] as $flash) {
            $cat = htmlspecialchars($flash['category']);
            $msg = htmlspecialchars($flash['message']);
            echo "<div class=\"alert alert-{$cat}\">{$msg}</div>";
        }
        echo '</div>';
        unset($_SESSION['flash_messages']);
    }
}
?>
