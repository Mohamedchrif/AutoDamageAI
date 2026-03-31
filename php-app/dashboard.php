<?php
require_once 'config.php';
require_login();
$user = get_current_user_data($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_analysis_id'])) {
    $del_id = (int) $_POST['delete_analysis_id'];
    $fetch = $pdo->prepare("SELECT result_json, annotated_image FROM analyses WHERE id = ? AND user_id = ?");
    $fetch->execute([$del_id, $user['id']]);
    $row = $fetch->fetch();
    if ($row) {
        autodamg_delete_analysis_files($row['result_json'] ?? null, $row['annotated_image'] ?? null);
    }
    $del_stmt = $pdo->prepare("DELETE FROM analyses WHERE id = ? AND user_id = ?");
    $del_stmt->execute([$del_id, $user['id']]);
    set_flash_message('success', 'Analysis record deleted successfully.');
    header("Location: dashboard.php");
    exit;
}

// Pagination logic
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

$total_stmt = $pdo->prepare("SELECT COUNT(*) FROM analyses WHERE user_id = ?");
$total_stmt->execute([$user['id']]);
$total_analyses = $total_stmt->fetchColumn();
$total_pages = ceil($total_analyses / $per_page);

// Fetch recent analyses for the current user
$stmt = $pdo->prepare("SELECT * FROM analyses WHERE user_id = ? ORDER BY timestamp DESC LIMIT ? OFFSET ?");
$stmt->bindValue(1, $user['id'], PDO::PARAM_INT);
$stmt->bindValue(2, $per_page, PDO::PARAM_INT);
$stmt->bindValue(3, $offset, PDO::PARAM_INT);
$stmt->execute();
$analyses = $stmt->fetchAll();

// Process high severity count
$high_count = 0;
foreach ($analyses as &$a) {
    if (!empty($a['result_json'])) {
        $decoded = json_decode($a['result_json'], true);
        $a['result'] = $decoded ? $decoded : ['total_detections' => 0];
        if (isset($a['result']['total_detections']) && $a['result']['total_detections'] > 5) {
            $high_count++;
        }
    } else {
        $a['result'] = ['total_detections' => 0];
    }
}
unset($a);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AutoDamg | Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/dashboard.css">
</head>
<body>
    <div class="page-wrapper">
        <?php include 'navbar.php'; ?>

        <main class="main-content container dashboard-main">
            <header class="page-header dashboard-header">
                <div>
                    <h1 class="dashboard-title">Welcome Back, <span><?= htmlspecialchars($user['username']) ?></span></h1>
                    <p class="dashboard-subtitle">Review your latest vehicle inspection matches and history.</p>
                </div>
                <a href="analyze.php" class="submit-btn dashboard-new-btn">
                    <i class="fas fa-plus-circle"></i> New Analysis
                </a>
            </header>

            <?php display_flash_messages(); ?>

            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Inspections</h3>
                    <div class="value"><?= count($analyses) ?></div>
                </div>
                <div class="stat-card">
                    <h3>High Severity</h3>
                    <div class="value val-danger">
                        <?= $high_count ?>
                    </div>
                </div>
                <div class="stat-card">
                    <h3>System Status</h3>
                    <div class="value val-success">
                        <i class="fas fa-check-circle"></i> Operational
                    </div>
                </div>
            </div>

            <div class="history-table-card">
                <div class="history-header">
                    <h2 class="history-title">Inspection History</h2>
                    <div class="history-count">Total <?= $total_analyses ?> entries</div>
                </div>
                
                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>File Name</th>
                                <th>Findings</th>
                                <th>Severity Status</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($analyses) > 0): ?>
                                <?php foreach ($analyses as $analysis): ?>
                                    <tr>
                                        <td class="td-date" data-label="Date & Time"><?= date('M d, Y • H:i', strtotime($analysis['timestamp'])) ?></td>
                                        <td class="td-file" data-label="File Name"><?= htmlspecialchars($analysis['filename']) ?></td>
                                        <td data-label="Findings">
                                            <span class="td-detections"><?= $analysis['result']['total_detections'] ?? 0 ?></span> detections
                                        </td>
                                        <td data-label="Severity Status">
                                            <?php $det = $analysis['result']['total_detections'] ?? 0; ?>
                                            <?php if ($det > 5): ?>
                                                <span class="status-badge status-major"><i class="fas fa-exclamation-triangle"></i> Major Damage</span>
                                            <?php elseif ($det > 0): ?>
                                                <span class="status-badge status-moderate"><i class="fas fa-info-circle"></i> Moderate</span>
                                            <?php else: ?>
                                                <span class="status-badge status-clear"><i class="fas fa-check"></i> Clear</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-right" data-label="Actions">
                                            <div class="action-buttons">
                                                <a href="result.php?id=<?= $analysis['id'] ?>" class="action-link"><i class="fas fa-file-alt"></i> Report</a>
                                                <form method="POST" action="dashboard.php" class="form-inline" onsubmit="return confirm('Are you sure you want to permanently delete this report?');">
                                                    <input type="hidden" name="delete_analysis_id" value="<?= $analysis['id'] ?>">
                                                    <button type="submit" class="btn-delete" title="Delete">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="empty-state">
                                        <div class="empty-icon">
                                            <i class="fas fa-folder-open"></i>
                                        </div>
                                        <h3 class="empty-title">No History Found</h3>
                                        <p class="empty-text">Your analyzed vehicle photos will appear here.</p>
                                        <a href="analyze.php" class="submit-btn empty-btn">Start First Analysis</a>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Controls -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination-wrapper">
                    <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>" class="submit-btn btn-page"><i class="fas fa-chevron-left"></i> Prev</a>
                    <?php endif; ?>
                    
                    <span class="page-info">
                        Page <?= $page ?> of <?= $total_pages ?>
                    </span>
                    
                    <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?>" class="submit-btn btn-page">Next <i class="fas fa-chevron-right"></i></a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

            </div>
        </main>
    </div>
    <script src="js/nav.js"></script>

</body>
</html>
