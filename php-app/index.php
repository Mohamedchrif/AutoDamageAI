<?php
require_once 'config.php';
// Index is the "New Analysis" page.
// We can allow either guests or require login. The original Flask app required login for `/app`.
require_login(); 
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
                    <li><a href="index.php" class="active"><i class="fas fa-plus"></i> New Analysis</a></li>
                    <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                    <li><a href="logout.php" class="nav-cta" style="color: white !important;"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>

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
        <p class="hero-subtitle">Upload a photo of any vehicle to get a comprehensive AI report with damage location, severity, and repair cost estimate â€” in seconds.</p>

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
                        <p style="font-size:0.8rem; color:#94a3b8; margin-top:0.75rem;">JPG, PNG, WEBP â€” Max 16 MB</p>
                        <input type="file" id="file-input" name="image" accept="image/*" hidden>
                    </div>
                    <div class="file-preview" id="file-preview">
                        <div class="file-preview-icon"><i class="fas fa-file-image"></i></div>
                        <div class="file-preview-info">
                            <strong id="preview-name"></strong>
                            <span id="preview-meta"></span>
                        </div>
                        <a href="#" onclick="clearFile(event)" style="color:var(--danger-color); font-size:0.85rem; font-weight:600;">âœ• Remove</a>
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

            <!-- Camera Tab -->
            <div id="camera-tab" class="tab-content">
                <div class="camera-area" id="camera-area">
                    <video id="video-preview" autoplay playsinline style="display:block;"></video>
                    <canvas id="canvas-preview" style="display:none; width:100%; height:100%;"></canvas>
                </div>
                <div class="camera-controls">
                    <button class="cam-btn cam-btn-primary" id="start-camera-btn" onclick="startCamera()">
                        <i class="fas fa-video"></i> Start Camera
                    </button>
                    <button class="cam-btn cam-btn-capture" id="capture-btn" disabled onclick="capturePhoto()">
                        <i class="fas fa-circle"></i> Capture
                    </button>
                    <button class="cam-btn cam-btn-secondary" id="stop-camera-btn" style="display:none;" onclick="stopCamera()">
                        <i class="fas fa-stop"></i> Stop
                    </button>
                    <button class="cam-btn cam-btn-secondary" id="retake-btn" style="display:none;" onclick="retakePhoto()">
                        <i class="fas fa-redo"></i> Retake
                    </button>
                </div>
                <div class="error-card" id="camera-error-card"></div>
                <button class="analyze-btn" id="camera-analyze-btn" style="display:none;" onclick="submitCapture()">
                    <i class="fas fa-search"></i> Analyze Captured Photo
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
