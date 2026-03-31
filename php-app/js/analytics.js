/* Analytics specific logic - Chart.js implementations */

document.addEventListener('DOMContentLoaded', function() {
    if (!window.analyticsData) {
        console.warn('Analytics data not found. Charts will not load.');
        return;
    }

    const data = window.analyticsData;

    // Setup Defaults
    Chart.defaults.font.family = "'Inter', system-ui, -apple-system, sans-serif";
    Chart.defaults.color = '#64748b';
    
    // 1. Severity Doughnut
    const severityCanvas = document.getElementById('severityChart');
    if (severityCanvas) {
        new Chart(severityCanvas.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Major Damage', 'Moderate', 'Minor', 'Clear'],
                datasets: [{
                    data: data.severity,
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
    }

    // 2. Timeline Line Chart
    const timelineCanvas = document.getElementById('timelineChart');
    if (timelineCanvas) {
        new Chart(timelineCanvas.getContext('2d'), {
            type: 'line',
            data: {
                labels: data.timelineLabels,
                datasets: [{
                    label: 'Analyses Performed',
                    data: data.timelineCounts,
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
    }

    // 3. Damage Types Bar Chart
    const damageCanvas = document.getElementById('damageTypeChart');
    if (damageCanvas) {
        new Chart(damageCanvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: data.damageLabels,
                datasets: [{
                    label: 'Occurrences',
                    data: data.damageCounts,
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
    }
});
