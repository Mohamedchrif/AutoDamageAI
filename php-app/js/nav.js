// Mobile navigation menu toggle (shared across all pages)
function toggleMobileMenu() {
    const navLinks = document.getElementById('navLinks');
    if(navLinks) navLinks.classList.toggle('active');
}

document.addEventListener('DOMContentLoaded', function () {
    // Close mobile menu when clicking any nav link
    document.querySelectorAll('.nav-links a').forEach(link => {
        link.addEventListener('click', () => {
            const navLinks = document.getElementById('navLinks');
            if(navLinks) navLinks.classList.remove('active');
        });
    });
});
