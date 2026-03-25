<?php
require_once 'config.php';
require_login();
$user = get_current_user_data($pdo);

// Fetch recent analyses for the current user
$stmt = $pdo->prepare("SELECT * FROM analyses WHERE user_id = ? ORDER BY timestamp DESC LIMIT 10");
$stmt->execute([$user['id']]);
$analyses = $stmt->fetchAll();

// Process high severity count
$high_count = 0;
foreach ($analyses as &$a) {
    if (!empty($a['result_json'])) {
        $a['result'] = json_decode($a['result_json'], true);
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
    <link rel="stylesheet" href="css/main.css">
</head>
<body>
    <div class="page-wrapper">
        <header class="navbar" style="position: relative;">
            <div class="container header-content" style="width: 100%;">
                <a href="home.php" class="nav-logo" style="color: var(--primary-color);">
                    <span class="logo-icon"><i class="fas fa-car-crash"></i></span> AutoDamg
                </a>
                
                <div class="mobile-menu-btn" onclick="toggleMobileMenu()">
                    <span></span><span></span><span></span>
                </div>

                <nav>
                    <ul class="nav-links" id="navLinks">
                        <li><a href="dashboard.php" class="active"><i class="fas fa-th-large"></i> Dashboard</a></li>
                        <li><a href="index.php"><i class="fas fa-plus"></i> New Analysis</a></li>
                        <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                        <li><a href="logout.php" class="nav-cta" style="color: white !important;"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                    </ul>
                </nav>
            </div>
        </header>

        <main class="main-content container" style="padding-top: 3rem; margin: 0 auto; max-width: 1200px;">
            <header class="page-header" style="margin-bottom: 2.5rem; display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 1.5rem;">
                <div>
                    <h1 style="margin: 0; font-size: 2.25rem; font-weight: 800; color: var(--primary-color);">Welcome Back, <span style="color: var(--secondary-color);"><?= htmlspecialchars($user['username']) ?></span></h1>
                    <p style="color: var(--text-secondary); margin-top: 0.5rem; font-size: 1.05rem;">Review your latest vehicle inspection matches and history.</p>
                </div>
                <a href="index.php" class="submit-btn" style="margin: 0; width: auto; padding: 0.8rem 1.75rem; text-decoration: none; display: flex; align-items: center; gap: 0.6rem; border-radius: 0.75rem;">
                    <i class="fas fa-plus-circle"></i> New Analysis
                </a>
            </header>

            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Inspections</h3>
                    <div class="value"><?= count($analyses) ?></div>
                </div>
                <div class="stat-card">
                    <h3>High Severity</h3>
                    <div class="value" style="color: var(--danger-color);">
                        <?= $high_count ?>
                    </div>
                </div>
                <div class="stat-card">
                    <h3>System Status</h3>
                    <div class="value" style="color: var(--success-color); font-size: 1.5rem;">
                        <i class="fas fa-check-circle"></i> Operational
                    </div>
                </div>
            </div>

            <div class="history-table-card">
                <div style="padding: 1.5rem 2rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; background: white;">
                    <h2 style="font-size: 1.25rem; margin: 0;">Recent Inspection History</h2>
                    <div style="font-size: 0.875rem; color: var(--text-secondary);">Showing last <?= count($analyses) ?> entries</div>
                </div>
                
                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>File Name</th>
                                <th>Findings</th>
                                <th>Severity Status</th>
                                <th style="text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($analyses) > 0): ?>
                                <?php foreach ($analyses as $analysis): ?>
                                    <tr>
                                        <td style="font-weight: 500;" data-label="Date & Time"><?= date('M d, Y • H:i', strtotime($analysis['timestamp'])) ?></td>
                                        <td style="color: var(--text-secondary);" data-label="File Name"><?= htmlspecialchars($analysis['filename']) ?></td>
                                        <td data-label="Findings">
                                            <span style="font-weight: 700; color: var(--primary-color);"><?= $analysis['result']['total_detections'] ?></span> detections
                                        </td>
                                        <td data-label="Severity Status">
                                            <?php if ($analysis['result']['total_detections'] > 5): ?>
                                                <span class="status-badge status-major"><i class="fas fa-exclamation-triangle"></i> Major Damage</span>
                                            <?php elseif ($analysis['result']['total_detections'] > 0): ?>
                                                <span class="status-badge status-moderate"><i class="fas fa-info-circle"></i> Moderate</span>
                                            <?php else: ?>
                                                <span class="status-badge status-clear"><i class="fas fa-check"></i> Clear</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align: right;" data-label="Actions">
                                            <a href="result.php?id=<?= $analysis['id'] ?>" class="action-link"><i class="fas fa-file-alt"></i> Report</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 5rem 2rem;">
                                        <div style="margin-bottom: 1rem; opacity: 0.2;">
                                            <i class="fas fa-folder-open" style="font-size: 4rem;"></i>
                                        </div>
                                        <h3 style="color: var(--text-secondary); margin-bottom: 0.5rem;">No History Found</h3>
                                        <p style="color: var(--text-secondary); font-size: 0.875rem;">Your analyzed vehicle photos will appear here.</p>
                                        <a href="index.php" class="submit-btn" style="width: auto; display: inline-flex; margin-top: 1.5rem; text-decoration: none; padding: 0.8rem 2rem; border-radius: 0.75rem;">Start First Analysis</a>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    <script src="js/nav.js"></script>
</body>
</html>
