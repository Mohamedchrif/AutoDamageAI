// Toggle between profile view and edit form
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

// Confirm before submitting account deletion
function confirmDelete() {
    if (confirm("Are you incredibly sure? This will instantly and permanently wipe your account and all damage analyses. This action cannot be undone.")) {
        document.getElementById('delete-form').submit();
    }
}
