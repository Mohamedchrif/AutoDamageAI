<?php
require_once 'config.php';
require_admin();

// All inspections system-wide (admin only)
$analyses = $pdo->query("SELECT * FROM analyses ORDER BY timestamp DESC")->fetchAll();

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
<link rel="stylesheet" href="css/analytics.css">
</head>
<body>
    <div class="page-wrapper">
        <?php include 'navbar.php'; ?>

        <main class="main-content container" style="padding-top: 3rem; margin: 0 auto; max-width: 1200px;">
            <header class="page-header" style="margin-bottom: 2.5rem; display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 1.5rem;">
                <div>
                    <h1 style="margin: 0; font-size: 2.25rem; font-weight: 800; color: var(--primary-color);">Advanced <span style="color: var(--secondary-color);">Analytics</span></h1>
                    <p style="color: var(--text-secondary); margin-top: 0.5rem; font-size: 1.05rem;">Platform-wide data insights and repair cost trends (all users).</p>
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
                    <h3><i class="fas fa-exclamation-circle" style="color: #ef4444;"></i> Severity Distribution (all scans)</h3>
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

                <div class="chart-card" style="grid-column: 1 / -1;">
                    <h3><i class="fas fa-coins" style="color: #0ea5e9;"></i> Financial Overview</h3>
                    <p style="margin: -0.75rem 0 1.25rem 0; font-size: 0.9rem; color: var(--text-secondary); line-height: 1.5;">
                        Aggregated repair estimates across all inspections (sum of per-scan minimum and maximum ranges).
                    </p>
                    <div class="financial-stats">
                        <div class="financial-stat">
                            <p class="financial-stat-label">Total minimum estimated liability</p>
                            <p class="financial-stat-value financial-stat-value--min">$<?= number_format($total_cost_min) ?></p>
                        </div>
                        <div class="financial-stat">
                            <p class="financial-stat-label">Total maximum estimated liability</p>
                            <p class="financial-stat-value financial-stat-value--max">$<?= number_format($total_cost_max) ?></p>
                        </div>
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
