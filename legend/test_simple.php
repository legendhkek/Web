<?php
// Simple test page without redirects
echo "<h1>Simple Test Page</h1>";
echo "<p>Current URL: " . htmlspecialchars(($_SERVER['REQUEST_SCHEME'] ?? 'http') . "://" . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? ''), ENT_QUOTES, 'UTF-8') . "</p>";
echo "<p>HTTPS Status: " . htmlspecialchars(isset($_SERVER['HTTPS']) ? $_SERVER['HTTPS'] : 'not set', ENT_QUOTES, 'UTF-8') . "</p>";
echo "<p>X-Forwarded-Proto: " . htmlspecialchars(isset($_SERVER['HTTP_X_FORWARDED_PROTO']) ? $_SERVER['HTTP_X_FORWARDED_PROTO'] : 'not set', ENT_QUOTES, 'UTF-8') . "</p>";

// Simple Telegram widget test
?>
<div>
<script async src="https://telegram.org/js/telegram-widget.js?22" 
        data-telegram-login="WebkeBot" 
        data-size="large" 
        data-auth-url="<?php echo htmlspecialchars(($_SERVER['REQUEST_SCHEME'] ?? 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . '/web/login.php', ENT_QUOTES, 'UTF-8'); ?>" 
        data-request-access="write">
</script>
</div>
