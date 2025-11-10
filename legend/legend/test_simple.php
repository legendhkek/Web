<?php
// Simple test page without redirects
echo "<h1>Simple Test Page</h1>";
echo "<p>Current URL: " . $_SERVER['REQUEST_SCHEME'] . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . "</p>";
echo "<p>HTTPS Status: " . (isset($_SERVER['HTTPS']) ? $_SERVER['HTTPS'] : 'not set') . "</p>";
echo "<p>X-Forwarded-Proto: " . (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) ? $_SERVER['HTTP_X_FORWARDED_PROTO'] : 'not set') . "</p>";

// Simple Telegram widget test
?>
<div>
<script async src="https://telegram.org/js/telegram-widget.js?22" 
        data-telegram-login="WebkeBot" 
        data-size="large" 
        data-auth-url="<?php echo $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/web/login.php'; ?>" 
        data-request-access="write">
</script>
</div>
