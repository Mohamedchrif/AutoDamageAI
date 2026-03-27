<?php
// Buffer all output so PHP warnings don't corrupt the JSON response
ob_start();

require_once 'config.php';

// Clean any output from config.php (DB warnings etc.)
ob_clean();

// Set JSON response header
header('Content-Type: application/json');

// ─── Read JSON Body ────────────────────────────────────────────────────────────
$rawBody = file_get_contents('php://input');
$payload = json_decode($rawBody, true);

if (!$payload || empty($payload['image_base64'])) {
    echo json_encode(["error" => "No image data received. Expected JSON with 'image_base64' field."]);
    exit;
}

$base64Data = $payload['image_base64'];
$fileName   = isset($payload['filename']) ? basename($payload['filename']) : 'upload.jpg';

// ─── Decode Base64 → Binary ───────────────────────────────────────────────────
// Strip the data URI prefix:  data:image/jpeg;base64,<data>
if (strpos($base64Data, ';base64,') !== false) {
    [, $base64Data] = explode(';base64,', $base64Data);
}

$imageData = base64_decode($base64Data, true);
if ($imageData === false) {
    echo json_encode(["error" => "Invalid base64 image data."]);
    exit;
}

// ─── Validate It Is Really an Image ───────────────────────────────────────────
$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->buffer($imageData);

if (!$mimeType || strpos($mimeType, 'image/') !== 0) {
    echo json_encode(["error" => "Uploaded data is not a valid image."]);
    exit;
}

// ─── Write to a Temp File (for cURL to Flask) ─────────────────────────────────
$tmpPath = tempnam(sys_get_temp_dir(), 'autodamg_');
file_put_contents($tmpPath, $imageData);

// ─── Send to Flask AI API ──────────────────────────────────────────────────────
$flaskApiUrl = 'http://127.0.0.1:5000/predict';
$cFile       = new CURLFile($tmpPath, $mimeType, $fileName);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $flaskApiUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, ['image' => $cFile]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);

$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// Remove the temporary file
@unlink($tmpPath);

if ($response === false || $httpCode !== 200) {
    $friendlyError = "Our AI engines are currently offline or unreachable. Please try again later.";
    echo json_encode(["error" => $friendlyError, "details" => $curlError, "http_code" => $httpCode]);
    exit;
}

// ─── Validate Flask Response ───────────────────────────────────────────────────
$resultData = json_decode($response, true);

if (!$resultData || !isset($resultData['success'])) {
    echo json_encode(["error" => "Invalid response from AI service.", "raw" => substr($response, 0, 300)]);
    exit;
}

// ─── Save Original Image for Before & After Slider ────────────────────────────
$uploadDir = 'assets/uploads/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$originalPath = $uploadDir . 'orig_' . time() . '_' . rand(1000, 9999) . '.jpg';
file_put_contents($originalPath, $imageData);
$resultData['original_image'] = $originalPath;
$jsonToDB = json_encode($resultData);

// ─── Save to Database ──────────────────────────────────────────────────────────
$userId = $_SESSION['user_id'] ?? null;

try {
    $stmt = $pdo->prepare("INSERT INTO analyses (user_id, filename, result_json) VALUES (?, ?, ?)");
    $stmt->execute([$userId, $fileName, $jsonToDB]);
    $analysisId = $pdo->lastInsertId();
    $resultData['redirect'] = "result.php?id=" . $analysisId;
    echo json_encode($resultData);
} catch (Exception $e) {
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}
?>
