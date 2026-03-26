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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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
                    <li><a href="analytics.php"><i class="fas fa-chart-line"></i> Analytics</a></li>
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
        <style>
            .result-split-layout {
                display: grid;
                grid-template-columns: 1.15fr 0.85fr;
                gap: 2rem;
                margin-bottom: 2.5rem;
                align-items: start;
            }
            @media (max-width: 992px) {
                .result-split-layout {
                    grid-template-columns: 1fr;
                }
            }
        </style>

        <div class="result-split-layout">
            <!-- Left: Annotated Image -->
            <div class="card" style="padding: 1.5rem; text-align: center; margin: 0; box-shadow: var(--shadow-sm);">
                <h2 style="font-size: 1.25rem; font-weight: 800; color: var(--primary-color); display: flex; align-items: center; justify-content: center; gap: 0.75rem; margin-bottom: 1.5rem;">
                    <i class="fas fa-image" style="color: var(--secondary-color);"></i> AI Inspection View
                </h2>
                
                <?php if (isset($result['original_image'])): ?>
                <div class="comparison-container" id="comparison-container" style="position:relative; width: 100%; border-radius:1rem; overflow:hidden; user-select:none; touch-action:none; background:#f1f5f9; cursor:crosshair; box-shadow: inset 0 2px 4px rgba(0,0,0,0.05);">
                    <!-- Original Image -->
                    <img id="original-img-pdf" src="<?= $result['original_image'] ?>" style="width:100%; display:block; object-fit:contain;" alt="Original Vehicle">
                    
                    <!-- Annotated Image (Clipped) -->
                    <img src="<?= $result['annotated_image'] ?>" id="annotated-img-compare" style="position:absolute; top:0; left:0; width:100%; height:100%; object-fit:cover; clip-path: polygon(50% 0, 100% 0, 100% 100%, 50% 100%); pointer-events:none;" alt="Annotated Vehicle">
                    
                    <!-- Slider Handle -->
                    <div id="compare-slider" style="position:absolute; top:0; bottom:0; left:50%; width:4px; background:white; cursor:ew-resize; transform: translateX(-50%); z-index:10; pointer-events:none;">
                        <div style="position:absolute; top:50%; left:50%; transform:translate(-50%, -50%); width:36px; height:36px; background:white; border-radius:50%; display:flex; align-items:center; justify-content:center; box-shadow:0 2px 10px rgba(0,0,0,0.25); color:var(--primary-color); font-size:14px; pointer-events:auto;" id="slider-pill">
                            <i class="fas fa-arrows-alt-h"></i>
                        </div>
                    </div>
                </div>
                <div style="display:flex; justify-content:space-between; margin: 0.75rem auto 0 auto; font-size:0.85rem; color:var(--text-secondary); font-weight:700; text-transform:uppercase; letter-spacing:0.05em;">
                    <span><i class="fas fa-camera" style="margin-right:4px;"></i> Original</span>
                    <span>AI Annotated <i class="fas fa-robot" style="margin-left:4px;"></i></span>
                </div>
                <?php else: ?>
                <img class="annotated-img" id="annotated-img" src="<?= $result['annotated_image'] ?>" alt="Annotated Vehicle" style="max-width: 100%; margin: 0 auto; border-radius: 1rem; box-shadow: inset 0 2px 4px rgba(0,0,0,0.05);">
                <?php endif; ?>
            </div>

            <!-- Right: Summary section -->
            <div class="summary-card" style="margin: 0; box-shadow: var(--shadow-sm); height: 100%;">
                <div class="summary-card-header" style="font-size: 1.25rem; font-weight: 800; margin-bottom: 1.5rem;"><i class="fas fa-chart-bar" style="color: var(--secondary-color); margin-right: 0.5rem;"></i> Damage Severity Summary</div>
                <div class="severity-row" style="display: flex; justify-content: space-around; padding: 1.25rem; background: #f8fafc; border-radius: 1rem; border: 1px solid var(--border-color); margin-bottom: 1.5rem;">
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
                    <div class="sev-block" style="text-align: center;">
                        <div class="sev-num major" style="font-size: 1.75rem; font-weight: 800; color: #ef4444;"><?= $majorCount ?></div>
                        <div class="sev-label" style="font-weight: 700; color: var(--text-secondary); text-transform: uppercase; font-size: 0.75rem;">🔴 Major</div>
                    </div>
                    <div style="width:1px; background:var(--border-color);"></div>
                    <div class="sev-block" style="text-align: center;">
                        <div class="sev-num moderate" style="font-size: 1.75rem; font-weight: 800; color: #f59e0b;"><?= $moderateCount ?></div>
                        <div class="sev-label" style="font-weight: 700; color: var(--text-secondary); text-transform: uppercase; font-size: 0.75rem;">🟠 Moderate</div>
                    </div>
                    <div style="width:1px; background:var(--border-color);"></div>
                    <div class="sev-block" style="text-align: center;">
                        <div class="sev-num minor" style="font-size: 1.75rem; font-weight: 800; color: #10b981;"><?= $minorCount ?></div>
                        <div class="sev-label" style="font-weight: 700; color: var(--text-secondary); text-transform: uppercase; font-size: 0.75rem;">🟢 Minor</div>
                    </div>
                </div>

                <div style="padding:1.5rem; background: white; border-radius: 1rem; border: 1px solid var(--border-color);">
                    <h4 style="font-size:0.875rem; color:var(--text-secondary); text-transform:uppercase; letter-spacing:0.06em; margin-bottom:1.25rem;">Damage Breakdown</h4>
                    <?php if (isset($result['detected_issues'])): ?>
                    <div style="max-height: 250px; overflow-y: auto; padding-right: 0.5rem;" class="custom-scroll">
                        <style>
                            .custom-scroll::-webkit-scrollbar { width: 6px; }
                            .custom-scroll::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 4px; }
                            .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
                        </style>
                        <?php foreach ($result['detected_issues'] as $issue): ?>
                        <div style="display:flex; justify-content:space-between; align-items:center; padding:0.875rem 0; border-bottom: 1px solid #f1f5f9;">
                            <div style="display:flex; align-items:center; gap:0.75rem;">
                                <span class="status-badge status-<?= $issue['severity'] == 'clear' ? 'clear' : $issue['severity'] ?>" style="text-transform:capitalize; padding: 0.3rem 0.6rem; font-size: 0.75rem;">
                                    <?= htmlspecialchars($issue['severity']) ?>
                                </span>
                                <span style="font-weight:700; font-size:0.95rem; color:var(--primary-color);"><?= htmlspecialchars($issue['class']) ?></span>
                            </div>
                            <span style="font-weight:800; color:var(--secondary-color); font-size: 1rem;">$<?= $issue['cost_min'] ?>–$<?= $issue['cost_max'] ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Cost Estimate Banner (Below the split layout) -->
        <div class="cost-banner" style="margin-bottom: 3rem;">
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
        <div id="bottom-actions" style="padding:3rem 0; display:flex; gap:1rem; flex-wrap:wrap; justify-content:center;">
            <button onclick="downloadPDF()" class="btn-primary" style="background:var(--primary-color); border-color:var(--primary-color); cursor:pointer;"><i class="fas fa-file-pdf"></i> Download PDF Report</button>
            <a href="index.php" class="btn-outline"><i class="fas fa-plus"></i> Start New Analysis</a>
            <?php if (is_logged_in()): ?>
            <a href="dashboard.php" class="btn-outline"><i class="fas fa-th-large"></i> Dashboard</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Hidden PDF Layout (Only used for exporting) -->
    <div id="pdf-report-content" style="display: none; padding: 40px; background: white; color: black; font-family: sans-serif; width: 800px; margin: 0 auto;">
        <div style="text-align: center; margin-bottom: 30px; border-bottom: 2px solid #e2e8f0; padding-bottom: 20px;">
            <h1 style="color: #0f172a; margin: 0; font-size: 28px;">AutoDamg Inspection Report</h1>
            <p style="color: #64748b; margin-top: 10px; font-size: 14px;">ID: <?= htmlspecialchars($filename) ?> | Date: <?= date('Y-m-d') ?></p>
        </div>
        
        <div style="margin-bottom: 30px; text-align: center;">
            <img src="<?= $result['annotated_image'] ?>" style="max-width: 100%; max-height: 400px; border-radius: 8px; border: 1px solid #cbd5e1; object-fit: contain;" alt="Vehicle AI Analysis">
        </div>

        <div>
            <h2 style="color: #0f172a; font-size: 18px; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px; margin-bottom: 15px;">Detected Damage Details</h2>
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 14px;">
                <thead>
                    <tr style="background-color: #f8fafc;">
                        <th style="padding: 12px; border-bottom: 2px solid #cbd5e1; color: #334155;">Damage Type</th>
                        <th style="padding: 12px; border-bottom: 2px solid #cbd5e1; color: #334155;">Severity</th>
                        <th style="padding: 12px; border-bottom: 2px solid #cbd5e1; color: #334155; text-align: right;">Cost Estimate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (isset($result['detected_issues'])): ?>
                    <?php foreach ($result['detected_issues'] as $issue): ?>
                    <tr>
                        <td style="padding: 12px; border-bottom: 1px solid #e2e8f0; font-weight: bold; color: #0f172a;"><?= htmlspecialchars($issue['class']) ?></td>
                        <td style="padding: 12px; border-bottom: 1px solid #e2e8f0; text-transform: capitalize; color: <?= $issue['severity']=='major'?'#dc2626':($issue['severity']=='moderate'?'#d97706':'#16a34a') ?>;"><?= htmlspecialchars($issue['severity']) ?></td>
                        <td style="padding: 12px; border-bottom: 1px solid #e2e8f0; color: #2563eb; font-weight: bold; text-align: right;">$<?= $issue['cost_min'] ?> - $<?= $issue['cost_max'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <tr><td colspan="3" style="padding: 12px; text-align: center; color: #64748b;">No damage detected.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <div style="margin-top: 30px; text-align: right; font-size: 16px; font-weight: bold; color: #0f172a; border-top: 2px solid #e2e8f0; padding-top: 15px;">
                Total Estimated Repair Cost: <span style="color: #2563eb;">$<?= $result['cost_min'] ?? 0 ?> - $<?= $result['cost_max'] ?? 0 ?></span>
            </div>
            
            <div style="margin-top: 40px; font-size: 11px; color: #94a3b8; text-align: center; border-top: 1px solid #e2e8f0; padding-top: 15px;">
                AutoDamg AI Assistant &bull; Estimates are purely AI-driven and should be verified by a certified mechanic.
            </div>
        </div>
    </div>

    <!-- html2pdf for PDF Generation -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>
        function downloadPDF() {
            const reportContent = document.getElementById('pdf-report-content');
            
            // Clone the node
            const clone = reportContent.cloneNode(true);
            clone.style.display = 'block';
            
            // Mount it invisibly to the viewport so html2canvas renders it fully
            const tempDiv = document.createElement('div');
            tempDiv.style.position = 'fixed';
            tempDiv.style.top = '0';
            tempDiv.style.left = '0';
            tempDiv.style.width = '800px';
            tempDiv.style.zIndex = '-9999';
            tempDiv.style.opacity = '0';
            tempDiv.style.pointerEvents = 'none';
            tempDiv.appendChild(clone);
            document.body.appendChild(tempDiv);
            
            const opt = {
                margin:       10,
                filename:     'AutoDamg_Report_<?= htmlspecialchars($filename) ?>.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, useCORS: true, logging: false },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };

            // Allow the browser time to paint the cloned image and styles
            setTimeout(() => {
                html2pdf().set(opt).from(clone).save().then(() => {
                    // Cleanup
                    document.body.removeChild(tempDiv);
                });
            }, 300);
        }
    </script>

    <!-- Comparison Slider JS -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const container = document.getElementById('comparison-container');
            const slider = document.getElementById('compare-slider');
            const annotatedImg = document.getElementById('annotated-img-compare');
            const sliderPill = document.getElementById('slider-pill');

            if (container && slider && annotatedImg) {
                let isDown = false;

                const startDrag = (e) => { isDown = true; };
                const stopDrag = () => { isDown = false; };
                
                sliderPill.addEventListener('mousedown', startDrag);
                sliderPill.addEventListener('touchstart', startDrag, {passive: true});
                
                // Allow clicking anywhere on the image to jump the slider
                container.addEventListener('mousedown', startDrag);
                container.addEventListener('touchstart', startDrag, {passive: true});

                window.addEventListener('mouseup', stopDrag);
                window.addEventListener('touchend', stopDrag);

                const doDrag = (e) => {
                    if (!isDown) return;
                    e.preventDefault(); // Prevent scrolling while dragging
                    
                    const rect = container.getBoundingClientRect();
                    let clientX = e.clientX;
                    
                    if (e.type === 'touchmove') {
                        clientX = e.touches[0].clientX;
                    }

                    let x = clientX - rect.left;
                    let percent = (x / rect.width) * 100;
                    
                    if (percent < 0) percent = 0;
                    if (percent > 100) percent = 100;
                    
                    // Move slider line
                    slider.style.left = percent + '%';
                    
                    // Update clip path (shows Original on left, Annotated on right)
                    annotatedImg.style.clipPath = `polygon(${percent}% 0, 100% 0, 100% 100%, ${percent}% 100%)`;
                };

                window.addEventListener('mousemove', doDrag);
                window.addEventListener('touchmove', doDrag, {passive: false});
            }
        });
    </script>

    <script src="js/nav.js"></script>
    <script src="js/result.js"></script>
</body>
</html>
