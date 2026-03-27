// ─── Tab Switching ─────────────────────────────────────────
function switchTab(tab, btn) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById(tab + '-tab').classList.add('active');
    if (tab !== 'camera' && videoStream) stopCamera();
}

// ─── File Handling ─────────────────────────────────────────
const dropZone  = document.getElementById('drop-zone');
const fileInput = document.getElementById('file-input');
const filePreview = document.getElementById('file-preview');
const analyzeBtn  = document.getElementById('analyze-btn');

['dragenter', 'dragover', 'dragleave', 'drop'].forEach(e => {
    dropZone.addEventListener(e, ev => { ev.preventDefault(); ev.stopPropagation(); });
});
['dragenter', 'dragover'].forEach(e => dropZone.addEventListener(e, () => dropZone.classList.add('drag-over')));
['dragleave',  'drop'   ].forEach(e => dropZone.addEventListener(e, () => dropZone.classList.remove('drag-over')));
dropZone.addEventListener('drop', e => { fileInput.files = e.dataTransfer.files; handleFile(e.dataTransfer.files[0]); });
fileInput.addEventListener('change', () => handleFile(fileInput.files[0]));

function handleFile(file) {
    if (!file) return;
    document.getElementById('preview-name').textContent = file.name;
    document.getElementById('preview-meta').textContent =
        `${(file.size / 1024 / 1024).toFixed(2)} MB • ${file.type || 'image'}`;
    filePreview.classList.add('visible');
    analyzeBtn.disabled = false;
    hideError();
}

function clearFile(e) {
    e.preventDefault();
    fileInput.value = '';
    filePreview.classList.remove('visible');
    analyzeBtn.disabled = true;
}

// ─── Convert File → Base64 ─────────────────────────────────
function fileToBase64(file) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload  = e => resolve(e.target.result); // full data URI (e.g. data:image/jpeg;base64,...)
        reader.onerror = () => reject(new Error('Failed to read file'));
        reader.readAsDataURL(file);
    });
}

// Convert Blob → Base64 (used for camera captures)
function blobToBase64(blob) {
    return fileToBase64(blob); // FileReader works for Blobs too
}

// ─── Upload & Analysis (Base64) ────────────────────────────
document.getElementById('upload-form').addEventListener('submit', async function (e) {
    e.preventDefault();
    const file = fileInput.files && fileInput.files[0];
    if (!file) { showError('Please select an image file first.'); return; }

    try {
        const base64 = await fileToBase64(file);
        await runAnalysis(base64, file.name);
    } catch (err) {
        showError('Could not read image: ' + err.message);
    }
});

/**
 * Send the image as a base64 string (JSON body) to upload.php
 * @param {string} base64DataUrl  - full data URI  e.g. "data:image/jpeg;base64,..."
 * @param {string} fileName       - original file name (for DB record)
 */
async function runAnalysis(base64DataUrl, fileName) {
    document.getElementById('loading-overlay').classList.add('active');
    hideError();
    try {
        const response = await fetch('upload.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ image_base64: base64DataUrl, filename: fileName }),
        });
        const data = await response.json();
        document.getElementById('loading-overlay').classList.remove('active');

        if (!response.ok) { showError(data.error || 'Analysis failed. Please try again.'); return; }
        if (data.error)            { showError(data.error); }
        else if (data.redirect)    { window.location.href = data.redirect; }
        else { showError('Unexpected response from server. Please try again.'); }
    } catch (err) {
        document.getElementById('loading-overlay').classList.remove('active');
        showError('Network error: ' + err.message);
    }
}

function showError(msg) {
    const card = document.getElementById('error-card');
    document.getElementById('error-message').innerHTML = `<strong>${msg}</strong>`;
    card.style.display = 'block';
}
function hideError() { document.getElementById('error-card').style.display = 'none'; }

// ─── Camera ────────────────────────────────────────────────
let videoStream  = null;
let capturedBlob = null;
const video  = document.getElementById('video-preview');
const canvas = document.getElementById('canvas-preview');

async function startCamera() {
    try {
        videoStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
        video.srcObject = videoStream;
        document.getElementById('start-camera-btn').style.display = 'none';
        document.getElementById('capture-btn').disabled = false;
        document.getElementById('stop-camera-btn').style.display = 'flex';
    } catch (err) {
        const ec = document.getElementById('camera-error-card');
        ec.style.display = 'block';
        ec.textContent = 'Camera error: ' + err.message;
    }
}

function capturePhoto() {
    canvas.width  = video.videoWidth;
    canvas.height = video.videoHeight;
    canvas.getContext('2d').drawImage(video, 0, 0);
    canvas.toBlob(blob => {
        capturedBlob = blob;
        video.style.display   = 'none';
        canvas.style.display  = 'block';
        document.getElementById('capture-btn').style.display       = 'none';
        document.getElementById('retake-btn').style.display        = 'flex';
        document.getElementById('camera-analyze-btn').style.display = 'flex';
    }, 'image/jpeg', 0.92);
}

function retakePhoto() {
    video.style.display  = 'block';
    canvas.style.display = 'none';
    document.getElementById('capture-btn').style.display        = 'flex';
    document.getElementById('retake-btn').style.display         = 'none';
    document.getElementById('camera-analyze-btn').style.display = 'none';
    capturedBlob = null;
}

function stopCamera() {
    if (videoStream) { videoStream.getTracks().forEach(t => t.stop()); videoStream = null; }
    video.srcObject = null;
    document.getElementById('start-camera-btn').style.display = 'flex';
    document.getElementById('capture-btn').disabled = true;
    document.getElementById('stop-camera-btn').style.display  = 'none';
}

// Camera capture → convert blob to base64 → send
async function submitCapture() {
    if (!capturedBlob) return;
    try {
        const base64 = await blobToBase64(capturedBlob);
        await runAnalysis(base64, 'camera-capture.jpg');
    } catch (err) {
        showError('Could not process captured photo: ' + err.message);
    }
}

window.addEventListener('beforeunload', () => {
    if (videoStream) videoStream.getTracks().forEach(t => t.stop());
});
