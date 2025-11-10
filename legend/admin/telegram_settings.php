<?php
require_once 'admin_header.php';

// Only owner should modify bot token/name
if (!isOwner()) {
    echo '<div class="alert alert-danger">Only the OWNER can modify Telegram settings.</div>';
    require_once 'admin_footer.php';
    exit;
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $botName = trim($_POST['bot_name_override'] ?? '');
    $botToken = trim($_POST['telegram_bot_token'] ?? '');
    $debugAuth = isset($_POST['debug_auth']) ? (bool)$_POST['debug_auth'] : false;
    $allowInsecure = isset($_POST['allow_insecure_telegram_auth']) ? (bool)$_POST['allow_insecure_telegram_auth'] : false;

    $toSave = [
        'bot_name_override' => $botName,
        'debug_auth' => $debugAuth,
        'allow_insecure_telegram_auth' => $allowInsecure,
    ];

    if ($botToken !== '') {
        $toSave['telegram_bot_token'] = $botToken;
    }

    if (SiteConfig::save($toSave) !== false) {
        $success = 'Telegram settings updated.';
    } else {
        $errors[] = 'Failed to save configuration.';
    }
}

$botNameCurrent = SiteConfig::get('bot_name_override', TelegramConfig::BOT_NAME);
$botTokenSet = SiteConfig::get('telegram_bot_token') ? 'Yes (stored)' : (getenv('TELEGRAM_BOT_TOKEN') ? 'Yes (env var)' : 'No');
$debugAuth = (bool) SiteConfig::get('debug_auth', false);
$allowInsecure = (bool) SiteConfig::get('allow_insecure_telegram_auth', false);

// Fetch getMe to assist operators
$apiInfo = '';
try {
    $token = SiteConfig::get('telegram_bot_token', TelegramConfig::BOT_TOKEN);
    $url = "https://api.telegram.org/bot{$token}/getMe";
    $ctx = stream_context_create([ 'http' => [ 'timeout' => 5 ] ]);
    $resp = @file_get_contents($url, false, $ctx);
    if ($resp) {
        $data = json_decode($resp, true);
        if ($data && !empty($data['ok'])) {
            $uname = $data['result']['username'] ?? 'unknown';
            $apiInfo = 'Token resolves to bot username: @' . htmlspecialchars($uname);
        } else {
            $apiInfo = 'Failed to resolve token via getMe.';
        }
    } else {
        $apiInfo = 'Network error calling getMe.';
    }
} catch (Throwable $e) {
    $apiInfo = 'Error: ' . $e->getMessage();
}
?>

<div class="container-fluid">
  <div class="row">
    <div class="col-12">
      <div class="card">
        <div class="card-header">
          <h5 class="card-title">Telegram Settings</h5>
          <p class="card-subtitle text-muted">Align BOT_NAME and BOT_TOKEN to fix authentication.</p>
        </div>
        <div class="card-body">
          <?php if (!empty($success)): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
          <?php foreach ($errors as $err): ?><div class="alert alert-danger"><?php echo $err; ?></div><?php endforeach; ?>

          <div class="mb-3 p-3 border rounded bg-light">
            <div><strong>Current Widget Bot Name:</strong> @<?php echo htmlspecialchars($botNameCurrent); ?></div>
            <div><strong>Bot Token Set:</strong> <?php echo $botTokenSet; ?></div>
            <div><strong>getMe Result:</strong> <?php echo $apiInfo; ?></div>
            <div class="small text-muted">The widget uses the bot name. The Telegram signature uses the token. They must belong to the same bot.</div>
          </div>

          <form method="POST">
            <div class="mb-3">
              <label class="form-label">Bot Name Override (without @)</label>
              <input type="text" name="bot_name_override" class="form-control" value="<?php echo htmlspecialchars($botNameCurrent); ?>" placeholder="e.g., MyBot" />
            </div>
            <div class="mb-3">
              <label class="form-label">Bot Token</label>
              <input type="password" name="telegram_bot_token" class="form-control" placeholder="123456:ABCDEF... (leave blank to keep current)" />
              <small class="text-muted">Stored in data/system_config.json. Alternatively, set TELEGRAM_BOT_TOKEN environment variable.</small>
            </div>
            <div class="form-check form-switch mb-2">
              <input class="form-check-input" type="checkbox" id="debug_auth" name="debug_auth" <?php echo $debugAuth ? 'checked' : ''; ?>>
              <label class="form-check-label" for="debug_auth">Enable debug_auth (shows diagnosis on login page)</label>
            </div>
            <div class="form-check form-switch mb-3">
              <input class="form-check-input" type="checkbox" id="allow_insecure_telegram_auth" name="allow_insecure_telegram_auth" <?php echo $allowInsecure ? 'checked' : ''; ?>>
              <label class="form-check-label" for="allow_insecure_telegram_auth">Allow insecure auth (DEV ONLY)</label>
            </div>
            <button type="submit" class="btn btn-primary">Save Settings</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once 'admin_footer.php'; ?>
