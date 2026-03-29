<?php
// Buffer output to prevent JSON corruption
ob_start();
require_once 'config.php';
ob_clean();

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required.', 'redirect' => 'login.php']);
    exit;
}

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No image uploaded.']);
    exit;
}

$file = $_FILES['image'];
$originalName = basename($file['name']);
$tmpPath = $file['tmp_name'];
$fileSize = $file['size'];

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($tmpPath);
$allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
if (!in_array($mimeType, $allowed)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Invalid file type. Allowed: JPG, PNG, WEBP, GIF']);
    exit;
}

if ($fileSize > 16 * 1024 * 1024) {
    http_response_code(413);
    echo json_encode(['success' => false, 'error' => 'File too large. Maximum size is 16 MB']);
    exit;
}

$ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION) ?: 'jpg');
if (strlen($ext) > 10) {
    $ext = 'jpg';
}
$storedFilename = 'analysis_' . $_SESSION['user_id'] . '_' . time() . '.' . $ext;

// ── Call Flask AI Server ────────────────────────────────────
$FLASK_URL = autodamg_flask_predict_url();
$curlFile = new CURLFile($tmpPath, $mimeType, $originalName);
$ch = curl_init($FLASK_URL);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => ['image' => $curlFile],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 120,
    CURLOPT_CONNECTTIMEOUT => 10,
]);

$rawResponse = curl_exec($ch);
$curlError = curl_error($ch);
curl_close($ch);

if ($rawResponse === false || $curlError) {
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'Could not reach AI server: ' . $curlError]);
    exit;
}

$flaskData = json_decode($rawResponse, true);

if (json_last_error() !== JSON_ERROR_NONE || empty($flaskData['success'])) {
    $flaskError = $flaskData['error'] ?? 'Unknown AI error';
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'AI analysis failed: ' . $flaskError]);
    exit;
}

// ── Map Flask Data ──────────────────────────────────────────
$isUndamaged = (bool)($flaskData['is_undamaged'] ?? true);
$totalDetections = (int)($flaskData['total_detections'] ?? 0);
$costMin = (float)($flaskData['cost_min'] ?? 0);
$costMax = (float)($flaskData['cost_max'] ?? 0);
$detectedIssues = $flaskData['detected_issues'] ?? [];
$annotatedImage = $flaskData['annotated_image'] ?? '';
$originalDimensions = $flaskData['original_dimensions'] ?? null;

// ── Store images in DB only (data URIs), not on disk ───────────────────────
// Large payloads need adequate MySQL max_allowed_packet (e.g. 64M+) and PHP memory_limit.
if (!is_uploaded_file($tmpPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Invalid upload.']);
    exit;
}
$fileBinary = file_get_contents($tmpPath);
if ($fileBinary === false || $fileBinary === '') {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not read uploaded image.']);
    exit;
}
$originalDataUri = 'data:' . $mimeType . ';base64,' . base64_encode($fileBinary);
unset($fileBinary);

$annotatedStored = '';
if ($annotatedImage !== '' && strpos($annotatedImage, 'data:') === 0) {
    $annotatedStored = $annotatedImage;
}

// Build result JSON for the frontend (original + annotated as data URIs in DB)
$resultJson = json_encode([
    'is_undamaged' => $isUndamaged,
    'total_detections' => $totalDetections,
    'detected_issues' => $detectedIssues,
    'cost_min' => $costMin,
    'cost_max' => $costMax,
    'original_image' => $originalDataUri,
    'annotated_image' => $annotatedStored,
    'original_dimensions' => $originalDimensions,
]);

if ($resultJson === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not encode analysis result.']);
    exit;
}

// ── Save to Database ────────────────────────────────────────
try {
    $stmt = $pdo->prepare("
        INSERT INTO analyses 
            (user_id, filename, original_filename, file_size, result_json, annotated_image, cost_min, cost_max, total_detections, is_undamaged, timestamp) 
        VALUES 
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $_SESSION['user_id'],          // user_id
        $storedFilename,               // filename
        $originalName,                 // original_filename
        $fileSize,                     // file_size
        $resultJson,                   // result_json
        $annotatedStored,              // annotated_image
        $costMin,                      // cost_min
        $costMax,                      // cost_max
        $totalDetections,              // total_detections
        (int)$isUndamaged,             // is_undamaged
        date('Y-m-d H:i:s')            // timestamp (added here)
    ]);

    echo json_encode([
        'success' => true,
        'analysis_id' => $pdo->lastInsertId(),
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
