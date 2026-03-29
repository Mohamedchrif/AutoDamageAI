<?php
require_once 'config.php';
require_admin(); 

if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    try {
        switch ($action) {
            case 'toggle_block':
                $userId = (int)($_POST['user_id'] ?? 0);
                $blocked = (bool)($_POST['blocked'] ?? false);
                $reason = trim($_POST['reason'] ?? '');
                
                if (!$userId || $userId === $_SESSION['user_id']) {
                    throw new Exception('Invalid user or cannot block yourself');
                }
                
                if (toggle_user_blocked($pdo, $userId, $blocked, $blocked ? $reason : null)) {
                    echo json_encode(['success' => true, 'message' => $blocked ? 'User blocked' : 'User unblocked']);
                } else {
                    throw new Exception('Failed to update user status');
                }
                break;
                
            case 'update_role':
                $userId = (int)($_POST['user_id'] ?? 0);
                $role = $_POST['role'] ?? '';
                
                if (!$userId || $userId === $_SESSION['user_id'] || !in_array($role, ['user', 'admin'])) {
                    throw new Exception('Invalid parameters');
                }
                
                if (update_user_role($pdo, $userId, $role)) {
                    echo json_encode(['success' => true, 'message' => "Role updated to $role"]);
                } else {
                    throw new Exception('Failed to update role');
                }
                break;
                
            case 'get_user_reports':
                $userId = (int)($_GET['user_id'] ?? 0);
                if (!$userId) throw new Exception('Invalid user ID');
                
                $user = get_user_with_status($pdo, $userId);
                $analyses = get_user_analyses($pdo, $userId);
                
                echo json_encode([
                    'success' => true,
                    'user' => $user,
                    'analyses' => $analyses,
                    'count' => count($analyses)
                ]);
                break;
                
            case 'filter_users':
                $filters = [
                    'search' => trim($_GET['search'] ?? ''),
                    'role' => $_GET['role'] ?? '',
                    'blocked' => $_GET['blocked'] ?? '',
                    'limit' => 100
                ];
                
                $users = get_all_users($pdo, $filters);
                $totalUsers = count($users);
                $blockedCount = count(array_filter($users, fn($u) => $u['blocked']));
                $adminCount = count(array_filter($users, fn($u) => $u['role'] === 'admin'));
                
                ob_start();
                if ($users):
                    foreach ($users as $user): 
                        $isCurrentUser = $user['id'] === $_SESSION['user_id'];
                        $isBlocked = (bool)$user['blocked'];
                ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($user['username']) ?></strong>
                                <?php if ($isCurrentUser): ?>
                                <span class="badge" style="background:#fef3c7;color:#92400e;margin-left:0.5rem;">You</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td>
                                <span class="badge badge-role-<?= $user['role'] ?>">
                                    <i class="fas fa-<?= $user['role'] === 'admin' ? 'crown' : 'user' ?>"></i>
                                    <?= ucfirst($user['role']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($isBlocked): ?>
                                    <span class="badge badge-blocked"><i class="fas fa-ban"></i> Blocked</span>
                                <?php else: ?>
                                    <span class="badge badge-active"><i class="fas fa-check"></i> Active</span>
                                <?php endif; ?>
                            </td>
                            <td><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                            <td>
                                <?php if (!$isCurrentUser): ?>
                                    <button class="action-btn btn-<?= $isBlocked ? 'unblock' : 'block' ?>" 
                                            onclick="toggleBlock(<?= $user['id'] ?>, <?= $isBlocked ? 'false' : 'true' ?>)">
                                        <i class="fas fa-<?= $isBlocked ? 'unlock' : 'ban' ?>"></i>
                                        <?= $isBlocked ? 'Unblock' : 'Block' ?>
                                    </button>
                                    <button class="action-btn btn-role" 
                                            onclick="updateRole(<?= $user['id'] ?>, '<?= $user['role'] === 'admin' ? 'user' : 'admin' ?>')">
                                        <i class="fas fa-exchange-alt"></i>
                                        Make <?= $user['role'] === 'admin' ? 'User' : 'Admin' ?>
                                    </button>
                                <?php endif; ?>
                                <button class="action-btn btn-reports" onclick="viewReports(<?= $user['id'] ?>)">
                                    <i class="fas fa-file-alt"></i> Reports
                                </button>
                            </td>
                        </tr>
                <?php 
                    endforeach; 
                else: ?>
                        <tr>
                            <td colspan="6" class="empty-state">
                                <i class="fas fa-users"></i>
                                <p>No users found matching your filters.</p>
                            </td>
                        </tr>
                <?php endif;
                $tableRows = ob_get_clean();
                
                echo json_encode([
                    'success' => true,
                    'tableRows' => $tableRows,
                    'stats' => [
                        'total' => $totalUsers,
                        'admins' => $adminCount,
                        'blocked' => $blockedCount
                    ]
                ]);
                break;
                
            default:
                throw new Exception('Unknown action');
        }
        exit;
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

$search = trim($_GET['search'] ?? '');
$roleFilter = $_GET['role'] ?? '';
$blockedFilter = $_GET['blocked'] ?? '';

$users = get_all_users($pdo, [
    'search' => $search,
    'role' => $roleFilter,
    'blocked' => $blockedFilter,
    'limit' => 100
]);
$totalUsers = count($users);
$blockedCount = count(array_filter($users, fn($u) => $u['blocked']));
$adminCount = count(array_filter($users, fn($u) => $u['role'] === 'admin'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AutoDamg | Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/admin.css">

</head>
<body>
    <?php include 'navbar.php'; ?>
 <div class="admin-container">
        <div class="admin-header">
            <h1 class="admin-title"><i class="fas fa-shield-alt"></i> Admin Dashboard</h1>
            <div class="admin-stats">
                <div class="stat-card">
                    <div class="stat-value" id="statTotal"><?= $totalUsers ?></div>
                    <div class="stat-label">Total Users</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="statAdmins"><?= $adminCount ?></div>
                    <div class="stat-label">Admins</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="statBlocked"><?= $blockedCount ?></div>
                    <div class="stat-label">Blocked</div>
                </div>
            </div>
        </div>

        <!-- Filters Bar (Real-Time AJAX) -->
        <div class="filters-bar" id="filtersBar">
            <input type="text" id="filterSearch" placeholder="Search users..." value="<?= htmlspecialchars($search) ?>" data-filter="search">
            <select id="filterRole" data-filter="role">
                <option value="">All Roles</option>
                <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Admins</option>
                <option value="user" <?= $roleFilter === 'user' ? 'selected' : '' ?>>Users</option>
            </select>
            <select id="filterBlocked" data-filter="blocked">
                <option value="">All Status</option>
                <option value="1" <?= $blockedFilter === '1' ? 'selected' : '' ?>>Blocked</option>
                <option value="0" <?= $blockedFilter === '0' ? 'selected' : '' ?>>Active</option>
            </select>
            <button type="button" id="filterApply"><i class="fas fa-filter"></i> Filter</button>
            <?php if ($search || $roleFilter || $blockedFilter): ?>
            <a href="admin.php" class="action-btn" style="background:#f1f5f9;color:#475569;"><i class="fas fa-times"></i> Clear</a>
            <?php endif; ?>
        </div>
        
        <!-- Filter Loading Indicator -->
        <div id="filterLoading">
            <div class="loading-spinner"></div>
            <small>Filtering users...</small>
        </div>

        <!-- Users Table -->
        <div style="overflow-x:auto;">
            <table class="users-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="usersTableBody">
                    <?php if ($users): ?>
                        <?php foreach ($users as $user): 
                            $isCurrentUser = $user['id'] === $_SESSION['user_id'];
                            $isBlocked = (bool)$user['blocked'];
                        ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($user['username']) ?></strong>
                                <?php if ($isCurrentUser): ?>
                                <span class="badge" style="background:#fef3c7;color:#92400e;margin-left:0.5rem;">You</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td>
                                <span class="badge badge-role-<?= $user['role'] ?>">
                                    <i class="fas fa-<?= $user['role'] === 'admin' ? 'crown' : 'user' ?>"></i>
                                    <?= ucfirst($user['role']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($isBlocked): ?>
                                    <span class="badge badge-blocked"><i class="fas fa-ban"></i> Blocked</span>
                                <?php else: ?>
                                    <span class="badge badge-active"><i class="fas fa-check"></i> Active</span>
                                <?php endif; ?>
                            </td>
                            <td><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                            <td>
                                <?php if (!$isCurrentUser): ?>
                                    <button class="action-btn btn-<?= $isBlocked ? 'unblock' : 'block' ?>" 
                                            onclick="toggleBlock(<?= $user['id'] ?>, <?= $isBlocked ? 'false' : 'true' ?>)">
                                        <i class="fas fa-<?= $isBlocked ? 'unlock' : 'ban' ?>"></i>
                                        <?= $isBlocked ? 'Unblock' : 'Block' ?>
                                    </button>
                                    <button class="action-btn btn-role" 
                                            onclick="updateRole(<?= $user['id'] ?>, '<?= $user['role'] === 'admin' ? 'user' : 'admin' ?>')">
                                        <i class="fas fa-exchange-alt"></i>
                                        Make <?= $user['role'] === 'admin' ? 'User' : 'Admin' ?>
                                    </button>
                                <?php endif; ?>
                                <button class="action-btn btn-reports" onclick="viewReports(<?= $user['id'] ?>)">
                                    <i class="fas fa-file-alt"></i> Reports
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="empty-state">
                                <i class="fas fa-users"></i>
                                <p>No users found matching your filters.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Reports Modal -->
    <div class="modal" id="reportsModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modalUserTitle">User Reports</h3>
                <button class="modal-close" onclick="closeReportsModal()">&times;</button>
            </div>
            <div class="modal-body" id="reportsModalBody">
                <div style="text-align:center;padding:2rem;">
                    <div class="loading-spinner"></div>
                    <p>Loading reports...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button class="action-btn" style="background:#f1f5f9;color:#475569;" onclick="closeReportsModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Block Reason Modal -->
    <div class="modal" id="blockModal">
        <div class="modal-content" style="max-width:500px;">
            <div class="modal-header">
                <h3 class="modal-title">Block User</h3>
                <button class="modal-close" onclick="closeBlockModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p>Provide a reason for blocking this user (optional):</p>
                <textarea id="blockReason" rows="3" style="width:100%;padding:0.75rem;border:1px solid var(--border-color);border-radius:6px;margin-top:1rem;" placeholder="e.g., Violation of terms, spam, etc."></textarea>
            </div>
            <div class="modal-footer">
                <button class="action-btn" style="background:#f1f5f9;color:#475569;" onclick="closeBlockModal()">Cancel</button>
                <button class="action-btn btn-block" onclick="confirmBlock()">Confirm Block</button>
            </div>
        </div>
    </div>

    <script src="js/admin.js"></script>
    <script src="js/nav.js"></script>
</body>
</html>