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

// Convert original to Base64 for the frontend slider so we don't save to disk
$originalBase64 = 'data:' . $mimeType . ';base64,' . base64_encode(file_get_contents($tmpPath));

// ── Call Flask AI Server ────────────────────────────────────
$FLASK_URL = 'http://127.0.0.1:5000/predict';
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
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
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

// Build result JSON for the old frontend logic which expected things inside result_json
$resultJson = json_encode([
    'is_undamaged' => $isUndamaged,
    'total_detections' => $totalDetections,
    'detected_issues' => $detectedIssues,
    'cost_min' => $costMin,
    'cost_max' => $costMax,
    'original_image' => $originalBase64
]);

// ── Save to Database using the new schema ───────────────────
try {
    $ext = pathinfo($originalName, PATHINFO_EXTENSION) ?: 'jpg';
    $storedFilename = 'analysis_' . $_SESSION['user_id'] . '_' . time() . '.' . $ext;
    
    $stmt = $pdo->prepare("
        INSERT INTO analyses 
            (user_id, filename, original_filename, file_size, result_json, annotated_image, cost_min, cost_max, total_detections, is_undamaged) 
        VALUES 
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $_SESSION['user_id'],
        $storedFilename,
        $originalName,
        $fileSize,
        $resultJson,
        $annotatedImage,
        $costMin,
        $costMax,
        $totalDetections,
        (int)$isUndamaged
    ]);
    
    echo json_encode([
        'success' => true,
        'analysis_id' => $pdo->lastInsertId()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>
