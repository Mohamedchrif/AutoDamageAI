// ── State ────────────────────────────────────────────────────
let selectedFile  = null;
let capturedBlob  = null;
let stream        = null;

// ── Tab switching ─────────────────────────────────────────────
function switchTab(tab, btn) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById(tab + '-tab').classList.add('active');
}

// ── File Upload helpers ───────────────────────────────────────
const dropZone   = document.getElementById('drop-zone');
const fileInput  = document.getElementById('file-input');
const filePreview = document.getElementById('file-preview');
const analyzeBtn = document.getElementById('analyze-btn');
const errorCard  = document.getElementById('error-card');
const errorMsg   = document.getElementById('error-message');

function showError(msg) { errorCard.style.display = 'flex'; errorMsg.textContent = msg; }
function hideError() { errorCard.style.display = 'none'; errorMsg.textContent = ''; }

function showPreview(file) {
    selectedFile = file;
    document.getElementById('preview-name').textContent = file.name;
    const sizeKB = (file.size / 1024).toFixed(1);
    document.getElementById('preview-meta').textContent = `${file.type || 'image'} · ${sizeKB} KB`;
    filePreview.style.display = 'flex';
    dropZone.style.display = 'none';
    analyzeBtn.disabled = false;
    hideError();
}

function clearFile(e) {
    if (e) e.preventDefault();
    selectedFile = null;
    fileInput.value = '';
    filePreview.style.display = 'none';
    dropZone.style.display = '';
    analyzeBtn.disabled = true;
    hideError();
}

fileInput.addEventListener('change', () => {
    if (fileInput.files.length) showPreview(fileInput.files[0]);
});

dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('drag-over'); });
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('drag-over'));
dropZone.addEventListener('drop', e => {
    e.preventDefault();
    dropZone.classList.remove('drag-over');
    const file = e.dataTransfer.files[0];
    if (file && file.type.startsWith('image/')) { showPreview(file); }
    else { showError('Please drop a valid image file (JPG, PNG, WEBP).'); }
});

// ── Upload Form Submit ────────────────────────────────────────
document.getElementById('upload-form').addEventListener('submit', async e => {
    e.preventDefault();
    if (!selectedFile) return;
    if (selectedFile.size > 16 * 1024 * 1024) {
        showError('File is too large. Maximum size is 16 MB.');
        return;
    }
    const formData = new FormData();
    formData.append('image', selectedFile, selectedFile.name);
    await submitAnalysis(formData);
});

// ── Camera (Native Input) ───────────────────────────────────────────────────
const nativeCamInput = document.getElementById('native-camera-input');
const nativeCamImg = document.getElementById('native-camera-img');
const nativeCamPreview = document.getElementById('native-camera-preview');
const nativeCamPlaceholder = document.getElementById('native-camera-placeholder');
const camAnalyzeBtn = document.getElementById('camera-analyze-btn');
const camErrorCard = document.getElementById('camera-error-card');
let nativeCapturedFile = null;

function showCamError(msg) { camErrorCard.style.display = 'block'; camErrorCard.textContent = msg; }
function hideCamError() { camErrorCard.style.display = 'none'; }

if (nativeCamInput) {
    nativeCamInput.addEventListener('change', (e) => {
        hideCamError();
        const file = e.target.files[0];
        if (file) {
            nativeCapturedFile = file;
            nativeCamImg.src = URL.createObjectURL(file);
            nativeCamPlaceholder.style.display = 'none';
            nativeCamPreview.style.display = 'flex';
            camAnalyzeBtn.style.display = 'inline-flex';
        }
    });
}

window.clearNativeCamera = function(e) {
    if (e) e.preventDefault();
    nativeCapturedFile = null;
    if (nativeCamInput) nativeCamInput.value = '';
    nativeCamPreview.style.display = 'none';
    nativeCamPlaceholder.style.display = 'block';
    camAnalyzeBtn.style.display = 'none';
    hideCamError();
}

window.submitNativeCapture = async function() {
    if (!nativeCapturedFile) {
        showCamError('Capture a photo first.');
        return;
    }
    const formData = new FormData();
    formData.append('image', nativeCapturedFile, nativeCapturedFile.name || 'camera_capture.jpg');
    await submitAnalysis(formData);
}

// ── Core: send image → backend (same file) → redirect ──────────
async function submitAnalysis(formData) {
    const overlay = document.getElementById('loading-overlay');
    overlay.style.display = 'flex';
    hideError();
    hideCamError();

    try {
        const response = await fetch('upload.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData,
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            if (response.status === 401 && data.redirect) {
                window.location.href = data.redirect;
                return;
            }
            throw new Error(data.error || 'Analysis failed. Please try again.');
        }

        window.location.href = 'result.php?id=' + data.analysis_id;

    } catch (err) {
        overlay.style.display = 'none';
        const msg = err.message || 'Unexpected error. Please try again.';
        const activeTab = document.querySelector('.tab-content.active').id;
        if (activeTab === 'upload-tab') { showError(msg); }
        else { showCamError(msg); }
    }
}
