<?php
require_once 'config.php';
// Index is the "New Analysis" page.
// We can allow either guests or require login. The original Flask app required login for `/app`.
// require_login(); 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AutoDamg | Vehicle Damage Analysis</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/index.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>


</head>
<body>
<div class="page-wrapper">

    <!-- Navbar -->
    <?php include 'navbar.php'; ?>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loading-overlay">
        <div class="loading-spinner"></div>
        <div>
            <div class="loading-text">Analyzing Damage...</div>
            <div class="loading-sub">Our AI is inspecting your vehicle. This takes a few seconds.</div>
        </div>
    </div>

    <!-- Hero + Upload Card -->
    <main class="hero-section">
        <div class="hero-badge"><i class="fas fa-robot"></i> AI-Powered Damage Detection</div>
        <h1 class="hero-title">Instant Vehicle<br>Damage Assessment</h1>
        <p class="hero-subtitle">Upload a photo of any vehicle to get a comprehensive AI report with damage location, severity, and repair cost estimate &mdash; in seconds.</p>

        <div class="upload-card">
            <!-- Tabs -->
            <div class="upload-tabs">
                <button class="tab-btn active" onclick="switchTab('upload', this)">
                    <i class="fas fa-upload"></i> Upload Photo
                </button>
                <button class="tab-btn" onclick="switchTab('camera', this)">
                    <i class="fas fa-camera"></i> Use Camera
                </button>
            </div>

            <!-- Upload Tab -->
            <div id="upload-tab" class="tab-content active">
                <form id="upload-form" enctype="multipart/form-data">
                    <div class="drop-zone" id="drop-zone" onclick="document.getElementById('file-input').click()">
                        <div class="drop-zone-icon"><i class="fas fa-cloud-upload-alt"></i></div>
                        <h3>Drop your image here</h3>
                        <p>or <span class="browse-link">browse files</span> from your computer</p>
                        <p style="font-size:0.8rem; color:#94a3b8; margin-top:0.75rem;">JPG, PNG, WEBP &mdash; Max 16 MB</p>
                        <input type="file" id="file-input" name="image" accept="image/*" hidden>
                    </div>
                    <div class="file-preview" id="file-preview">
                        <div class="file-preview-icon"><i class="fas fa-file-image"></i></div>
                        <div class="file-preview-info">
                            <strong id="preview-name"></strong>
                            <span id="preview-meta"></span>
                        </div>
                        <a href="#" onclick="clearFile(event)" style="color:var(--danger-color); font-size:0.85rem; font-weight:600;"><i class="fas fa-times" style="margin-right:0.25rem;"></i>Remove</a>
                    </div>
                    <div class="error-card" id="error-card">
                        <i class="fas fa-exclamation-circle" style="font-size:1.5rem;margin-bottom:0.5rem;"></i>
                        <div id="error-message"></div>
                    </div>
                    <button type="submit" class="analyze-btn" id="analyze-btn" disabled>
                        <i class="fas fa-search"></i> Analyze Vehicle Damage
                    </button>
                </form>
            </div>

            <!-- Camera Tab: Native Device Camera -->
            <div id="camera-tab" class="tab-content">
                <p class="camera-live-hint">Capture a photo directly using your device's full-screen camera.</p>
                <div id="camera-area" class="drop-zone" style="cursor: default; pointer-events: none; margin-bottom: 1.5rem; display: flex; flex-direction: column; justify-content: center; align-items: center; min-height: 250px; padding: 1.5rem;">
                    <!-- Preview Mode -->
                    <div id="native-camera-preview" style="display:none; width:100%; height:100%; flex-direction:column; align-items:center;">
                        <img id="native-camera-img" style="max-width:100%; max-height:220px; border-radius:0.75rem; object-fit:contain; box-shadow: 0 4px 12px rgba(0,0,0,0.1); pointer-events: auto;" />
                        <a href="#" onclick="clearNativeCamera(event)" style="color:var(--danger-color); font-size:0.9rem; font-weight:600; margin-top:12px; pointer-events: auto;"><i class="fas fa-times" style="margin-right:0.25rem;"></i> Remove Photo</a>
                    </div>
                    <!-- Placeholder Mode -->
                    <div id="native-camera-placeholder" style="text-align:center;">
                        <div class="drop-zone-icon" style="margin: 0 auto 1rem;"><i class="fas fa-camera"></i></div>
                        <h3 style="font-size: 1.25rem; margin-bottom: 0.5rem; color: var(--primary-color);">No Photo Captured Yet</h3>
                        <p style="color: var(--text-secondary); font-size: 0.9rem;">Tap the button below to take a picture of the vehicle damage.</p>
                    </div>
                </div>
                
                <input type="file" id="native-camera-input" accept="image/*" capture="environment" hidden>
                
                <div class="camera-controls">
                    <button type="button" class="cam-btn cam-btn-primary" style="width: 100%; display: flex; justify-content: center; padding: 12px; border-radius: 8px; font-weight: 600; font-size: 1rem; background: var(--primary-color); color: white; border: none; cursor: pointer; align-items: center; gap: 8px;" onclick="document.getElementById('native-camera-input').click()">
                        <i class="fas fa-camera"></i> Open Full Camera
                    </button>
                </div>
                
                <div class="error-card" id="camera-error-card" style="margin-top:1rem;"></div>
                <button type="button" class="analyze-btn" id="camera-analyze-btn" style="display:none; margin-top: 1rem; width: 100%; justify-content: center; padding: 14px; border-radius: 8px; font-weight: 600; font-size: 1rem; background: #10b981; color: white; border: none; cursor: pointer; align-items: center; gap: 8px;" onclick="submitNativeCapture()">
                    <i class="fas fa-search"></i> Analyze captured photo
                </button>
            </div>
        </div>
    </main>

    <!-- Trust Bar -->
    <div class="trust-bar">
        <div class="trust-item"><i class="fas fa-shield-alt"></i> Secure & Private</div>
        <div class="trust-item"><i class="fas fa-bolt"></i> Results in Seconds</div>
        <div class="trust-item"><i class="fas fa-robot"></i> YOLOv8 AI Model</div>
        <div class="trust-item"><i class="fas fa-dollar-sign"></i> Cost Estimation</div>
    </div>
</div>

<script src="js/nav.js"></script>
<script src="js/index.js"></script>
</body>
</html>
