// ─── Image Cropper (banner avatar only; Edit Profile no longer has file field) ─
let cropper = null;

function startCropperFromFile(file) {
    const imageToCrop = document.getElementById('image-to-crop');
    const cropModal = document.getElementById('crop-modal');
    if (!imageToCrop || !cropModal || !file) return;

    const reader = new FileReader();
    reader.onload = function (event) {
        imageToCrop.src = event.target.result;
        cropModal.style.display = 'flex';

        if (cropper) cropper.destroy();

        cropper = new Cropper(imageToCrop, {
            aspectRatio: 1,
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

document.addEventListener('DOMContentLoaded', function () {
    const avatarFileInput = document.getElementById('avatar_file_input');
    const avatarPickerBtn = document.getElementById('avatar-picker-btn');

    if (avatarPickerBtn && avatarFileInput) {
        avatarPickerBtn.addEventListener('click', function () {
            avatarFileInput.click();
        });
    }

    if (avatarFileInput) {
        avatarFileInput.addEventListener('change', function (e) {
            const files = e.target.files;
            if (files && files.length > 0) {
                startCropperFromFile(files[0]);
            }
        });
    }
});

function cancelCrop() {
    const cropModal = document.getElementById('crop-modal');
    const avatarFileInput = document.getElementById('avatar_file_input');
    if (cropModal) cropModal.style.display = 'none';
    if (cropper) {
        cropper.destroy();
        cropper = null;
    }
    if (avatarFileInput) avatarFileInput.value = '';
}

function applyCrop() {
    if (!cropper) return;

    const canvas = cropper.getCroppedCanvas({ width: 300, height: 300 });
    const dataUrl = canvas.toDataURL('image/png');

    const cropModalEl = document.getElementById('crop-modal');
    if (cropModalEl) cropModalEl.style.display = 'none';
    cropper.destroy();
    cropper = null;

    const hidden = document.getElementById('avatar_cropped_input');
    const form = document.getElementById('avatar-only-form');
    if (hidden && form) {
        hidden.value = dataUrl;
        form.submit();
    }
    const avatarFileInput = document.getElementById('avatar_file_input');
    if (avatarFileInput) avatarFileInput.value = '';
}

// ─── Toggle profile view / edit form ─────────────────────────────────────────
function toggleEditMode() {
    const viewMode = document.getElementById('profile-view');
    const editMode = document.getElementById('profile-edit');
    const isEditing = !editMode.classList.contains('d-none') || editMode.style.display === 'block';
    if (isEditing) {
        viewMode.style.display = '';
        viewMode.classList.remove('d-none');
        editMode.style.display = 'none';
    } else {
        viewMode.style.display = 'none';
        editMode.style.display = 'block';
        editMode.classList.remove('d-none');
    }
}

// ─── Confirm account deletion ─────────────────────────────────────────────────
function confirmDelete() {
    if (confirm("Are you incredibly sure? This will instantly and permanently wipe your account and all damage analyses. This action cannot be undone.")) {
        document.getElementById('delete-form').submit();
    }
}
