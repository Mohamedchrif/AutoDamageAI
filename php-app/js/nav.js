// Mobile navigation menu toggle (shared across all pages)
function toggleMobileMenu() {
    const navLinks = document.getElementById('navLinks');
    const btn = document.querySelector('.mobile-menu-btn');
    if (navLinks) {
        navLinks.classList.toggle('active');
        if (btn) {
            btn.classList.toggle('is-open', navLinks.classList.contains('active'));
        }
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const navLinks = document.getElementById('navLinks');
    const btn = document.querySelector('.mobile-menu-btn');

    document.querySelectorAll('.nav-links a').forEach((link) => {
        link.addEventListener('click', () => {
            if (navLinks) navLinks.classList.remove('active');
            if (btn) btn.classList.remove('is-open');
        });
    });

    document.addEventListener(
        'click',
        (e) => {
            if (!navLinks || !navLinks.classList.contains('active')) return;
            if (e.target.closest('.mobile-menu-btn') || e.target.closest('#navLinks')) return;
            navLinks.classList.remove('active');
            if (btn) btn.classList.remove('is-open');
        },
        true
    );
});
