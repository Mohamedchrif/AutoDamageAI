<?php
/**
 * Navigation Bar Component
 * This file is included in all main pages to provide a consistent navigation experience.
 * It dynamically highlights the active page and handles user authentication states.
 */

// Get the current page filename to determine the active menu item
$current_page = basename($_SERVER['PHP_SELF']);

// Check if the user is logged in (using the function from config.php)
$is_authenticated = is_logged_in();
?>

<!-- Global Navbar -->
<header class="navbar navbar-relative">
    <div class="container header-content full-width">
        <a href="index.php" class="nav-logo text-primary">
            <span class="logo-icon"><i class="fas fa-car-crash"></i></span> AutoDamg
        </a>
        
        <div class="mobile-menu-btn" onclick="toggleMobileMenu()">
            <span></span><span></span><span></span>
        </div>

        <nav>
            <ul class="nav-links" id="navLinks">
                <?php if ($is_authenticated): ?>
                    <!-- Authenticated Navigation -->
                    <li>
                        <a href="dashboard.php" class="<?= $current_page === 'dashboard.php' ? 'active' : '' ?>">
                            <i class="fas fa-th-large"></i> Dashboard
                        </a>
                    </li>
                    
                    <?php if (is_admin()): ?>
                        <li>
                            <a href="analytics.php" class="<?= $current_page === 'analytics.php' ? 'active' : '' ?>">
                                <i class="fas fa-chart-line"></i> Analytics
                            </a>
                        </li>
                        <li>
                            <a href="admin.php" class="<?= $current_page === 'admin.php' ? 'active' : '' ?>">
                                <i class="fas fa-shield-alt"></i> Admin
                            </a>
                        </li>
                    <?php endif; ?>

                    <li>
                        <a href="analyze.php" class="<?= ($current_page === 'analyze.php' || $current_page === 'result.php') ? 'active' : '' ?>">
                            <i class="fas fa-plus"></i> New Analysis
                        </a>
                    </li>
                    
                    <li>
                        <a href="profile.php" class="<?= $current_page === 'profile.php' ? 'active' : '' ?>">
                            <i class="fas fa-user"></i> Profile
                        </a>
                    </li>
                    
                    <li>
                        <a href="logout.php" class="nav-cta">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </li>

                <?php else: ?>
                    <!-- Guest Navigation (for Home Page) -->
                    <li><a href="index.php#features">Features</a></li>
                    <li><a href="index.php#how-it-works">How It Works</a></li>
                    <li><a href="login.php" class="<?= $current_page === 'login.php' ? 'active' : '' ?>">Login</a></li>
                    <li><a href="signup.php" class="nav-cta <?= $current_page === 'signup.php' ? 'active' : '' ?>">Sign Up</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</header>
