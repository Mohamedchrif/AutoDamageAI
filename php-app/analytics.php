<?php
require_once 'config.php';
require_login();
$user = get_current_user_data($pdo);

// Fetch all analyses for the current user
$stmt = $pdo->prepare("SELECT * FROM analyses WHERE user_id = ? ORDER BY timestamp DESC");
$stmt->execute([$user['id']]);
$analyses = $stmt->fetchAll();

// -----------------------------------------
// Data Aggregation Variables
// -----------------------------------------
$total_inspections = count($analyses);
$total_cost_min = 0;
$total_cost_max = 0;
$high_severity_count = 0;
$moderate_severity_count = 0;
$minor_severity_count = 0;
$clear_count = 0;
$damage_types = [];
$timeline_data = [];

// Initialize timeline for last 7 days
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $timeline_data[$date] = 0;
}

foreach ($analyses as $a) {
    // Timeline Tally
    $date = date('Y-m-d', strtotime($a['timestamp']));
    if (isset($timeline_data[$date])) {
        $timeline_data[$date]++;
    }

    if (!empty($a['result_json'])) {
        $result = json_decode($a['result_json'], true);
        if (!$result) continue; // Skip invalid json

        // Costs
        $total_cost_min += $result['cost_min'] ?? 0;
        $total_cost_max += $result['cost_max'] ?? 0;

        // Severities and Damage Types
        if (isset($result['detected_issues']) && is_array($result['detected_issues'])) {
            if (count($result['detected_issues']) === 0) {
                $clear_count++;
            } else {
                $max_severity_for_this_scan = 'clear';
                
                foreach ($result['detected_issues'] as $issue) {
                    // Count specific damage classes (e.g., bumper_dent)
                    $class = ucwords(str_replace('-', ' ', $issue['class']));
                    if (!isset($damage_types[$class])) {
                        $damage_types[$class] = 0;
                    }
                    $damage_types[$class]++;

                    // Find worst severity for this specific car scan
                    if ($issue['severity'] === 'major') {
                        $max_severity_for_this_scan = 'major';
                    } elseif ($issue['severity'] === 'moderate' && $max_severity_for_this_scan !== 'major') {
                        $max_severity_for_this_scan = 'moderate';
                    } elseif ($issue['severity'] === 'minor' && $max_severity_for_this_scan === 'clear') {
                        $max_severity_for_this_scan = 'minor';
                    }
                }

                if ($max_severity_for_this_scan === 'major') $high_severity_count++;
                elseif ($max_severity_for_this_scan === 'moderate') $moderate_severity_count++;
                elseif ($max_severity_for_this_scan === 'minor') $minor_severity_count++;
            }
        } else {
            $clear_count++;
        }
    }
}

// Sort damage types by frequency descending
arsort($damage_types);
$top_damage_types = array_slice($damage_types, 0, 6, true); // take top 6 for chart
$most_common_damage = !empty($top_damage_types) ? array_key_first($top_damage_types) : 'None';

// Calculate Averages
$avg_cost = $total_inspections > 0 ? (($total_cost_min + $total_cost_max) / 2) / $total_inspections : 0;

// Prepare JSON for JS Chart logic
$js_severity_data = json_encode([$high_severity_count, $moderate_severity_count, $minor_severity_count, $clear_count]);
$js_damage_labels = json_encode(array_keys($top_damage_types));
$js_damage_counts = json_encode(array_values($top_damage_types));
$js_timeline_labels = json_encode(array_map(function($d) { return date('M d', strtotime($d)); }, array_keys($timeline_data)));
$js_timeline_counts = json_encode(array_values($timeline_data));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AutoDamg | Analytics Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .analytics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem; margin-bottom: 2.5rem; }
        .chart-card { background: white; border-radius: 1rem; padding: 1.5rem; box-shadow: var(--shadow-sm); border: 1px solid var(--border-color); }
        .chart-card h3 { font-size: 1.1rem; color: var(--primary-color); margin-top: 0; margin-bottom: 1.5rem; font-weight: 800; display: flex; align-items: center; gap: 0.5rem; }
        .chart-container-inner { position: relative; height: 300px; width: 100%; display: flex; justify-content: center; align-items: center; }
        
        .kpi-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .kpi-card { background: white; padding: 1.5rem; border-radius: 1rem; border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); display: flex; align-items: center; gap: 1.25rem; transition: transform 0.2s; }
        .kpi-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }
        .kpi-icon { width: 56px; height: 56px; border-radius: 1rem; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0; }
        .kpi-blue { background: #eff6ff; color: #3b82f6; }
        .kpi-green { background: #f0fdf4; color: #10b981; }
        .kpi-orange { background: #fffbeb; color: #f59e0b; }
        
        .kpi-details h4 { margin: 0; color: var(--text-secondary); font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 700; }
        .kpi-value { margin: 0.4rem 0 0 0; font-size: 1.75rem; font-weight: 800; color: var(--primary-color); }
    </style>
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
                        <li><a href="dashboard.php"><i class="fas fa-th-large"></i> Dashboard</a></li>
                        <li><a href="analytics.php" class="active"><i class="fas fa-chart-line"></i> Analytics</a></li>
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
                    <h1 style="margin: 0; font-size: 2.25rem; font-weight: 800; color: var(--primary-color);">Advanced <span style="color: var(--secondary-color);">Analytics</span></h1>
                    <p style="color: var(--text-secondary); margin-top: 0.5rem; font-size: 1.05rem;">Data insights and repair cost estimations across your entire fleet.</p>
                </div>
            </header>

            <!-- KPI Cards -->
            <div class="kpi-row">
                <div class="kpi-card">
                    <div class="kpi-icon kpi-blue"><i class="fas fa-search"></i></div>
                    <div class="kpi-details">
                        <h4>Total Scans</h4>
                        <div class="kpi-value"><?= number_format($total_inspections) ?></div>
                    </div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-icon kpi-green"><i class="fas fa-dollar-sign"></i></div>
                    <div class="kpi-details">
                        <h4>Avg Est. Repair Cost</h4>
                        <div class="kpi-value">$<?= number_format($avg_cost) ?></div>
                    </div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-icon kpi-orange"><i class="fas fa-car-side"></i></div>
                    <div class="kpi-details">
                        <h4>Top Damage Type</h4>
                        <div class="kpi-value" style="font-size: 1.25rem; margin-top: 0.6rem;"><?= htmlspecialchars($most_common_damage) ?></div>
                    </div>
                </div>
            </div>

            <!-- Charts Grid -->
            <div class="analytics-grid">
                <!-- Doughnut Chart: Severity -->
                <div class="chart-card">
                    <h3><i class="fas fa-exclamation-circle" style="color: #ef4444;"></i> Fleet Severity Distribution</h3>
                    <div class="chart-container-inner">
                        <canvas id="severityChart"></canvas>
                        <?php if ($total_inspections == 0): ?>
                        <div style="position: absolute; justify-content: center; align-items: center; color: var(--text-secondary); background: rgba(255,255,255,0.8); z-index: 10; display: flex; width: 100%; height: 100%; font-weight: 600;">No data available</div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Timeline Chart -->
                <div class="chart-card" style="grid-column: span 2;">
                    <h3><i class="fas fa-history" style="color: #3b82f6;"></i> Inspection Activity (Last 7 Days)</h3>
                    <div class="chart-container-inner">
                        <canvas id="timelineChart"></canvas>
                    </div>
                </div>

                <!-- Bar Chart: Damage Types -->
                <div class="chart-card" style="grid-column: 1 / -1;">
                    <h3><i class="fas fa-tags" style="color: #f59e0b;"></i> Most Frequent Damage Classes</h3>
                    <div class="chart-container-inner" style="height: 350px;">
                        <canvas id="damageTypeChart"></canvas>
                        <?php if (empty($top_damage_types)): ?>
                        <div style="position: absolute; justify-content: center; align-items: center; color: var(--text-secondary); background: rgba(255,255,255,0.8); z-index: 10; display: flex; width: 100%; height: 100%; font-weight: 600;">No damages detected yet</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="card" style="margin-bottom: 2.5rem;">
                <h3 style="margin-top: 0; font-size: 1.25rem; font-weight: 800; color: var(--primary-color); display: flex; align-items: center; gap: 0.75rem; border-bottom: 2px solid #f1f5f9; padding-bottom: 0.75rem;">
                    <i class="fas fa-coins" style="color: var(--secondary-color); background: #eff6ff; padding: 0.5rem; border-radius: 0.5rem;"></i> Financial Overview
                </h3>
                <div style="display: flex; flex-wrap: wrap; gap: 3rem; margin-top: 1.5rem;">
                    <div>
                        <div style="font-size: 0.85rem; color: var(--text-secondary); text-transform: uppercase; font-weight: 700; margin-bottom: 0.4rem;">Total Minimum Estimated Liability</div>
                        <div style="font-size: 2rem; font-weight: 800; color: var(--text-primary);">$<?= number_format($total_cost_min) ?></div>
                    </div>
                    <div>
                        <div style="font-size: 0.85rem; color: var(--text-secondary); text-transform: uppercase; font-weight: 700; margin-bottom: 0.4rem;">Total Maximum Estimated Liability</div>
                        <div style="font-size: 2rem; font-weight: 800; color: var(--danger-color);">$<?= number_format($total_cost_max) ?></div>
                    </div>
                </div>
            </div>

        </main>
    </div>

    <!-- Chart.js Logic -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Setup Defaults
            Chart.defaults.font.family = "'Inter', sans-serif";
            Chart.defaults.color = '#64748b';
            
            // 1. Severity Doughnut
            const severityCtx = document.getElementById('severityChart').getContext('2d');
            new Chart(severityCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Major Damage', 'Moderate', 'Minor', 'Clear'],
                    datasets: [{
                        data: <?= $js_severity_data ?>,
                        backgroundColor: ['#ef4444', '#f59e0b', '#10b981', '#cbd5e1'],
                        borderWidth: 2,
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { usePointStyle: true, padding: 20 } }
                    },
                    cutout: '70%'
                }
            });

            // 2. Timeline Line Chart
            const timelineCtx = document.getElementById('timelineChart').getContext('2d');
            new Chart(timelineCtx, {
                type: 'line',
                data: {
                    labels: <?= $js_timeline_labels ?>,
                    datasets: [{
                        label: 'Analyses Performed',
                        data: <?= $js_timeline_counts ?>,
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37, 99, 235, 0.1)',
                        borderWidth: 3,
                        pointBackgroundColor: '#ffffff',
                        pointBorderColor: '#2563eb',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: '#f1f5f9' } },
                        x: { grid: { display: false } }
                    }
                }
            });

            // 3. Damage Types Bar Chart
            const damageCtx = document.getElementById('damageTypeChart').getContext('2d');
            new Chart(damageCtx, {
                type: 'bar',
                data: {
                    labels: <?= $js_damage_labels ?>,
                    datasets: [{
                        label: 'Occurrences',
                        data: <?= $js_damage_counts ?>,
                        backgroundColor: '#3b82f6',
                        borderRadius: 6,
                        barThickness: 'flex',
                        maxBarThickness: 50
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: '#f1f5f9' } },
                        x: { grid: { display: false } }
                    }
                }
            });
        });
    </script>
    <script src="js/nav.js"></script>
</body>
</html>
