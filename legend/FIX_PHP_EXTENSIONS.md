# CRITICAL: PHP Extensions Not Enabled

## Problem
Your bot and all API features are NOT working because:
- ❌ **cURL extension is DISABLED**
- ❌ **OpenSSL extension is DISABLED**

## These extensions are required for:
- Telegram Bot API communication
- Card checking API requests
- Site checker functionality  
- All HTTPS requests
- Webhook receiving

## Solution: Enable PHP Extensions

### Step 1: Find your php.ini file
Run this command to find the location:
```powershell
php --ini
```

### Step 2: Edit php.ini
Open the php.ini file in a text editor (as Administrator)

### Step 3: Enable Extensions
Find and uncomment (remove the semicolon `;` from the start):

```ini
;extension=curl
;extension=openssl
```

Change to:
```ini
extension=curl
extension=openssl
```

Also ensure these are uncommented:
```ini
extension=mbstring
extension=fileinfo
extension=mongodb
```

### Step 4: Restart Web Server
- If using Apache: Restart Apache service
- If using IIS: Restart IIS
- If using PHP built-in server: Stop and restart it

### Step 5: Verify
Run this command:
```powershell
php -r "echo 'cURL: ' . (extension_loaded('curl') ? 'Enabled' : 'Disabled') . PHP_EOL;"
```

Should output: **cURL: Enabled**

## Quick Alternative (if you have composer)
Install PHP with curl pre-enabled or use XAMPP/WAMP which includes all extensions.

## After Enabling Extensions:
1. Visit: `http://your-domain.com/test_bot.php`
2. This will test the bot and set the webhook
3. Open Telegram and send `/start` to @WebkeBot
4. Bot should respond immediately

---

**IMPORTANT**: Without these extensions, your entire application will NOT work!
