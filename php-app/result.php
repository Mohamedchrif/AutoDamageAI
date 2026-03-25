<?php
require_once 'config.php';
require_login();

if (!isset($_GET['id'])) {
    header("Location: dashboard.php");
    exit;
}

$analysis_id = $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM analyses WHERE id = ? AND user_id = ?");
$stmt->execute([$analysis_id, $_SESSION['user_id']]);
$analysis = $stmt->fetch();

if (!$analysis) {
    die("Analysis not found or permission denied.");
}

$result = json_decode($analysis['result_json'], true);
$filename = $analysis['filename'];
$timestamp = $analysis['timestamp'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AutoDamg | Analysis Report</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/result.css">
</head>
<body>

    <!-- Navbar -->
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
                    <li><a href="dashboard.php"><i class="fas fa-th-large"></i> Dashboard</a></li>
                    <li><a href="index.php"><i class="fas fa-plus"></i> New Analysis</a></li>
                    <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                    <li><a href="logout.php" class="nav-cta" style="color: white !important;"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <div class="result-container">

        <!-- Header Banner -->
        <div class="result-header">
            <div class="header-left">
                <h1>🔍 Damage Analysis Report</h1>
                <div class="header-meta">
                    <div class="meta-item">
                        <span class="meta-label">File</span>
                        <span class="meta-val"><?= htmlspecialchars($filename) ?></span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Date</span>
                        <span class="meta-val"><?= date('F d, Y \a\t H:i', strtotime($timestamp)) ?></span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Total Findings</span>
                        <span class="meta-val"><?= $result['total_detections'] ?? 0 ?> Detections</span>
                    </div>
                    <?php if (isset($result['original_dimensions'])): ?>
                    <div class="meta-item">
                        <span class="meta-label">Image Resolution</span>
                        <span class="meta-val"><?= $result['original_dimensions']['width'] ?>×<?= $result['original_dimensions']['height'] ?>px</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <img src="assets/car_icon.svg" alt="" style="height:80px; opacity:0.1;" onerror="this.style.display='none'">
        </div>

        <?php if (!empty($result['is_undamaged'])): ?>
        <!-- No Damage State -->
        <div class="no-damage">
            <div class="no-damage-icon"><i class="fas fa-check"></i></div>
            <h2 style="color: var(--success-color); font-size:2rem; margin-bottom:1rem;">No Damage Detected</h2>
            <p style="font-size:1.1rem; color:var(--text-secondary); max-width:480px; margin:0 auto 2.5rem;">
                The AI analysis found no signs of damage on this vehicle. It appears to be in excellent condition.
            </p>
            <div style="display:flex; gap:1rem; justify-content:center; flex-wrap:wrap;">
                <a href="index.php" class="btn-primary"><i class="fas fa-plus"></i> New Analysis</a>
                <?php if (is_logged_in()): ?>
                <a href="dashboard.php" class="btn-outline"><i class="fas fa-th-large"></i> Dashboard</a>
                <?php endif; ?>
            </div>
        </div>

        <?php else: ?>

        <!-- Cost Estimate Banner -->
        <div class="cost-banner">
            <div>
                <div class="cost-banner-label">💰 Estimated Repair Cost</div>
                <div class="cost-range">
                    $<span><?= $result['cost_min'] ?? 0 ?></span> – $<span><?= $result['cost_max'] ?? 0 ?></span>
                </div>
                <div class="cost-note">Industry-based estimate for <?= $result['total_detections'] ?> detected issue<?= $result['total_detections'] != 1 ? 's' : '' ?>. Actual costs may vary by region and workshop.</div>
            </div>
            <div class="cost-actions">
                <a href="index.php" class="btn-primary"><i class="fas fa-plus"></i> New Analysis</a>
                <?php if (is_logged_in()): ?>
                <a href="dashboard.php" class="btn-outline"><i class="fas fa-th-large"></i> Dashboard</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Annotated Image + Summary -->
        <div class="grid-two-col">
            <div class="image-card">
                <div class="image-card-header"><i class="fas fa-image"></i> Annotated Inspection View</div>
                <img class="annotated-img" id="annotated-img" src="<?= $result['annotated_image'] ?>" alt="Annotated Vehicle">
            </div>

            <div class="summary-card">
                <div class="summary-card-header"><i class="fas fa-chart-bar"></i> Damage Severity Summary</div>
                <div class="severity-row">
                    <?php 
                    $majorCount = $moderateCount = $minorCount = 0;
                    if (isset($result['detected_issues'])) {
                        foreach ($result['detected_issues'] as $issue) {
                            if ($issue['severity'] == 'major') $majorCount++;
                            elseif ($issue['severity'] == 'moderate') $moderateCount++;
                            elseif ($issue['severity'] == 'minor') $minorCount++;
                        }
                    }
                    ?>
                    <div class="sev-block">
                        <div class="sev-num major"><?= $majorCount ?></div>
                        <div class="sev-label">🔴 Major</div>
                    </div>
                    <div style="width:1px; background:var(--border-color); height:60px;"></div>
                    <div class="sev-block">
                        <div class="sev-num moderate"><?= $moderateCount ?></div>
                        <div class="sev-label">🟠 Moderate</div>
                    </div>
                    <div style="width:1px; background:var(--border-color); height:60px;"></div>
                    <div class="sev-block">
                        <div class="sev-num minor"><?= $minorCount ?></div>
                        <div class="sev-label">🟢 Minor</div>
                    </div>
                </div>

                <div style="padding:1.5rem 2rem; border-top:1px solid var(--border-color); flex:1;">
                    <h4 style="font-size:0.875rem; color:var(--text-secondary); text-transform:uppercase; letter-spacing:0.06em; margin-bottom:1.5rem;">Damage Breakdown</h4>
                    <?php if (isset($result['detected_issues'])): ?>
                    <?php foreach ($result['detected_issues'] as $issue): ?>
                    <div style="display:flex; justify-content:space-between; align-items:center; padding:0.875rem 0; border-bottom: 1px solid var(--border-color);">
                        <div style="display:flex; align-items:center; gap:0.75rem;">
                            <span class="status-badge status-<?= $issue['severity'] == 'clear' ? 'clear' : $issue['severity'] ?>" style="text-transform:capitalize;">
                                <?= htmlspecialchars($issue['severity']) ?>
                            </span>
                            <span style="font-weight:600; font-size:0.9375rem; color:var(--primary-color);"><?= htmlspecialchars($issue['class']) ?></span>
                        </div>
                        <span style="font-weight:700; color:var(--secondary-color);">$<?= $issue['cost_min'] ?>–$<?= $issue['cost_max'] ?></span>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Individual Damage Cards -->
        <h2 style="font-size:1.5rem; font-weight:800; margin-bottom:1.5rem; color:var(--primary-color);">
            Detailed Findings <span style="font-size:1rem; color:var(--text-secondary); font-weight:500;">(<?= $result['total_detections'] ?> issues found)</span>
        </h2>
        <div class="damage-cards-grid">
            <?php if (isset($result['detected_issues'])): ?>
            <?php foreach ($result['detected_issues'] as $issue): 
                $conf_pct = round($issue['confidence'] * 100, 1);
            ?>
            <div class="damage-card">
                <div class="damage-card-top">
                    <div class="damage-card-sev-icon icon-<?= $issue['severity'] ?>">
                        <?php if ($issue['severity'] == 'major'): ?><i class="fas fa-exclamation-triangle"></i>
                        <?php elseif ($issue['severity'] == 'moderate'): ?><i class="fas fa-exclamation-circle"></i>
                        <?php else: ?><i class="fas fa-info-circle"></i><?php endif; ?>
                    </div>
                    <div>
                        <div class="damage-card-title"><?= ucwords(str_replace('-', ' ', htmlspecialchars($issue['class']))) ?></div>
                        <div class="damage-card-class" style="margin-top:0.25rem;">
                            <span class="status-badge status-<?= $issue['severity'] == 'clear' ? 'clear' : $issue['severity'] ?>" style="text-transform:capitalize; font-size:0.7rem;">
                                <?= htmlspecialchars($issue['severity']) ?> damage
                            </span>
                        </div>
                        <div class="confidence-bar" style="width:180px; margin-top:0.75rem;" data-conf="<?= $conf_pct ?>">
                            <div class="confidence-fill"></div>
                        </div>
                        <div style="font-size:0.75rem; color:var(--text-secondary); margin-top:0.4rem;">Confidence: <strong><?= $conf_pct ?>%</strong></div>
                    </div>
                </div>
                <div class="damage-card-body">
                    <div class="detail-item">
                        <div class="detail-label">Repair Cost</div>
                        <div class="cost-pill"><i class="fas fa-dollar-sign"></i>$<?= $issue['cost_min'] ?> – $<?= $issue['cost_max'] ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Detection Zone</div>
                        <div class="detail-val" style="font-size:0.8rem; color:var(--text-secondary);">[<?= implode(', ', $issue['bbox']) ?>]</div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php endif; ?>

        <!-- Bottom Actions -->
        <div style="padding:3rem 0; display:flex; gap:1rem; flex-wrap:wrap; justify-content:center;">
            <a href="index.php" class="btn-primary"><i class="fas fa-plus"></i> Start New Analysis</a>
            <?php if (is_logged_in()): ?>
            <a href="dashboard.php" class="btn-outline"><i class="fas fa-th-large"></i> Back to Dashboard</a>
            <?php endif; ?>
        </div>
    </div>

<script src="js/nav.js"></script>
    <script src="js/result.js"></script>
</body>
</html>
