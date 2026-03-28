<?php
require_once 'config.php';
require_login();

if (!isset($_GET['id'])) {
    header("Location: dashboard.php");
    exit;
}

$analysis_id = (int) $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM analyses WHERE id = ? AND user_id = ?");
$stmt->execute([$analysis_id, $_SESSION['user_id']]);
$analysis = $stmt->fetch();

if (!$analysis) {
    set_flash_message('danger', 'Analysis not found or permission denied.');
    header("Location: dashboard.php");
    exit;
}

$result   = json_decode($analysis['result_json'], true);
$filename = htmlspecialchars($analysis['filename']);
$timestamp = $analysis['timestamp'];

// Severity counts
$majorCount = $moderateCount = $minorCount = 0;
if (isset($result['detected_issues'])) {
    foreach ($result['detected_issues'] as $issue) {
        if ($issue['severity'] === 'major')        $majorCount++;
        elseif ($issue['severity'] === 'moderate') $moderateCount++;
        elseif ($issue['severity'] === 'minor')    $minorCount++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AutoDamg | Analysis Report</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/result.css">
</head>
<body>

    <!-- Navbar -->
    <header class="navbar">
        <div class="container header-content">
            <a href="home.php" class="nav-logo">
                <span class="logo-icon"><i class="fas fa-car-crash"></i></span> AutoDamg
            </a>
            <div class="mobile-menu-btn" onclick="toggleMobileMenu()">
                <span></span><span></span><span></span>
            </div>
            <nav>
                <ul class="nav-links" id="navLinks">
                    <li><a href="dashboard.php"><i class="fas fa-th-large"></i> Dashboard</a></li>
                    <li><a href="analytics.php"><i class="fas fa-chart-line"></i> Analytics</a></li>
                    <li><a href="index.php"><i class="fas fa-plus"></i> New Analysis</a></li>
                    <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                    <li><a href="logout.php" class="nav-cta"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <div class="result-container">

        <!-- ── Report Header ───────────────────────────────────────────────── -->
        <div class="result-header">
            <div class="header-left">
                <h1>🔍 Damage Analysis Report</h1>
                <div class="header-meta">
                    <div class="meta-item">
                        <span class="meta-label">File</span>
                        <span class="meta-val"><?= $filename ?></span>
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
        </div>

        <?php if (!empty($result['is_undamaged'])): ?>

        <!-- ── No Damage State ─────────────────────────────────────────────── -->
        <div class="no-damage">
            <div class="no-damage-icon"><i class="fas fa-check"></i></div>
            <h2 class="no-damage-title">No Damage Detected</h2>
            <p class="no-damage-desc">
                The AI analysis found no signs of damage on this vehicle. It appears to be in excellent condition.
            </p>
            <div class="action-group">
                <a href="index.php" class="btn-primary"><i class="fas fa-plus"></i> New Analysis</a>
                <a href="dashboard.php" class="btn-outline"><i class="fas fa-th-large"></i> Dashboard</a>
            </div>
        </div>

        <?php else: ?>

        <!-- ── Split Layout: Image + Summary ──────────────────────────────── -->
        <div class="result-split-layout">

            <!-- Left: Annotated Image -->
            <div class="card result-image-card">
                <h2 class="section-title">
                    <i class="fas fa-image"></i> AI Inspection View
                </h2>

                <?php if (isset($result['original_image'])): ?>
                <div class="comparison-container" id="comparison-container">
                    <img id="original-img-pdf"      src="<?= $result['original_image'] ?>"   class="comparison-base"     alt="Original Vehicle">
                    <img id="annotated-img-compare" src="<?= $result['annotated_image'] ?>"  class="comparison-annotated" alt="Annotated Vehicle">
                    <div id="compare-slider" class="compare-slider">
                        <div class="slider-pill" id="slider-pill">
                            <i class="fas fa-arrows-alt-h"></i>
                        </div>
                    </div>
                </div>
                <div class="comparison-labels">
                    <span><i class="fas fa-camera"></i> Original</span>
                    <span>AI Annotated <i class="fas fa-robot"></i></span>
                </div>
                <?php else: ?>
                <img class="annotated-img" id="annotated-img" src="<?= $result['annotated_image'] ?>" alt="Annotated Vehicle">
                <?php endif; ?>
            </div>

            <!-- Right: Severity Summary -->
            <div class="summary-card">
                <div class="summary-card-header">
                    <i class="fas fa-chart-bar"></i> Damage Severity Summary
                </div>

                <div class="severity-row">
                    <div class="sev-block">
                        <div class="sev-num major"><?= $majorCount ?></div>
                        <div class="sev-label">🔴 Major</div>
                    </div>
                    <div class="sev-divider"></div>
                    <div class="sev-block">
                        <div class="sev-num moderate"><?= $moderateCount ?></div>
                        <div class="sev-label">🟠 Moderate</div>
                    </div>
                    <div class="sev-divider"></div>
                    <div class="sev-block">
                        <div class="sev-num minor"><?= $minorCount ?></div>
                        <div class="sev-label">🟢 Minor</div>
                    </div>
                </div>

                <div class="breakdown-box">
                    <h4 class="breakdown-title">Damage Breakdown</h4>
                    <?php if (isset($result['detected_issues'])): ?>
                    <div class="breakdown-scroll custom-scroll">
                        <?php foreach ($result['detected_issues'] as $issue): ?>
                        <div class="breakdown-row">
                            <div class="breakdown-left">
                                <span class="status-badge status-<?= $issue['severity'] ?>">
                                    <?= htmlspecialchars($issue['severity']) ?>
                                </span>
                                <span class="breakdown-name"><?= htmlspecialchars($issue['class']) ?></span>
                            </div>
                            <span class="breakdown-cost">$<?= $issue['cost_min'] ?>–$<?= $issue['cost_max'] ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ── Cost Estimate Banner ───────────────────────────────────────── -->
        <div class="cost-banner">
            <div>
                <div class="cost-banner-label">💰 Estimated Repair Cost</div>
                <div class="cost-range">
                    $<span><?= $result['cost_min'] ?? 0 ?></span> – $<span><?= $result['cost_max'] ?? 0 ?></span>
                </div>
                <div class="cost-note">
                    Industry-based estimate for <?= $result['total_detections'] ?> detected issue<?= $result['total_detections'] != 1 ? 's' : '' ?>.
                    Actual costs may vary by region and workshop.
                </div>
            </div>
            <div class="cost-actions">
                <a href="index.php"   class="btn-primary"><i class="fas fa-plus"></i> New Analysis</a>
                <a href="dashboard.php" class="btn-outline"><i class="fas fa-th-large"></i> Dashboard</a>
            </div>
        </div>

        <!-- ── Individual Damage Cards ────────────────────────────────────── -->
        <h2 class="section-heading">
            Detailed Findings
            <span class="section-sub">(<?= $result['total_detections'] ?> issues found)</span>
        </h2>

        <div class="damage-cards-grid">
            <?php if (isset($result['detected_issues'])): ?>
            <?php foreach ($result['detected_issues'] as $issue):
                $conf_pct = round($issue['confidence'] * 100, 1);
            ?>
            <div class="damage-card">
                <div class="damage-card-top">
                    <div class="damage-card-sev-icon icon-<?= $issue['severity'] ?>">
                        <?php if ($issue['severity'] === 'major'): ?>
                            <i class="fas fa-exclamation-triangle"></i>
                        <?php elseif ($issue['severity'] === 'moderate'): ?>
                            <i class="fas fa-exclamation-circle"></i>
                        <?php else: ?>
                            <i class="fas fa-info-circle"></i>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="damage-card-title">
                            <?= ucwords(str_replace('-', ' ', htmlspecialchars($issue['class']))) ?>
                        </div>
                        <div class="damage-card-class">
                            <span class="status-badge status-<?= $issue['severity'] ?>">
                                <?= htmlspecialchars($issue['severity']) ?> damage
                            </span>
                        </div>
                        <div class="confidence-bar" data-conf="<?= $conf_pct ?>">
                            <div class="confidence-fill"></div>
                        </div>
                        <div class="confidence-label">Confidence: <strong><?= $conf_pct ?>%</strong></div>
                    </div>
                </div>
                <div class="damage-card-body">
                    <div class="detail-item">
                        <div class="detail-label">Repair Cost</div>
                        <div class="cost-pill"><i class="fas fa-dollar-sign"></i>$<?= $issue['cost_min'] ?> – $<?= $issue['cost_max'] ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Detection Zone</div>
                        <div class="detail-val">[<?= implode(', ', $issue['bbox']) ?>]</div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php endif; ?>

        <!-- ── Bottom Actions ─────────────────────────────────────────────── -->
        <div id="bottom-actions" class="bottom-actions">
            <button onclick="downloadPDF()" class="btn-primary">
                <i class="fas fa-file-pdf"></i> Download PDF Report
            </button>
            <a href="index.php"     class="btn-outline"><i class="fas fa-plus"></i> Start New Analysis</a>
            <a href="dashboard.php" class="btn-outline"><i class="fas fa-th-large"></i> Dashboard</a>
        </div>

    </div><!-- /.result-container -->

    <!-- ── Hidden PDF Layout ──────────────────────────────────────────────── -->
    <div id="pdf-report-content"
         class="pdf-hidden"
         data-filename="<?= $filename ?>">
        <div class="pdf-header">
            <h1>AutoDamg Inspection Report</h1>
            <p>ID: <?= $filename ?> | Date: <?= date('Y-m-d') ?></p>
        </div>

        <div class="pdf-image-wrap">
            <img src="<?= $result['annotated_image'] ?>" alt="Vehicle AI Analysis" class="pdf-image">
        </div>

        <div>
            <h2 class="pdf-section-title">Detected Damage Details</h2>
            <table class="pdf-table">
                <thead>
                    <tr>
                        <th>Damage Type</th>
                        <th>Severity</th>
                        <th style="text-align:right;">Cost Estimate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (isset($result['detected_issues'])): ?>
                    <?php foreach ($result['detected_issues'] as $issue): ?>
                    <tr>
                        <td><?= htmlspecialchars($issue['class']) ?></td>
                        <td class="pdf-sev-<?= $issue['severity'] ?>"><?= htmlspecialchars($issue['severity']) ?></td>
                        <td style="text-align:right;">$<?= $issue['cost_min'] ?> - $<?= $issue['cost_max'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <tr><td colspan="3" style="text-align:center; color:#64748b;">No damage detected.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="pdf-total">
                Total Estimated Repair Cost:
                <span>$<?= $result['cost_min'] ?? 0 ?> - $<?= $result['cost_max'] ?? 0 ?></span>
            </div>

            <div class="pdf-footer">
                AutoDamg AI Assistant &bull; Estimates are purely AI-driven and should be verified by a certified mechanic.
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script src="js/nav.js"></script>
    <script src="js/result.js"></script>
</body>
</html>
