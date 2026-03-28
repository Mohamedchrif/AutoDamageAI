<?php
// config.php — Database config, session init, helpers

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db_host    = '127.0.0.1';
$db_name    = 'autodamg_db';
$db_user    = 'root';
$db_pass    = '';
$db_charset = 'utf8mb4';

try {
    // Auto-create the database if missing
    $pdo_init = new PDO("mysql:host=$db_host;charset=$db_charset", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $pdo_init->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    $dsn     = "mysql:host=$db_host;dbname=$db_name;charset=$db_charset";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);

    // ── Users table — WITH role and blocked columns ───────────
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id              INT AUTO_INCREMENT PRIMARY KEY,
            username        VARCHAR(80)  NOT NULL UNIQUE,
            email           VARCHAR(120) NOT NULL UNIQUE,
            role            ENUM('user', 'admin') DEFAULT 'user',
            blocked         TINYINT(1) DEFAULT 0,
            blocked_reason  VARCHAR(255) DEFAULT NULL,
            blocked_at      DATETIME DEFAULT NULL,
            phone           VARCHAR(30)  DEFAULT NULL,
            password_hash   VARCHAR(256) NOT NULL,
            profile_pic     LONGTEXT DEFAULT NULL,
            created_at      DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB
    ");

    // ── Analyses table ─────────────────────────────────────────
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS analyses (
            id                INT AUTO_INCREMENT PRIMARY KEY,
            user_id           INT DEFAULT NULL,
            filename          VARCHAR(256) NOT NULL,
            original_filename VARCHAR(256) DEFAULT NULL,
            file_size         BIGINT DEFAULT NULL,
            result_json       LONGTEXT NOT NULL,
            annotated_image   LONGTEXT DEFAULT NULL,
            cost_min          DECIMAL(10,2) DEFAULT 0,
            cost_max          DECIMAL(10,2) DEFAULT 0,
            total_detections  INT DEFAULT 0,
            is_undamaged      TINYINT(1) DEFAULT 0,
            timestamp         DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB
    ");

} catch (\PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ── Auth helpers ─────────────────────────────────────────────
function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}

function require_login(): bool {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    if (!is_logged_in()) {
        if ($isAjax) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode([
                'success'  => false,
                'error'    => 'Authentication required. Please log in again.',
                'redirect' => 'login.php',
            ]);
            exit;
        } else {
            header('Location: login.php');
            exit;
        }
    }
    
    // Block blocked users from accessing protected pages
    if (isset($_SESSION['blocked']) && $_SESSION['blocked']) {
        session_destroy();
        if ($isAjax) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Your account has been blocked.']);
            exit;
        }
        header('Location: login.php?blocked=1');
        exit;
    }
    
    return true;
}

function get_current_user_data(PDO $pdo): array|false {
    $stmt = $pdo->prepare("
        SELECT id, username, email, phone, role, blocked, created_at, profile_pic 
        FROM users WHERE id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

// ── Flash messages ────────────────────────────────────────────
function set_flash_message(string $category, string $message): void {
    if (!isset($_SESSION['flash_messages'])) {
        $_SESSION['flash_messages'] = [];
    }
    $_SESSION['flash_messages'][] = ['category' => $category, 'message' => $message];
}

function display_flash_messages(): void {
    if (!empty($_SESSION['flash_messages'])) {
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

// ── Admin Helpers ──────────────────────────────────────────────

function is_admin(): bool {
    return isset($_SESSION['user_id'], $_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function require_admin(): bool {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    
    if (!is_logged_in()) {
        if ($isAjax) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Authentication required.', 'redirect' => 'login.php']);
            exit;
        }
        header('Location: login.php');
        exit;
    }
    
    if (!is_admin()) {
        if ($isAjax) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Admin access required.']);
            exit;
        }
        header('Location: dashboard.php');
        exit;
    }
    return true;
}

function get_user_with_status(PDO $pdo, int $userId): array|false {
    $stmt = $pdo->prepare("
        SELECT id, username, email, phone, role, blocked, blocked_reason, blocked_at, created_at, profile_pic 
        FROM users WHERE id = ?
    ");
    $stmt->execute([$userId]);
    return $stmt->fetch();
}

function get_all_users(PDO $pdo, array $filters = []): array {
    $sql = "SELECT id, username, email, role, blocked, blocked_at, created_at FROM users WHERE 1=1";
    $params = [];
    
    if (!empty($filters['search'])) {
        $sql .= " AND (username LIKE ? OR email LIKE ?)";
        $params[] = "%{$filters['search']}%";
        $params[] = "%{$filters['search']}%";
    }
    if (isset($filters['role']) && $filters['role'] !== '') {
        $sql .= " AND role = ?";
        $params[] = $filters['role'];
    }
    if (isset($filters['blocked']) && $filters['blocked'] !== '') {
        $sql .= " AND blocked = ?";
        $params[] = (int)$filters['blocked'];
    }
    
    $sql .= " ORDER BY created_at DESC";
    
    if (!empty($filters['limit'])) {
        $sql .= " LIMIT ?";
        $params[] = (int)$filters['limit'];
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function get_user_analyses(PDO $pdo, int $userId, int $limit = 50): array {
    $stmt = $pdo->prepare("
        SELECT id, filename, original_filename, file_size, result_json, 
               cost_min, cost_max, total_detections, is_undamaged, timestamp
        FROM analyses 
        WHERE user_id = ? 
        ORDER BY timestamp DESC 
        LIMIT ?
    ");
    $stmt->execute([$userId, $limit]);
    return $stmt->fetchAll();
}

function toggle_user_blocked(PDO $pdo, int $userId, bool $blocked, ?string $reason = null): bool {
    $stmt = $pdo->prepare("
        UPDATE users 
        SET blocked = ?, blocked_reason = ?, blocked_at = ? 
        WHERE id = ?
    ");
    return $stmt->execute([
        $blocked ? 1 : 0,
        $reason,
        $blocked ? date('Y-m-d H:i:s') : null,
        $userId
    ]);
}

function update_user_role(PDO $pdo, int $userId, string $role): bool {
    if (!in_array($role, ['user', 'admin'])) return false;
    $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
    return $stmt->execute([$role, $userId]);
}
?>