// ─── Confidence Bars ──────────────────────────────────────────────────────────
document.querySelectorAll('.confidence-bar[data-conf]').forEach(el => {
    el.style.setProperty('--conf', el.dataset.conf + '%');
});

// ─── Before / After Comparison Slider ────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    const container    = document.getElementById('comparison-container');
    const slider       = document.getElementById('compare-slider');
    const annotatedImg = document.getElementById('annotated-img-compare');
    const sliderPill   = document.getElementById('slider-pill');

    if (!container || !slider || !annotatedImg) return;

    let isDown = false;

    const startDrag = () => { isDown = true; };
    const stopDrag  = () => { isDown = false; };

    // Start drag from the pill or anywhere on the container
    sliderPill.addEventListener('mousedown',  startDrag);
    sliderPill.addEventListener('touchstart', startDrag, { passive: true });
    container.addEventListener('mousedown',   startDrag);
    container.addEventListener('touchstart',  startDrag, { passive: true });

    window.addEventListener('mouseup',  stopDrag);
    window.addEventListener('touchend', stopDrag);

    const doDrag = e => {
        if (!isDown) return;
        e.preventDefault(); // Prevent page scroll while dragging

        const rect    = container.getBoundingClientRect();
        const clientX = e.type === 'touchmove' ? e.touches[0].clientX : e.clientX;
        const percent = Math.min(100, Math.max(0, ((clientX - rect.left) / rect.width) * 100));

        slider.style.left          = percent + '%';
        annotatedImg.style.clipPath = `polygon(${percent}% 0, 100% 0, 100% 100%, ${percent}% 100%)`;
    };

    window.addEventListener('mousemove', doDrag);
    window.addEventListener('touchmove', doDrag, { passive: false });
});

// ─── PDF Report Download ───────────────────────────────────────────────────────
function downloadPDF() {
    const reportContent = document.getElementById('pdf-report-content');
    if (!reportContent) return;

    // Clone and mount invisibly so html2canvas can render it
    const clone = reportContent.cloneNode(true);
    clone.style.display = 'block';

    const tempWrap = Object.assign(document.createElement('div'), {});
    Object.assign(tempWrap.style, {
        position:      'fixed',
        top:           '0',
        left:          '0',
        width:         '800px',
        zIndex:        '-9999',
        opacity:       '0',
        pointerEvents: 'none',
    });
    tempWrap.appendChild(clone);
    document.body.appendChild(tempWrap);

    const filename = reportContent.dataset.filename || 'report';

    const opt = {
        margin:      10,
        filename:    `AutoDamg_Report_${filename}.pdf`,
        image:       { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true, logging: false },
        jsPDF:       { unit: 'mm', format: 'a4', orientation: 'portrait' },
    };

    // Small delay so the browser can paint the cloned content
    setTimeout(() => {
        html2pdf().set(opt).from(clone).save().then(() => {
            document.body.removeChild(tempWrap);
        });
    }, 300);
}
