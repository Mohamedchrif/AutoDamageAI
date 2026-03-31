<?php
// config.php — Database config, session init, helpers

if (!function_exists('autodamg_load_env')) {
    function autodamg_load_env(string $path): void {
        if (!is_readable($path)) {
            return;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || (isset($line[0]) && $line[0] === '#')) {
                continue;
            }
            $eq = strpos($line, '=');
            if ($eq === false) {
                continue;
            }
            $k = trim(substr($line, 0, $eq));
            $v = trim(substr($line, $eq + 1));
            if ($k === '') {
                continue;
            }
            $len = strlen($v);
            if ($len >= 2 && $v[0] === '"' && $v[$len - 1] === '"') {
                $v = stripcslashes(substr($v, 1, -1));
            } elseif ($len >= 2 && $v[0] === "'" && $v[$len - 1] === "'") {
                $v = substr($v, 1, -1);
            }
            if (!array_key_exists($k, $_ENV)) {
                $_ENV[$k] = $v;
                putenv($k . '=' . $v);
            }
        }
    }
}

autodamg_load_env(__DIR__ . '/.env');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db_host    = $_ENV['AUTODAMG_DB_HOST'] ?? '127.0.0.1';
$db_name    = $_ENV['AUTODAMG_DB_NAME'] ?? 'autodamg_db';
$db_user    = $_ENV['AUTODAMG_DB_USER'] ?? 'root';
$db_pass    = $_ENV['AUTODAMG_DB_PASS'] ?? '';
$db_charset = $_ENV['AUTODAMG_DB_CHARSET'] ?? 'utf8mb4';

try {
    $dsn     = "mysql:host=$db_host;dbname=$db_name;charset=$db_charset";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (\PDOException $e) {
    die(
        'Database connection failed: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
        . "\n\nCreate the database and tables by importing php-app/autodamg_db.sql into MySQL, "
        . 'or copy .env.example to .env and set AUTODAMG_DB_* credentials.'
    );
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

function display_flash_messages(?string $filter = null): void {
    if (!empty($_SESSION['flash_messages'])) {
        $found = false;
        $html = '<div class="flash-messages">';
        foreach ($_SESSION['flash_messages'] as $key => $flash) {
            $cat = $flash['category'];

            // Handle filtering
            if ($filter !== null) {
                if ($filter === 'password_inline') {
                    if (strpos($cat, 'password_') !== 0) continue;
                } elseif ($cat !== $filter) {
                    continue;
                }
            } else {
                // If main display (filter null), exclude all 'password_' ones
                if (strpos($cat, 'password_') === 0) continue;
            }

            $msg = htmlspecialchars($flash['message']);
            // Map specific categories to base alert types for CSS (e.g. password_danger -> danger)
            $cssCat = str_replace(['password_danger', 'password_success'], ['danger', 'success'], $cat);
            $html .= "<div class=\"alert alert-" . htmlspecialchars($cssCat) . "\">{$msg}</div>";
            unset($_SESSION['flash_messages'][$key]);
            $found = true;
        }
        $html .= '</div>';
        if ($found) echo $html;
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
        $userId,
    ]);
}

function update_user_role(PDO $pdo, int $userId, string $role): bool {
    if (!in_array($role, ['user', 'admin'])) {
        return false;
    }
    $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
    return $stmt->execute([$role, $userId]);
}

// ── Upload / analysis helpers ─────────────────────────────────
// Analysis images live in DB as data URIs. autodamg_delete_analysis_files only removes legacy
// disk paths under uploads/ (if that folder exists); data: URIs are skipped.
function autodamg_delete_analysis_files(?string $resultJson, ?string $annotatedImage): void {
    $uploadRoot = realpath(__DIR__ . '/uploads');
    if ($uploadRoot === false) {
        return;
    }

    $tryUnlink = function (string $relative) use ($uploadRoot): void {
        if ($relative === '' || strpos($relative, 'uploads/') !== 0) {
            return;
        }
        $full = realpath(__DIR__ . '/' . str_replace('/', DIRECTORY_SEPARATOR, $relative));
        if ($full === false || !is_file($full)) {
            return;
        }
        $rootNorm = str_replace('\\', '/', $uploadRoot);
        $fullNorm = str_replace('\\', '/', $full);
        if (strpos($fullNorm, rtrim($rootNorm, '/')) !== 0) {
            return;
        }
        @unlink($full);
    };

    $decoded = $resultJson ? json_decode($resultJson, true) : null;
    if (is_array($decoded) && !empty($decoded['original_image']) && is_string($decoded['original_image'])) {
        $tryUnlink($decoded['original_image']);
    }
    if (!empty($annotatedImage) && is_string($annotatedImage)) {
        $tryUnlink($annotatedImage);
    }
}

function autodamg_flask_predict_url(): string {
    $u = $_ENV['AUTODAMG_FLASK_PREDICT_URL'] ?? '';
    return $u !== '' ? $u : 'http://127.0.0.1:5000/predict';
}
