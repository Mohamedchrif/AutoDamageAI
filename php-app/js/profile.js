// ─── Image Cropper & Base64 Upload ───────────────────────────────────────────
let cropper = null;

document.addEventListener('DOMContentLoaded', function () {
    const imageToCrop  = document.getElementById('image-to-crop');
    const fileInput    = document.getElementById('profile_picture_input');
    const croppedInput = document.getElementById('cropped_image_base64');
    const cropModal    = document.getElementById('crop-modal');

    if (!fileInput) return; // Guard: only run on profile page

    fileInput.addEventListener('change', function (e) {
        const files = e.target.files;
        if (files && files.length > 0) {
            const file   = files[0];
            const reader = new FileReader();

            reader.onload = function (event) {
                imageToCrop.src = event.target.result;
                cropModal.style.display = 'flex';

                if (cropper) cropper.destroy();

                cropper = new Cropper(imageToCrop, {
                    aspectRatio:          1,   // 1:1 square
                    viewMode:             1,
                    dragMode:             'move',
                    autoCropArea:         0.9,
                    restore:              false,
                    guides:               true,
                    center:               true,
                    highlight:            false,
                    cropBoxMovable:       true,
                    cropBoxResizable:     true,
                    toggleDragModeOnDblclick: false,
                });
            };

            reader.readAsDataURL(file); // Convert to base64 DataURL
        }
    });
});

function cancelCrop() {
    const cropModal = document.getElementById('crop-modal');
    const fileInput = document.getElementById('profile_picture_input');
    cropModal.style.display = 'none';
    if (cropper) { cropper.destroy(); cropper = null; }
    fileInput.value = '';
}

function applyCrop() {
    if (!cropper) return;

    const canvas = cropper.getCroppedCanvas({ width: 300, height: 300 });

    // Encode cropped image as base64 PNG and store in hidden input
    document.getElementById('cropped_image_base64').value = canvas.toDataURL('image/png');

    document.getElementById('crop-modal').style.display = 'none';
    cropper.destroy();
    cropper = null;

    document.getElementById('upload-status-text').innerHTML =
        '<i class="fas fa-check-circle" style="color:var(--success-color);"></i> Image cropped and ready to upload! Click <b>Save Changes</b> below.';
}

// ─── Toggle profile view / edit form ─────────────────────────────────────────
function toggleEditMode() {
    const viewMode = document.getElementById('profile-view');
    const editMode = document.getElementById('profile-edit');
    if (viewMode.style.display === 'none') {
        viewMode.style.display = 'block';
        editMode.style.display = 'none';
    } else {
        viewMode.style.display = 'none';
        editMode.style.display = 'block';
    }
}

// ─── Confirm account deletion ─────────────────────────────────────────────────
function confirmDelete() {
    if (confirm("Are you incredibly sure? This will instantly and permanently wipe your account and all damage analyses. This action cannot be undone.")) {
        document.getElementById('delete-form').submit();
    }
}
