<?php
// Buffer all output so PHP warnings don't corrupt the JSON response
ob_start();

require_once 'config.php';

// Clean any output from config.php (DB warnings etc.)
ob_clean();

// Now set the correct JSON header
header('Content-Type: application/json');

// ─── Validate File ────────────────────────────────────────────
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(["error" => "No valid image uploaded."]);
    exit;
}

$fileTmpPath = $_FILES['image']['tmp_name'];
$fileName    = $_FILES['image']['name'];
$fileType    = mime_content_type($fileTmpPath);

if (strpos($fileType, 'image/') !== 0) {
    echo json_encode(["error" => "Uploaded file is not an image."]);
    exit;
}

// ─── Send to Flask AI API ─────────────────────────────────────
$flaskApiUrl = 'http://127.0.0.1:5000/predict';
$cFile       = new CURLFile($fileTmpPath, $fileType, $fileName);

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

if ($response === false || $httpCode !== 200) {
    $friendlyError = "Our AI engines are currently offline or unreachable. Please try again later.";
    echo json_encode(["error" => $friendlyError, "details" => $curlError, "http_code" => $httpCode]);
    exit;
}

// ─── Validate Flask Response ──────────────────────────────────
$resultData = json_decode($response, true);

if (!$resultData || !isset($resultData['success'])) {
    echo json_encode(["error" => "Invalid response from AI service.", "raw" => substr($response, 0, 300)]);
    exit;
}

// ─── Save Original Image for Before & After Slider ─────────────
$uploadDir = 'assets/uploads/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
// Safely copy temporary file
$originalPath = $uploadDir . 'orig_' . time() . '_' . rand(1000, 9999) . '.jpg';
copy($fileTmpPath, $originalPath);
$resultData['original_image'] = $originalPath;
$jsonToDB = json_encode($resultData);

// ─── Save to Database ─────────────────────────────────────────
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
