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

// ─── PDF Report (jsPDF + fetch image — no html2canvas) ─────────────────────────
async function blobToDataURL(blob) {
    return new Promise((resolve, reject) => {
        const fr = new FileReader();
        fr.onload = () => resolve(fr.result);
        fr.onerror = () => reject(new Error('read'));
        fr.readAsDataURL(blob);
    });
}

async function loadPdfImageData(url) {
    if (!url || typeof url !== 'string') return null;
    if (url.startsWith('data:')) return url;
    const res = await fetch(url, { credentials: 'same-origin', cache: 'no-store' });
    if (!res.ok) throw new Error('image http ' + res.status);
    const blob = await res.blob();
    return blobToDataURL(blob);
}

async function downloadPDF() {
    const dataEl = document.getElementById('report-pdf-data');
    if (!dataEl) {
        alert('PDF data missing on this page.');
        return;
    }

    const jspdf = window.jspdf;
    if (!jspdf || !jspdf.jsPDF) {
        alert('PDF library failed to load. Check your network connection.');
        return;
    }

    let data;
    try {
        data = JSON.parse(dataEl.textContent);
    } catch (e) {
        console.error(e);
        alert('Could not read report data.');
        return;
    }

    const { jsPDF } = jspdf;
    const doc = new jsPDF({ unit: 'mm', format: 'a4', orientation: 'portrait' });
    const pageW = doc.internal.pageSize.getWidth();
    const margin = 14;
    let y = margin;

    doc.setFont('helvetica', 'bold');
    doc.setFontSize(18);
    doc.text('AutoDamg Inspection Report', pageW / 2, y, { align: 'center' });
    y += 9;

    doc.setFont('helvetica', 'normal');
    doc.setFontSize(10);
    doc.setTextColor(90);
    doc.text('ID: ' + String(data.filename) + ' | Date: ' + String(data.date), pageW / 2, y, { align: 'center' });
    doc.setTextColor(0);
    y += 12;

    let imgData = null;
    if (data.image) {
        try {
            imgData = await loadPdfImageData(data.image);
        } catch (e) {
            console.warn('PDF image load failed', e);
        }
    }

    if (imgData) {
        const fmt = imgData.toLowerCase().includes('image/png') ? 'PNG' : 'JPEG';
        const maxW = pageW - 2 * margin;
        const props = doc.getImageProperties(imgData);
        const drawH = (props.height * maxW) / props.width;
        const capH = 130;
        const hDraw = Math.min(drawH, capH);
        const wDraw = drawH > capH ? (props.width * capH) / props.height : maxW;
        doc.addImage(imgData, fmt, margin, y, wDraw, hDraw);
        y += hDraw + 10;
    }

    doc.setFont('helvetica', 'bold');
    doc.setFontSize(13);
    doc.text('Detected Damage Details', margin, y);
    y += 8;

    doc.setFont('helvetica', 'normal');
    doc.setFontSize(10);
    const issues = Array.isArray(data.issues) ? data.issues : [];

    if (issues.length === 0) {
        doc.setTextColor(100);
        doc.text('No damage detected.', margin, y);
        doc.setTextColor(0);
        y += 10;
    } else {
        doc.setFillColor(248, 250, 252);
        doc.rect(margin, y - 4, pageW - 2 * margin, 7, 'F');
        doc.setFont('helvetica', 'bold');
        doc.text('Damage Type', margin + 1, y + 1);
        doc.text('Severity', margin + 75, y + 1);
        doc.text('Cost', pageW - margin - 1, y + 1, { align: 'right' });
        doc.setFont('helvetica', 'normal');
        y += 10;

        issues.forEach((issue) => {
            if (y > 278) {
                doc.addPage();
                y = margin;
            }
            const cls = String(issue.class || '').substring(0, 42);
            const sev = String(issue.severity || '');
            const cost = issue.cost_min + ' - ' + issue.cost_max + ' DZD';
            doc.text(cls, margin + 1, y);
            doc.text(sev, margin + 75, y);
            doc.text(cost, pageW - margin - 1, y, { align: 'right' });
            y += 7;
        });
    }

    y += 6;
    if (y > 270) {
        doc.addPage();
        y = margin;
    }
    doc.setFont('helvetica', 'bold');
    doc.text(
        'Total Estimated Repair: ' + data.costMin + ' - ' + data.costMax + ' DZD',
        pageW - margin,
        y,
        { align: 'right' }
    );
    y += 10;
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(8);
    doc.setTextColor(120);
    doc.text(
        'AutoDamg AI — Estimates are AI-driven; verify with a certified mechanic.',
        margin,
        y,
        { maxWidth: pageW - 2 * margin }
    );

    const safe = String(data.filename || 'report').replace(/[^\w.\-]+/g, '_');
    doc.save('AutoDamg_Report_' + safe + '.pdf');
}
