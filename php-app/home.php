<?php
require_once 'config.php';
$is_auth = is_logged_in();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AutoDamg | AI-Powered Vehicle Damage Detection</title>
    <meta name="description" content="Instant car damage detection using AI. Upload or capture photos to get accurate damage assessment in seconds.">
    <meta name="theme-color" content="#2c3e50">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/home.css">
</head>
<body>
    <!-- Navigation -->
    <header class="navbar">
        <div class="container header-content" style="width: 100%;">
            <a href="home.php" class="nav-logo" style="color: var(--primary-color);">
                <span class="logo-icon"><i class="fas fa-car-crash"></i></span> AutoDamg
            </a>
            
            <div class="mobile-menu-btn" onclick="toggleMobileMenu()">
                <span></span>
                <span></span>
                <span></span>
            </div>

            <nav>
                <ul class="nav-links" id="navLinks">
                    <?php if ($is_auth): ?>
                        <li><a href="dashboard.php"><i class="fas fa-th-large"></i> Dashboard</a></li>
                        <li><a href="analytics.php"><i class="fas fa-chart-line"></i> Analytics</a></li>
                        <li><a href="index.php"><i class="fas fa-plus"></i> New Analysis</a></li>
                        <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                        <li><a href="logout.php" class="nav-cta" style="color: white !important;"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                    <?php else: ?>
                        <li><a href="#features">Features</a></li>
                        <li><a href="#how-it-works">How It Works</a></li>
                        <li><a href="login.php">Login</a></li>
                        <li><a href="signup.php" class="nav-cta" style="color: white !important;">Sign Up</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container hero-content">
            <div class="hero-badge">Next-Generation Computer Vision</div>
            <h2>Detect Car Damage in <span>Seconds</span></h2>
            <p>Professional AI-powered vehicle inspection. Get accurate, instant damage assessment with state-of-the-art computer vision.</p>
            <div class="hero-buttons">
                <?php if ($is_auth): ?>
                <a href="index.php" class="submit-btn" style="width: auto; padding: 1rem 2.5rem;">
                    <i class="fas fa-play-circle" style="margin-right: 0.5rem;"></i> Start Analysis
                </a>
                <?php else: ?>
                <a href="login.php" class="submit-btn" style="width: auto; padding: 1rem 2.5rem;">
                    <i class="fas fa-sign-in-alt" style="margin-right: 0.5rem;"></i> Sign In to Start
                </a>
                <?php endif; ?>
            </div>
            
            <div class="hero-stats animate delay-3">
                <div class="stat-item">
                    <div class="stat-value">95%+</div>
                    <div class="stat-label">Accuracy</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">&lt;5s</div>
                    <div class="stat-label">Processing</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">17+</div>
                    <div class="stat-label">Damage Types</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features" id="features">
        <div class="container">
            <div class="section-title animate-on-scroll">
                <span class="section-tag" style="display: none;">✨ Features</span>
                <h2>Why Choose AutoDamg?</h2>
                <p>Powered by state-of-the-art YOLOv8 computer vision technology for accurate, instant damage assessment.</p>
            </div>

            <div class="features-grid">
                <div class="feature-card animate-on-scroll">
                    <div class="feature-icon"><i class="fas fa-camera"></i></div>
                    <div class="feature-content">
                        <h3>Capture or Upload</h3>
                        <p>Take photos directly with your camera or upload existing images. Supports JPG, PNG, BMP, and WebP formats up to 16MB.</p>
                    </div>
                </div>

                <div class="feature-card animate-on-scroll delay-1">
                    <div class="feature-icon"><i class="fas fa-brain"></i></div>
                    <div class="feature-content">
                        <h3>AI-Powered Analysis</h3>
                        <p>Advanced deep learning models detect 17+ types of damage with over 95% accuracy, trained on thousands of vehicle images.</p>
                    </div>
                </div>

                <div class="feature-card animate-on-scroll delay-2">
                    <div class="feature-icon"><i class="fas fa-bolt"></i></div>
                    <div class="feature-content">
                        <h3>Instant Results</h3>
                        <p>Get detailed damage reports in under 5 seconds. No waiting, no complicated forms - just upload and analyze.</p>
                    </div>
                </div>

                <div class="feature-card animate-on-scroll">
                    <div class="feature-icon"><i class="fas fa-crosshairs"></i></div>
                    <div class="feature-content">
                        <h3>Precise Detection</h3>
                        <p>Exact bounding boxes show damage location. Confidence scores and severity levels help prioritize repairs.</p>
                    </div>
                </div>

                <div class="feature-card animate-on-scroll delay-1">
                    <div class="feature-icon"><i class="fas fa-mobile-alt"></i></div>
                    <div class="feature-content">
                        <h3>Mobile Optimized</h3>
                        <p>Works perfectly on smartphones, tablets, and desktops. Responsive design ensures great experience on any device.</p>
                    </div>
                </div>

                <div class="feature-card animate-on-scroll delay-2">
                    <div class="feature-icon"><i class="fas fa-shield-alt"></i></div>
                    <div class="feature-content">
                        <h3>Privacy First</h3>
                        <p>Your images are processed securely and never stored. We respect your privacy and data security.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section class="how-it-works" id="how-it-works">
        <div class="container">
            <div class="section-title animate-on-scroll">
                <span class="section-tag" style="display: none;">📋 Process</span>
                <h2>How It Works</h2>
                <p>Three simple steps to get your vehicle damage assessment.</p>
            </div>

            <div class="pipeline">
                <div class="pipeline-step animate-on-scroll">
                    <div class="step-number">1</div>
                    <h3 class="step-title"><i class="fas fa-image"></i> Capture Image</h3>
                    <p class="step-description">Take a photo with your camera or upload an existing image of the vehicle damage.</p>
                </div>

                <div class="pipeline-step animate-on-scroll delay-1">
                    <div class="step-number">2</div>
                    <h3 class="step-title"><i class="fas fa-brain"></i> AI Analysis</h3>
                    <p class="step-description">Our YOLOv8 model processes the image and identifies all visible damage with confidence scores.</p>
                </div>

                <div class="pipeline-step animate-on-scroll delay-2">
                    <div class="step-number">3</div>
                    <h3 class="step-title"><i class="fas fa-file-alt"></i> Get Results</h3>
                    <p class="step-description">View annotated images with bounding boxes, severity levels, and detailed damage reports.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="cta-content animate-on-scroll">
            <h2>Ready to Analyze Your Vehicle?</h2>
            <p>Start detecting car damage in seconds with our AI-powered platform. Sign in to get started.</p>
            <div class="cta-buttons">
                <?php if ($is_auth): ?>
                <a href="index.php" class="btn btn-white">
                    <i class="fas fa-rocket"></i> Launch Application
                </a>
                <?php else: ?>
                <a href="login.php" class="btn btn-white">
                    <i class="fas fa-sign-in-alt"></i> Sign In to Start
                </a>
                <a href="signup.php" class="btn btn-outline">
                    <i class="fas fa-user-plus"></i> Create Account
                </a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer id="contact">
        <div class="container">
            <div class="footer-content">
                <div class="footer-column">
                    <h3><i class="fas fa-car-crash"></i> AutoDamg</h3>
                    <p style="margin-top: 1rem;">AI-powered vehicle damage detection using advanced computer vision technology. Fast, accurate, and easy to use.</p>
                    <div class="social-links">
                        <a href="#" title="GitHub"><i class="fab fa-github"></i></a>
                        <a href="#" title="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#" title="Twitter"><i class="fab fa-twitter"></i></a>
                        <a href="#" title="Email"><i class="fas fa-envelope"></i></a>
                    </div>
                </div>

                <div class="footer-column">
                    <h3><i class="fas fa-cogs"></i> Product</h3>
                    <ul>
                        <li><a href="index.php"><i class="fas fa-rocket"></i> Launch App</a></li>
                        <li><a href="#features"><i class="fas fa-star"></i> Features</a></li>
                        <li><a href="#how-it-works"><i class="fas fa-info-circle"></i> How It Works</a></li>
                    </ul>
                </div>

                <div class="footer-column">
                    <h3><i class="fas fa-book"></i> Resources</h3>
                    <ul>
                        <li><a href="#"><i class="fas fa-file-code"></i> Documentation</a></li>
                        <li><a href="#"><i class="fas fa-file-alt"></i> API Reference</a></li>
                        <li><a href="#"><i class="fas fa-life-ring"></i> Support</a></li>
                    </ul>
                </div>

                <div class="footer-column">
                    <h3><i class="fas fa-shield-alt"></i> Legal</h3>
                    <ul>
                        <li><a href="#"><i class="fas fa-user-shield"></i> Privacy Policy</a></li>
                        <li><a href="#"><i class="fas fa-file-contract"></i> Terms of Service</a></li>
                        <li><a href="#"><i class="fas fa-cookie"></i> Cookie Policy</a></li>
                    </ul>
                </div>
            </div>

            <div class="copyright">
                <p>&copy; 2026 AutoDamg. All rights reserved. | Developed by Debba Mohamed Cherif </p>
                <p style="margin-top: 0.5rem; color: rgba(255, 255, 255, 0.7); font-size: 0.9rem;">Empowering the future of vehicle analysis.</p>
            </div>
        </div>
    </footer>

    <script src="js/nav.js"></script>
    <script src="js/home.js"></script>
</body>
</html>
