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

$result = json_decode($analysis['result_json'], true) ?: [];
$filename = htmlspecialchars($analysis['filename']);
$timestamp = $analysis['timestamp'];

$annotatedSrc = trim((string)($analysis['annotated_image'] ?? ''));
if ($annotatedSrc === '' && !empty($result['annotated_image'])) {
    $annotatedSrc = (string) $result['annotated_image'];
}
$originalSrc = trim((string)($result['original_image'] ?? ''));

$imgUrl = static function (string $src): string {
    return htmlspecialchars($src, ENT_QUOTES, 'UTF-8');
};

// Severity counts
$majorCount = $moderateCount = $minorCount = 0;
if (isset($result['detected_issues'])) {
    foreach ($result['detected_issues'] as $issue) {
        if ($issue['severity'] === 'major')        $majorCount++;
        elseif ($issue['severity'] === 'moderate') $moderateCount++;
        elseif ($issue['severity'] === 'minor')    $minorCount++;
    }
}

// Absolute image URL for PDF export (jsPDF + fetch — avoids blank html2canvas output)
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$appBaseUrl = rtrim($scheme . '://' . $host . (($scriptDir === '/' || $scriptDir === '') ? '' : $scriptDir), '/') . '/';
$annotatedForPdf = $annotatedSrc;
if ($annotatedSrc !== '' && strpos($annotatedSrc, 'data:') !== 0 && !preg_match('#^https?://#i', $annotatedSrc)) {
    $annotatedForPdf = $appBaseUrl . ltrim(str_replace('\\', '/', $annotatedSrc), '/');
}

$pdfPayload = [
    'filename' => $analysis['filename'],
    'date'     => date('Y-m-d', strtotime($timestamp)),
    'image'    => $annotatedForPdf,
    'costMin'  => (float)($result['cost_min'] ?? 0),
    'costMax'  => (float)($result['cost_max'] ?? 0),
    'issues'   => array_values($result['detected_issues'] ?? []),
];
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
    <?php include 'navbar.php'; ?>

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
                <a href="analyze.php" class="btn-primary"><i class="fas fa-plus"></i> New Analysis</a>
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

                <?php if ($originalSrc !== ''): ?>
                <div class="comparison-container" id="comparison-container">
                    <img id="original-img-pdf"      src="<?= $imgUrl($originalSrc) ?>"   class="comparison-base"     alt="Original Vehicle">
                    <img id="annotated-img-compare" src="<?= $imgUrl($annotatedSrc) ?>"  class="comparison-annotated" alt="Annotated Vehicle">
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
                <img class="annotated-img" id="annotated-img" src="<?= $imgUrl($annotatedSrc) ?>" alt="Annotated Vehicle">
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
                <a href="analyze.php"   class="btn-primary"><i class="fas fa-plus"></i> New Analysis</a>
                <a href="dashboard.php" class="btn-outline"><i class="fas fa-th-large"></i> Dashboard</a>
            </div>
        </div>

        <?php endif; ?>

        <!-- ── Bottom Actions ─────────────────────────────────────────────── -->
        <div id="bottom-actions" class="bottom-actions">
            <button onclick="downloadPDF()" class="btn-primary">
                <i class="fas fa-file-pdf"></i> Download PDF Report
            </button>
            <a href="analyze.php"     class="btn-outline"><i class="fas fa-plus"></i> Start New Analysis</a>
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
            <img src="<?= $imgUrl($annotatedSrc) ?>" alt="Vehicle AI Analysis" class="pdf-image">
        </div>

        <div>
            <h2 class="pdf-section-title">Detected Damage Details</h2>
            <table class="pdf-table">
                <thead>
                    <tr>
                        <th>Damage Type</th>
                        <th>Severity</th>
                        <th class="pdf-text-right">Cost Estimate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (isset($result['detected_issues'])): ?>
                    <?php foreach ($result['detected_issues'] as $issue): ?>
                    <tr>
                        <td><?= htmlspecialchars($issue['class']) ?></td>
                        <td class="pdf-sev-<?= $issue['severity'] ?>"><?= htmlspecialchars($issue['severity']) ?></td>
                        <td class="pdf-text-right">$<?= $issue['cost_min'] ?> - $<?= $issue['cost_max'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <tr><td colspan="3" class="pdf-no-damage-cell">No damage detected.</td></tr>
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

    <script type="application/json" id="report-pdf-data"><?= json_encode($pdfPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="js/nav.js"></script>
    <script src="js/result.js"></script>
</body>
</html>
