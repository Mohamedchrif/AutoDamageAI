<?php
// Quick test to verify file_put_contents works in assets/profiles/
echo "<h2>Profile Upload Test</h2>";

// Test 1: Check __DIR__
echo "<p><b>__DIR__:</b> " . __DIR__ . "</p>";

// Test 2: Check if assets/profiles/ exists
$uploadDir = __DIR__ . '/assets/profiles/';
echo "<p><b>Upload Dir:</b> " . $uploadDir . "</p>";
echo "<p><b>Dir Exists:</b> " . (is_dir($uploadDir) ? 'YES' : 'NO') . "</p>";

// Test 3: Check if writable
echo "<p><b>Is Writable:</b> " . (is_writable($uploadDir) ? 'YES' : 'NO') . "</p>";

// Test 4: Try writing a test file
$testFile = $uploadDir . 'test_' . time() . '.txt';
$result = file_put_contents($testFile, 'Hello, this is a test!');
echo "<p><b>Write Test:</b> " . ($result !== false ? "SUCCESS ($result bytes written to $testFile)" : "FAILED") . "</p>";

if ($result !== false) {
    echo "<p><b>File Exists After Write:</b> " . (file_exists($testFile) ? 'YES' : 'NO') . "</p>";
    // Clean up
    unlink($testFile);
    echo "<p><i>Test file cleaned up.</i></p>";
}

// Test 5: Check DB for profile_picture column
require_once 'config.php';
try {
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll();
    echo "<h3>Users Table Columns:</h3><ul>";
    foreach ($columns as $col) {
        $highlight = ($col['Field'] === 'profile_picture') ? ' style="color:green;font-weight:bold"' : '';
        echo "<li$highlight>{$col['Field']} ({$col['Type']})</li>";
    }
    echo "</ul>";
} catch (Exception $e) {
    echo "<p style='color:red'>DB Error: " . $e->getMessage() . "</p>";
}

// Test 6: Check current user's profile_picture value
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT id, username, profile_picture FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    echo "<h3>Current User:</h3><pre>" . print_r($user, true) . "</pre>";
} else {
    // Show all users
    $stmt = $pdo->query("SELECT id, username, profile_picture FROM users LIMIT 5");
    $users = $stmt->fetchAll();
    echo "<h3>Users in DB:</h3><pre>" . print_r($users, true) . "</pre>";
}
?>
