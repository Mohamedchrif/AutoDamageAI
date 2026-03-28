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
    if (tab !== 'camera' && stream) stopCamera();
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

// ── Camera ────────────────────────────────────────────────────
const video = document.getElementById('video-preview');
const canvas = document.getElementById('canvas-preview');
const startBtn = document.getElementById('start-camera-btn');
const captureBtn = document.getElementById('capture-btn');
const stopBtn = document.getElementById('stop-camera-btn');
const retakeBtn = document.getElementById('retake-btn');
const camAnalyzeBtn = document.getElementById('camera-analyze-btn');
const camErrorCard = document.getElementById('camera-error-card');

function showCamError(msg) { camErrorCard.style.display = 'block'; camErrorCard.textContent = msg; }
function hideCamError() { camErrorCard.style.display = 'none'; }

async function startCamera() {
    hideCamError();
    try {
        stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false });
        video.srcObject = stream;
        video.style.display = 'block';
        canvas.style.display = 'none';
        startBtn.style.display = 'none';
        stopBtn.style.display = '';
        captureBtn.disabled = false;
        camAnalyzeBtn.style.display = 'none';
        retakeBtn.style.display = 'none';
        capturedBlob = null;
    } catch (err) { showCamError('Camera access denied or not available: ' + err.message); }
}

function stopCamera() {
    if (stream) { stream.getTracks().forEach(t => t.stop()); stream = null; }
    video.srcObject = null;
    video.style.display = 'none';
    startBtn.style.display = '';
    stopBtn.style.display = 'none';
    captureBtn.disabled = true;
}

function capturePhoto() {
    if (!stream) return;
    const ctx = canvas.getContext('2d');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    ctx.drawImage(video, 0, 0);
    canvas.style.display = 'block';
    video.style.display = 'none';
    stopBtn.style.display = 'none';
    captureBtn.disabled = true;
    retakeBtn.style.display = '';
    camAnalyzeBtn.style.display = '';
    if (stream) { stream.getTracks().forEach(t => t.stop()); stream = null; }
    canvas.toBlob(blob => { capturedBlob = blob; }, 'image/jpeg', 0.92);
}

function retakePhoto() {
    capturedBlob = null;
    canvas.style.display = 'none';
    retakeBtn.style.display = 'none';
    camAnalyzeBtn.style.display = 'none';
    startCamera();
}

async function submitCapture() {
    if (!capturedBlob) { showCamError('No photo captured yet.'); return; }
    const formData = new FormData();
    formData.append('image', capturedBlob, 'camera_capture.jpg');
    await submitAnalysis(formData);
}

// ── Core: send image → backend (same file) → redirect ──────────
async function submitAnalysis(formData) {
    const overlay = document.getElementById('loading-overlay');
    overlay.style.display = 'flex';
    hideError();
    hideCamError();

    try {
        // POST to upload.php which encapsulates the backend Flask call
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

        // Redirect to result page with real analysis ID
        window.location.href = 'result.php?id=' + data.analysis_id;

    } catch (err) {
        overlay.style.display = 'none';
        const msg = err.message || 'Unexpected error. Please try again.';
        const activeTab = document.querySelector('.tab-content.active').id;
        if (activeTab === 'upload-tab') { showError(msg); }
        else { showCamError(msg); }
    }
}