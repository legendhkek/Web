@echo off
echo Testing Telegram API with curl from Windows...

REM Test bot info first
echo Testing bot validity...
curl -s "https://api.telegram.org/bot7564865488:AAEH8iY_tx__5ksx78nq4qhehgx7rc1-Bzo/getMe"
echo.

echo.
echo Testing message send...
curl -s -X POST "https://api.telegram.org/bot7564865488:AAEH8iY_tx__5ksx78nq4qhehgx7rc1-Bzo/sendMessage" ^
  -H "Content-Type: application/x-www-form-urlencoded" ^
  -d "chat_id=6658831303&text=🧪 Test from Windows curl&parse_mode=HTML"
echo.

pause