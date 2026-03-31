<?php
require_once 'config.php';
require_login();

$user = get_current_user_data($pdo);
$show_edit = isset($_GET['edit']) && $_GET['edit'] == '1';

if (isset($_POST['delete_account'])) {
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    try {
        $stmt->execute([$_SESSION['user_id']]);
        session_unset();
        session_destroy();
        header("Location: home.php");
        exit;
    } catch (Exception $e) {
        set_flash_message('danger', 'An error occurred. Please try again.');
        header("Location: profile.php");
        exit;
    }
}

// Quick avatar update from banner (crop modal → POST without full profile form)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['avatar_only'] ?? '') === '1') {
    $pic = $_POST['cropped_image'] ?? '';
    if (!is_string($pic) || strpos($pic, 'data:image/') !== 0) {
        set_flash_message('danger', 'Invalid image.');
    } elseif (strlen($pic) > 5 * 1024 * 1024) {
        set_flash_message('danger', 'Image too large. Try a smaller photo.');
    } else {
        $stmt = $pdo->prepare('UPDATE users SET profile_pic = ? WHERE id = ?');
        try {
            $stmt->execute([$pic, $_SESSION['user_id']]);
            set_flash_message('success', 'Profile photo updated.');
        } catch (Exception $e) {
            set_flash_message('danger', 'Could not save photo.');
        }
    }
    header('Location: profile.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete_account']) && !isset($_POST['change_password'])) {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if (empty($username) || empty($email)) {
        set_flash_message('danger', 'Username and Email are required.');
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->execute([$username, $_SESSION['user_id']]);
        if ($stmt->fetch()) {
            set_flash_message('danger', 'That username is already taken.');
        } else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $_SESSION['user_id']]);
            if ($stmt->fetch()) {
                set_flash_message('danger', 'That email is already registered to another account.');
            } else {
                $profile_pic = $user['profile_pic'] ?? null;
                $upload_ok = true;

                if (!empty($_POST['cropped_image'])) {
                    // Instagram-style cropped image (Base64)
                    $profile_pic = $_POST['cropped_image'];
                } elseif (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                    // Fallback to standard upload
                    $fileTmpPath = $_FILES['profile_picture']['tmp_name'];
                    $fileNameCmps = explode(".", $_FILES['profile_picture']['name']);
                    $fileExtension = strtolower(end($fileNameCmps));
                    
                    $allowedfileExtensions = array('jpg', 'gif', 'png', 'jpeg', 'webp');
                    if (in_array($fileExtension, $allowedfileExtensions)) {
                        $finfo = new finfo(FILEINFO_MIME_TYPE);
                        $mimeType = $finfo->file($fileTmpPath);
                        $profile_pic = 'data:' . $mimeType . ';base64,' . base64_encode(file_get_contents($fileTmpPath));
                    } else {
                        $upload_ok = false;
                        set_flash_message('danger', 'Upload failed. Allowed file types: ' . implode(',', $allowedfileExtensions));
                    }
                }

                if ($upload_ok) {
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, phone = ?, profile_pic = ? WHERE id = ?");
                    try {
                        $stmt->execute([$username, $email, $phone, $profile_pic, $_SESSION['user_id']]);
                        set_flash_message('success', 'Profile updated successfully!');
                        $user = get_current_user_data($pdo); // Refresh data
                    } catch (Exception $e) {
                        set_flash_message('danger', 'An error occurred while updating your profile.');
                        header("Location: profile.php?edit=1");
                        exit;
                    }
                } else {
                    header("Location: profile.php?edit=1");
                    exit;
                }
            }
        }
    }
    header("Location: profile.php");
    exit;
}

// Password Change Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_pwd = $_POST['current_password'];
    $new_pwd = $_POST['new_password'];
    $confirm_pwd = $_POST['confirm_password'];
    
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $db_pass = $stmt->fetchColumn();
    
    if ($db_pass && password_verify($current_pwd, $db_pass)) {
        if ($new_pwd === $confirm_pwd) {
            if (strlen($new_pwd) >= 8) {
                $hash = password_hash($new_pwd, PASSWORD_DEFAULT);
                $update_stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                if ($update_stmt->execute([$hash, $_SESSION['user_id']])) {
                    set_flash_message('password_success', 'Your password has been successfully changed! Security score increased.');
                    header("Location: profile.php?edit=1#password-section");
                    exit;
                } else {
                    set_flash_message('password_danger', 'System error occurred while updating the password.');
                    header("Location: profile.php?edit=1#password-section");
                    exit;
                }
            } else {
                set_flash_message('password_danger', 'The new password must be at least 8 characters long.');
                header("Location: profile.php?edit=1#password-section");
                exit;
            }
        } else {
            set_flash_message('password_danger', 'New passwords do not match!');
            header("Location: profile.php?edit=1#password-section");
            exit;
        }
    } else {
        set_flash_message('password_danger', 'Current password is incorrect! Action blocked.');
        header("Location: profile.php?edit=1#password-section");
        exit;
    }
    header("Location: profile.php");
    exit;
}

// Account deletion handled at top
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AutoDamg | Profile Settings</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/profile.css">
</head>
<body>
    <form id="avatar-only-form" method="POST" action="profile.php" hidden>
        <input type="hidden" name="avatar_only" value="1">
        <input type="hidden" name="cropped_image" id="avatar_cropped_input" value="">
    </form>
    <div class="page-wrapper">
        <?php include 'navbar.php'; ?>

        <main class="main-content container profile-main-container">
            <header class="page-header profile-page-header">
                <div>
                    <h1 class="profile-page-title">Account <span>Settings</span></h1>
                    <p class="profile-page-subtitle">Manage your identity, contact details, and security preferences.</p>
                </div>
                <div class="security-badge-container">
                    <div class="security-badge-dot"></div>
                    <span class="security-badge-text">Security: High</span>
                </div>
            </header>

            <?php display_flash_messages(); ?>

            <div class="card profile-outer-card">
                <!-- Header Banner Area -->
                <div class="profile-banner">
                    <div class="profile-banner-avatar">
                        <input type="file" id="avatar_file_input" accept="image/*" hidden aria-hidden="true" tabindex="-1">
                        <button type="button" class="avatar-picker-btn" id="avatar-picker-btn" title="<?= htmlspecialchars('Change profile photo', ENT_QUOTES, 'UTF-8') ?>" aria-label="Change profile photo">
                            <?php if (!empty($user['profile_pic'])): ?>
                                <div class="avatar-large avatar-has-photo">
                                    <img src="<?= htmlspecialchars($user['profile_pic'], ENT_QUOTES, 'UTF-8') ?>" alt="" class="avatar-photo-img">
                                </div>
                            <?php else: ?>
                                <div class="avatar-large avatar-no-photo">
                                    <?= strtoupper(substr($user['username'], 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                            <span class="avatar-picker-overlay" aria-hidden="true"><i class="fas fa-camera"></i><span class="avatar-picker-text">Change</span></span>
                        </button>
                    </div>
                </div>

                <div class="profile-content">
                    <!-- Static Profile Info -->
                    <div id="profile-view" class="<?= $show_edit ? 'd-none' : '' ?>">
                        <div class="profile-header-view">
                            <div>
                                <h2 class="profile-view-username"><?= htmlspecialchars($user['username']) ?></h2>
                                <p class="profile-view-member-since">
                                    <i class="fas fa-calendar-alt"></i> Member since <?= isset($user['created_at']) ? date('F d, Y', strtotime($user['created_at'])) : 'Recently' ?>
                                </p>
                            </div>
                            <button onclick="toggleEditMode()" class="submit-btn profile-edit-btn">
                                <i class="fas fa-edit"></i> Edit Profile
                            </button>
                        </div>

                        <div class="profile-grid">
                            <!-- Left Column: Personal Information -->
                            <div class="profile-section">
                                <h3 class="profile-section-title">
                                    <i class="fas fa-id-card"></i>
                                    Personal Information
                                </h3>
                                <div class="profile-info-list">
                                    <div class="info-item">
                                        <label class="profile-info-label">
                                            <i class="fas fa-user-circle"></i> Username
                                        </label>
                                        <p class="profile-info-value"><?= htmlspecialchars($user['username']) ?></p>
                                    </div>
                                    <div class="info-item">
                                        <label class="profile-info-label">
                                            <i class="fas fa-envelope"></i> Email Address
                                        </label>
                                        <p class="profile-info-value"><?= htmlspecialchars($user['email']) ?></p>
                                    </div>
                                </div>
                            </div>

                            <!-- Right Column: Contact & Status -->
                            <div class="profile-section">
                                <h3 class="profile-section-title">
                                    <i class="fas fa-shield-alt"></i>
                                    Security & Status
                                </h3>
                                <div class="profile-info-list">
                                    <div class="info-item">
                                        <label class="profile-info-label">
                                            <i class="fas fa-phone"></i> Phone Number
                                        </label>
                                        <p class="profile-info-value"><?= !empty($user['phone']) ? htmlspecialchars($user['phone']) : 'Not provided' ?></p>
                                    </div>
                                    <div class="info-item">
                                        <label class="profile-info-label">
                                            <i class="fas fa-check-double"></i> Account Status
                                        </label>
                                        <p class="profile-info-value profile-success-text">
                                            <i class="fas fa-verified"></i> Verified & Active
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Edit Profile Form (Hidden by default) -->
                    <div id="profile-edit" class="profile-edit-section <?= $show_edit ? '' : 'd-none' ?>">
                        <div class="profile-edit-header">
                            <h2>Edit Profile Details</h2>
                            <p>Keep your information up to date. To change your photo, use your profile picture above.</p>
                        </div>

                        <form action="profile.php" method="POST">
                            <div class="profile-edit-grid">
                                <div class="form-group">
                                    <label class="form-label profile-edit-label">Username</label>
                                    <div class="input-with-icon profile-input-wrapper">
                                        <i class="fas fa-user profile-input-icon"></i>
                                        <input type="text" name="username" class="form-input profile-form-input" value="<?= htmlspecialchars($user['username']) ?>" required>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label profile-edit-label">Email Address</label>
                                    <div class="input-with-icon profile-input-wrapper">
                                        <i class="fas fa-envelope profile-input-icon"></i>
                                        <input type="email" name="email" class="form-input profile-form-input" value="<?= htmlspecialchars($user['email']) ?>" required>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label profile-edit-label">Phone Number</label>
                                    <div class="input-with-icon profile-input-wrapper">
                                        <i class="fas fa-phone profile-input-icon"></i>
                                        <input type="tel" name="phone" class="form-input profile-form-input" value="<?= htmlspecialchars($user['phone']) ?>" placeholder="+1 (555) 000-0000">
                                    </div>
                                </div>
                            </div>
                            <div class="edit-actions">
                                <button type="submit" class="submit-btn profile-action-btn">
                                    <i class="fas fa-save"></i> Save Changes
                                </button>
                                <button type="button" onclick="toggleEditMode()" class="btn-cancel profile-action-btn">
                                    Cancel
                                </button>
                            </div>
                        </form>

                        <hr class="profile-divider">

                        <div id="password-section" class="profile-password-header">
                            <h2>
                                <i class="fas fa-key"></i> Change Password
                            </h2>
                            <p>Ensure your account is using a secure, random password.</p>
                        </div>
                        
                        <form action="profile.php" method="POST">
                            <input type="hidden" name="change_password" value="1">
                            
                            <?php display_flash_messages('password_inline'); ?>
                            
                            <div class="profile-edit-grid profile-password-grid">
                                <div class="form-group full-width-group">
                                    <label class="form-label profile-edit-label">Current Password</label>
                                    <div class="input-with-icon profile-input-wrapper">
                                        <i class="fas fa-lock profile-input-icon"></i>
                                        <input type="password" id="current_password" name="current_password" class="form-input profile-form-input" required>
                                        <button type="button" class="toggle-password" onclick="togglePassword('current_password', this)" title="Toggle visibility"><i class="fas fa-eye"></i></button>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label profile-edit-label">New Password</label>
                                    <div class="input-with-icon profile-input-wrapper">
                                        <i class="fas fa-shield-alt profile-input-icon"></i>
                                        <input type="password" id="new_password" name="new_password" class="form-input profile-form-input" minlength="8" required>
                                        <button type="button" class="toggle-password" onclick="togglePassword('new_password', this)" title="Toggle visibility"><i class="fas fa-eye"></i></button>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label profile-edit-label">Confirm New Password</label>
                                    <div class="input-with-icon profile-input-wrapper">
                                        <i class="fas fa-check profile-input-icon"></i>
                                        <input type="password" id="confirm_password" name="confirm_password" class="form-input profile-form-input" minlength="8" required>
                                        <button type="button" class="toggle-password" onclick="togglePassword('confirm_password', this)" title="Toggle visibility"><i class="fas fa-eye"></i></button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="edit-actions password-actions">
                                <button type="submit" class="submit-btn profile-action-btn btn-secondary-bg">
                                    <i class="fas fa-lock"></i> Update Password
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="danger-card danger-card-inner">
                <div class="danger-card-header">
                    <div class="danger-card-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="danger-card-text">
                        <h4>Danger Zone: Delete Account</h4>
                        <p>Permanently delete your account and all associated damage analyses. This action cannot be recovered or undone.</p>
                    </div>
                </div>
                <form action="profile.php" method="POST" id="delete-form" class="danger-card-form">
                    <input type="hidden" name="delete_account" value="1">
                    <button type="button" onclick="confirmDelete()" class="btn-danger-outline">
                        <i class="fas fa-trash-alt"></i> Delete Account
                    </button>
                </form>
            </div>
        </main>
    </div>

    <!-- Cropper Modal -->
    <div id="crop-modal" class="cropper-modal-overlay">
        <div class="cropper-modal-content">
            <h3>Adjust Profile Picture</h3>
            <div class="cropper-img-container">
                <img id="image-to-crop" src="">
            </div>
            <div class="cropper-modal-actions">
                <button type="button" onclick="cancelCrop()" class="btn-cancel cropper-btn">Cancel</button>
                <button type="button" onclick="applyCrop()" class="submit-btn cropper-btn"><i class="fas fa-crop"></i> Apply</button>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>


    <script src="js/nav.js"></script>
    <script src="js/auth.js"></script>
    <script src="js/profile.js"></script>
</body>
</html>
