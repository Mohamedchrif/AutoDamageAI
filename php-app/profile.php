<?php
require_once 'config.php';
require_login();

$user = get_current_user_data($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete_account'])) {
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
                $profile_picture = $user['profile_picture'] ?? null;
                $upload_ok = true;

                if (!empty($_POST['cropped_image'])) {
                    // Instagram-style cropped image (Base64)
                    $data = $_POST['cropped_image'];
                    list($type, $data) = explode(';', $data);
                    list(, $data)      = explode(',', $data);
                    $data = base64_decode($data);
                    
                    $newFileName = 'user_' . $_SESSION['user_id'] . '_' . time() . '.png';
                    $uploadFileDir = 'assets/profiles/';
                    $dest_path = $uploadFileDir . $newFileName;
                    
                    if (file_put_contents($dest_path, $data)) {
                        if (!empty($user['profile_picture']) && file_exists($user['profile_picture'])) {
                            unlink($user['profile_picture']);
                        }
                        $profile_picture = $dest_path;
                    } else {
                        $upload_ok = false;
                        set_flash_message('danger', 'Error saving the cropped profile picture.');
                    }
                } elseif (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                    // Fallback to standard upload
                    $fileTmpPath = $_FILES['profile_picture']['tmp_name'];
                    $fileName = $_FILES['profile_picture']['name'];
                    $fileNameCmps = explode(".", $fileName);
                    $fileExtension = strtolower(end($fileNameCmps));
                    
                    $allowedfileExtensions = array('jpg', 'gif', 'png', 'jpeg', 'webp');
                    if (in_array($fileExtension, $allowedfileExtensions)) {
                        $newFileName = 'user_' . $_SESSION['user_id'] . '_' . time() . '.' . $fileExtension;
                        $uploadFileDir = 'assets/profiles/';
                        $dest_path = $uploadFileDir . $newFileName;
                        
                        if (move_uploaded_file($fileTmpPath, $dest_path)) {
                            if (!empty($user['profile_picture']) && file_exists($user['profile_picture'])) {
                                unlink($user['profile_picture']);
                            }
                            $profile_picture = $dest_path;
                        } else {
                            $upload_ok = false;
                            set_flash_message('danger', 'Error moving the uploaded file.');
                        }
                    } else {
                        $upload_ok = false;
                        set_flash_message('danger', 'Upload failed. Allowed file types: ' . implode(',', $allowedfileExtensions));
                    }
                }

                if ($upload_ok) {
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, phone = ?, profile_picture = ? WHERE id = ?");
                    try {
                        $stmt->execute([$username, $email, $phone, $profile_picture, $_SESSION['user_id']]);
                        set_flash_message('success', 'Profile updated successfully!');
                        $user = get_current_user_data($pdo); // Refresh data
                    } catch (Exception $e) {
                        set_flash_message('danger', 'An error occurred while updating your profile.');
                    }
                }
            }
        }
    }
    header("Location: profile.php");
    exit;
}

// Additional check for account deletion POST route could be handled here or separate script
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AutoDamg | Profile Settings</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css">
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/profile.css">
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
                        <li><a href="index.php"><i class="fas fa-plus"></i> New Analysis</a></li>
                        <li><a href="profile.php" class="active"><i class="fas fa-user"></i> Profile</a></li>
                        <li><a href="logout.php" class="nav-cta" style="color: white !important;"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                    </ul>
                </nav>
            </div>
        </header>

        <main class="main-content container" style="padding-top: 3rem; margin: 0 auto; max-width: 1200px;">
            <header class="page-header" style="margin-bottom: 2.5rem; display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 1.5rem;">
                <div>
                    <h1 style="margin: 0; font-size: 2.25rem; font-weight: 800; color: var(--primary-color);">Account <span style="color: var(--secondary-color);">Settings</span></h1>
                    <p style="color: var(--text-secondary); margin-top: 0.5rem; font-size: 1.05rem;">Manage your identity, contact details, and security preferences.</p>
                </div>
                <div class="security-badge" style="background: white; padding: 0.75rem 1.25rem; border-radius: 99px; border: 1px solid var(--border-color); display: inline-flex; align-items: center; gap: 0.75rem; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
                    <div style="width: 12px; height: 12px; background: var(--success-color); border-radius: 50%; box-shadow: 0 0 0 3px #dcfce7;"></div>
                    <span style="font-size: 0.875rem; font-weight: 700; color: var(--text-primary); text-transform: uppercase; letter-spacing: 0.05em;">Security: High</span>
                </div>
            </header>

            <?php display_flash_messages(); ?>

            <div class="card" style="padding: 0; overflow: hidden; margin-bottom: 2.5rem;">
                <!-- Header Banner Area -->
                <div class="profile-banner" style="height: 140px; background: linear-gradient(135deg, var(--primary-color) 0%, #1e3a5f 100%); position: relative;">
                    <div class="profile-banner-avatar">
                        <?php if (!empty($user['profile_picture']) && file_exists($user['profile_picture'])): ?>
                            <div class="avatar-large" style="width: 100px; height: 100px; border-radius: 50%; border: 5px solid white; box-shadow: 0 8px 24px rgba(0,0,0,0.12); overflow: hidden; background: white; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                <img src="<?= htmlspecialchars($user['profile_picture']) ?>?v=<?= time() ?>" alt="Profile Picture" style="width: 100%; height: 100%; object-fit: cover; display: block;">
                            </div>
                        <?php else: ?>
                            <div class="avatar-large" style="width: 100px; height: 100px; border-radius: 50%; background: var(--secondary-color); border: 5px solid white; box-shadow: 0 8px 24px rgba(0,0,0,0.12); display: flex; align-items: center; justify-content: center; font-size: 3rem; font-weight: 800; color: white;">
                                <?= strtoupper(substr($user['username'], 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="profile-content">
                    <!-- Static Profile Info -->
                    <div id="profile-view">
                        <div class="profile-header-view">
                            <div>
                                <h2 style="margin: 0; font-size: 1.85rem; font-weight: 800; color: var(--primary-color);"><?= htmlspecialchars($user['username']) ?></h2>
                                <p style="color: var(--text-secondary); margin-top: 0.5rem; display: inline-flex; align-items: center; gap: 0.5rem; font-weight: 500; background: #f8fafc; padding: 0.4rem 0.8rem; border-radius: 0.5rem; border: 1px solid var(--border-color);">
                                    <i class="fas fa-calendar-alt" style="color: var(--secondary-color);"></i> Member since <?= isset($user['created_at']) ? date('F d, Y', strtotime($user['created_at'])) : 'Recently' ?>
                                </p>
                            </div>
                            <button onclick="toggleEditMode()" class="submit-btn" style="width: auto; padding: 0.75rem 1.75rem; font-size: 0.95rem; display: flex; align-items: center; gap: 0.6rem; margin: 0; border-radius: 0.75rem;">
                                <i class="fas fa-edit"></i> Edit Profile
                            </button>
                        </div>

                        <div class="profile-grid">
                            <!-- Left Column: Personal Information -->
                            <div class="profile-section">
                                <h3 style="font-size: 1.1rem; margin-bottom: 1.75rem; display: flex; align-items: center; gap: 0.75rem; color: var(--primary-color); border-bottom: 2px solid #f1f5f9; padding-bottom: 0.75rem;">
                                    <i class="fas fa-id-card" style="color: var(--secondary-color); background: #eff6ff; padding: 0.5rem; border-radius: 0.5rem;"></i>
                                    Personal Information
                                </h3>
                                <div style="display: flex; flex-direction: column; gap: 1.75rem;">
                                    <div class="info-item">
                                        <label style="display: flex; align-items: center; gap: 0.5rem; color: var(--text-secondary); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem;">
                                            <i class="fas fa-user-circle"></i> Username
                                        </label>
                                        <p style="font-size: 1.1rem; font-weight: 600; color: var(--text-primary);"><?= htmlspecialchars($user['username']) ?></p>
                                    </div>
                                    <div class="info-item">
                                        <label style="display: flex; align-items: center; gap: 0.5rem; color: var(--text-secondary); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem;">
                                            <i class="fas fa-envelope"></i> Email Address
                                        </label>
                                        <p style="font-size: 1.1rem; font-weight: 600; color: var(--text-primary);"><?= htmlspecialchars($user['email']) ?></p>
                                    </div>
                                </div>
                            </div>

                            <!-- Right Column: Contact & Status -->
                            <div class="profile-section">
                                <h3 style="font-size: 1.1rem; margin-bottom: 1.75rem; display: flex; align-items: center; gap: 0.75rem; color: var(--primary-color); border-bottom: 2px solid #f1f5f9; padding-bottom: 0.75rem;">
                                    <i class="fas fa-shield-alt" style="color: var(--secondary-color); background: #eff6ff; padding: 0.5rem; border-radius: 0.5rem;"></i>
                                    Security & Status
                                </h3>
                                <div style="display: flex; flex-direction: column; gap: 1.75rem;">
                                    <div class="info-item">
                                        <label style="display: flex; align-items: center; gap: 0.5rem; color: var(--text-secondary); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem;">
                                            <i class="fas fa-phone"></i> Phone Number
                                        </label>
                                        <p style="font-size: 1.1rem; font-weight: 600; color: var(--text-primary);"><?= !empty($user['phone']) ? htmlspecialchars($user['phone']) : 'Not provided' ?></p>
                                    </div>
                                    <div class="info-item">
                                        <label style="display: flex; align-items: center; gap: 0.5rem; color: var(--text-secondary); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem;">
                                            <i class="fas fa-check-double"></i> Account Status
                                        </label>
                                        <p style="font-size: 1.1rem; font-weight: 600; color: var(--success-color); display: flex; align-items: center; gap: 0.5rem;">
                                            <i class="fas fa-verified"></i> Verified & Active
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Edit Profile Form (Hidden by default) -->
                    <div id="profile-edit" style="display: none; animation: fadeIn 0.3s ease;">
                        <style>
                            @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
                            .edit-actions { display: flex; gap: 1rem; margin-top: 3rem; }
                            .btn-cancel { background: white; border: 2px solid var(--border-color); color: var(--text-secondary); border-radius: 0.75rem; padding: 0.8rem 1.75rem; cursor: pointer; font-weight: 700; transition: all 0.2s; font-size: 0.95rem; }
                            .btn-cancel:hover { background: #f8fafc; color: var(--primary-color); border-color: #cbd5e1; }
                        </style>
                        <div style="margin-bottom: 2.5rem;">
                            <h2 style="margin: 0; font-size: 1.85rem; font-weight: 800; color: var(--primary-color);">Edit Profile Details</h2>
                            <p style="color: var(--text-secondary); margin-top: 0.4rem;">Keep your information up to date.</p>
                        </div>

                        <form action="profile.php" method="POST" enctype="multipart/form-data">
                            <div class="profile-edit-grid">
                                <div class="form-group" style="grid-column: 1 / -1;">
                                    <label class="form-label" style="font-weight: 700; color: var(--primary-color); margin-bottom: 0.5rem; display: block;">Profile Picture</label>
                                    <div class="input-with-icon" style="position: relative;">
                                        <i class="fas fa-image" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-secondary);"></i>
                                        <input type="file" id="profile_picture_input" accept="image/*" class="form-input" style="width: 100%; padding: 0.875rem 1rem 0.875rem 2.75rem; border: 1px solid var(--border-color); border-radius: 0.75rem; font-size: 1rem; transition: all 0.2s; background: white;">
                                        <input type="hidden" name="cropped_image" id="cropped_image_base64">
                                    </div>
                                    <p id="upload-status-text" style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 0.5rem;">Optional. Allowed formats: JPG, PNG, GIF, WebP.</p>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" style="font-weight: 700; color: var(--primary-color); margin-bottom: 0.5rem; display: block;">Username</label>
                                    <div class="input-with-icon" style="position: relative;">
                                        <i class="fas fa-user" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-secondary);"></i>
                                        <input type="text" name="username" class="form-input" style="width: 100%; padding: 0.875rem 1rem 0.875rem 2.75rem; border: 1px solid var(--border-color); border-radius: 0.75rem; font-size: 1rem; transition: all 0.2s;" value="<?= htmlspecialchars($user['username']) ?>" required>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" style="font-weight: 700; color: var(--primary-color); margin-bottom: 0.5rem; display: block;">Email Address</label>
                                    <div class="input-with-icon" style="position: relative;">
                                        <i class="fas fa-envelope" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-secondary);"></i>
                                        <input type="email" name="email" class="form-input" style="width: 100%; padding: 0.875rem 1rem 0.875rem 2.75rem; border: 1px solid var(--border-color); border-radius: 0.75rem; font-size: 1rem; transition: all 0.2s;" value="<?= htmlspecialchars($user['email']) ?>" required>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" style="font-weight: 700; color: var(--primary-color); margin-bottom: 0.5rem; display: block;">Phone Number</label>
                                    <div class="input-with-icon" style="position: relative;">
                                        <i class="fas fa-phone" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-secondary);"></i>
                                        <input type="tel" name="phone" class="form-input" style="width: 100%; padding: 0.875rem 1rem 0.875rem 2.75rem; border: 1px solid var(--border-color); border-radius: 0.75rem; font-size: 1rem; transition: all 0.2s;" value="<?= htmlspecialchars($user['phone']) ?>" placeholder="+1 (555) 000-0000">
                                    </div>
                                </div>
                            </div>
                            <div class="edit-actions">
                                <button type="submit" class="submit-btn" style="width: 160px; height: 48px; box-sizing: border-box; border: 2px solid transparent; padding: 0; margin: 0; border-radius: 0.75rem; font-size: 0.95rem; display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                                    <i class="fas fa-save"></i> Save Changes
                                </button>
                                <button type="button" onclick="toggleEditMode()" class="btn-cancel" style="width: 160px; height: 48px; box-sizing: border-box; padding: 0; margin: 0; border-radius: 0.75rem; font-size: 0.95rem; display: flex; align-items: center; justify-content: center;">
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="danger-card danger-card-inner">
                <div style="display: flex; gap: 1.5rem; align-items: center;">
                    <div style="width: 56px; height: 56px; background: #fee2e2; border-radius: 1rem; display: flex; align-items: center; justify-content: center; color: var(--danger-color); font-size: 1.5rem;">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div>
                        <h4 style="color: var(--danger-color); font-size: 1.25rem; font-weight: 800; margin-bottom: 0.35rem;">Danger Zone: Delete Account</h4>
                        <p style="color: var(--text-secondary); font-size: 0.95rem; margin: 0; max-width: 500px;">Permanently delete your account and all associated damage analyses. This action cannot be recovered or undone.</p>
                    </div>
                </div>
                <form action="profile.php" method="POST" id="delete-form" style="margin: 0;">
                    <input type="hidden" name="delete_account" value="1">
                    <button type="button" onclick="confirmDelete()" style="background: white; color: var(--danger-color); border: 2px solid #fca5a5; padding: 0.875rem 2rem; border-radius: 0.875rem; cursor: pointer; font-weight: 700; transition: all 0.2s; font-size: 0.95rem; display: inline-flex; align-items: center; gap: 0.5rem;" onmouseover="this.style.background='#fee2e2'; this.style.borderColor='var(--danger-color)'" onmouseout="this.style.background='white'; this.style.borderColor='#fca5a5'">
                        <i class="fas fa-trash-alt"></i> Delete Account
                    </button>
                </form>
            </div>
        </main>
    </div>

    <!-- Cropper Modal -->
    <div id="crop-modal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 9999; align-items: center; justify-content: center; padding: 20px;">
        <div style="background: white; padding: 20px; border-radius: 12px; width: 100%; max-width: 500px; display: flex; flex-direction: column; gap: 15px;">
            <h3 style="margin: 0; color: var(--primary-color);">Adjust Profile Picture</h3>
            <div style="width: 100%; height: 350px; background: #f8fafc; border-radius: 8px; overflow: hidden;">
                <img id="image-to-crop" src="" style="max-width: 100%; display: block;">
            </div>
            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 10px;">
                <button type="button" onclick="cancelCrop()" class="btn-cancel" style="width: 140px; height: 48px; box-sizing: border-box; padding: 0; margin: 0; border-radius: 0.75rem; font-family: inherit; font-size: 0.95rem; font-weight: 700; display: flex; align-items: center; justify-content: center;">Cancel</button>
                <button type="button" onclick="applyCrop()" class="submit-btn" style="width: 140px; height: 48px; box-sizing: border-box; border: 2px solid transparent; padding: 0; margin: 0; border-radius: 0.75rem; font-family: inherit; font-size: 0.95rem; font-weight: 700; display: flex; align-items: center; justify-content: center; gap: 0.5rem;"><i class="fas fa-crop"></i> Apply</button>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
    <script>
        let cropper = null;
        const imageToCrop = document.getElementById('image-to-crop');
        const fileInput = document.getElementById('profile_picture_input');
        const croppedInput = document.getElementById('cropped_image_base64');
        const cropModal = document.getElementById('crop-modal');

        fileInput.addEventListener('change', function(e) {
            const files = e.target.files;
            if (files && files.length > 0) {
                const file = files[0];
                const reader = new FileReader();
                reader.onload = function(event) {
                    imageToCrop.src = event.target.result;
                    cropModal.style.display = 'flex';
                    if (cropper) cropper.destroy();
                    cropper = new Cropper(imageToCrop, {
                        aspectRatio: 1, // 1:1 square ratio
                        viewMode: 1,
                        dragMode: 'move',
                        autoCropArea: 0.9,
                        restore: false,
                        guides: true,
                        center: true,
                        highlight: false,
                        cropBoxMovable: true,
                        cropBoxResizable: true,
                        toggleDragModeOnDblclick: false,
                    });
                };
                reader.readAsDataURL(file);
            }
        });

        function cancelCrop() {
            cropModal.style.display = 'none';
            if (cropper) cropper.destroy();
            fileInput.value = ''; 
        }

        function applyCrop() {
            if (!cropper) return;
            const canvas = cropper.getCroppedCanvas({
                width: 300,
                height: 300,
            });
            croppedInput.value = canvas.toDataURL('image/png');
            cropModal.style.display = 'none';
            cropper.destroy();
            cropper = null;
            document.getElementById('upload-status-text').innerHTML = '<i class="fas fa-check-circle" style="color:var(--success-color);"></i> Image cropped and ready to upload! Click <b>Save Changes</b> below.';
        }
    </script>


    <script src="js/nav.js"></script>
    <script src="js/profile.js"></script>
</body>
</html>
