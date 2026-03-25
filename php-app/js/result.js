// Set confidence bar widths from data-conf attribute
document.querySelectorAll('.confidence-bar[data-conf]').forEach(function (el) {
    el.style.setProperty('--conf', el.dataset.conf + '%');
});
