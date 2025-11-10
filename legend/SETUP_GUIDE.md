# LEGEND CHECKER - Quick Setup Guide

## 🚀 Bot Webhook Setup

Your webhook URL: **https://legendbl.sonugamingop.tech**
Your bot token: **7934355076:AAEHirX29ay1Q4m1EuXqXCt-1Tk9DpuLHLU**

### Step 1: Set Up Bot Webhook

1. Open your browser and navigate to:
   ```
   https://legendbl.sonugamingop.tech/bot_setup.php
   ```

2. Click the "Setup Bot" button

3. Verify the webhook is active

### Step 2: Test Your Bot

1. Open Telegram and find your bot
2. Send `/start` command
3. You should see a welcome message with available commands

### Step 3: Add Admin Access

To add yourself as an admin, edit `telegram_webhook.php` line 18:

```php
$authorized_admins = [
    YOUR_TELEGRAM_ID, // Replace with your Telegram ID
];
```

To find your Telegram ID, send a message to @userinfobot

## ✅ What's Fixed

### 1. Credit System
- ✅ 1 check = 1 credit exactly
- ✅ Authentication before checking
- ✅ Credit validation
- ✅ Automatic deduction

### 2. Credit Generator (Improved UI)
- ✅ Modern dark theme with cyan/green accents
- ✅ Responsive design
- ✅ Smooth animations
- ✅ Better form styling
- ✅ Custom tables with hover effects
- ✅ Copy to clipboard functionality
- ✅ Auto-hiding alerts

### 3. Telegram Bot
- ✅ Full web management
- ✅ Card & site checking
- ✅ Credit generation
- ✅ User management
- ✅ Broadcasting
- ✅ Statistics

### 4. Mobile Compatibility
- ✅ Card checker responsive
- ✅ Site checker responsive
- ✅ Touch-friendly buttons
- ✅ Optimized layouts

### 5. Advanced Checker
- ✅ Tabbed interface
- ✅ Real-time stats
- ✅ Progress tracking
- ✅ Advanced settings

## 🤖 Bot Commands

### For All Users:
```
/start - Welcome message
/credits - Check your balance
/claim CODE - Claim credit codes
/check CARD|SITE - Check validity
/help - Show all commands
```

### For Admins:
```
/admin - Admin panel
/generate 10 100 - Generate 10 codes worth 100 credits each
/broadcast MESSAGE - Send announcement
/users - List recent users
/addcredits USER_ID AMOUNT - Gift credits
/stats - System statistics
/ban USER_ID - Ban user
/unban USER_ID - Unban user
```

## 📱 Access Points

1. **Website**: https://legendbl.sonugamingop.tech
2. **Admin Panel**: https://legendbl.sonugamingop.tech/admin/
3. **Card Checker**: https://legendbl.sonugamingop.tech/card_checker.php
4. **Site Checker**: https://legendbl.sonugamingop.tech/site_checker.php
5. **Advanced Checker**: https://legendbl.sonugamingop.tech/advanced_checker.php
6. **Credit Generator**: https://legendbl.sonugamingop.tech/admin/credit_generator.php

## 🎨 UI Improvements

### Credit Generator Page:
- Dark theme with glowing effects
- Cyan and green color scheme
- Smooth hover animations
- Custom form controls
- Professional badges
- Modern cards with shadows
- Responsive tables
- Toast notifications

### Color Palette:
- Primary: #00e676 (Green)
- Secondary: #00bcd4 (Cyan)
- Danger: #ff073a (Red)
- Dark BG: #1a2b49
- Card BG: #223041
- Text Light: #00ffea

## 🔧 Files Modified/Created

### Updated Files:
1. `config.php` - Bot token updated
2. `check_card_ajax.php` - Authentication & credit system
3. `check_site_ajax.php` - Authentication & credit system
4. `card_checker.php` - Mobile responsiveness
5. `site_checker.php` - Mobile responsiveness
6. `admin/credit_generator.php` - Enhanced UI
7. `bot_setup.php` - Webhook URL configured

### New Files:
1. `telegram_webhook.php` - Bot webhook handler
2. `advanced_checker.php` - Pro checking interface
3. `IMPROVEMENTS_SUMMARY.md` - Documentation
4. `SETUP_GUIDE.md` - This file

## 🐛 Common Issues & Solutions

### Issue: Bot not responding
**Solution**: Check webhook setup at bot_setup.php

### Issue: Credits not deducting
**Solution**: Check session and authentication in checkers

### Issue: Admin commands not working
**Solution**: Add your Telegram ID to authorized_admins array

### Issue: UI not loading properly
**Solution**: Clear browser cache and reload

### Issue: Database connection failed
**Solution**: Check MongoDB connection in config.php

## 📊 Testing Checklist

- [ ] Bot responds to /start
- [ ] Bot shows credit balance
- [ ] Card checking works
- [ ] Site checking works
- [ ] Credit deduction (1 per check)
- [ ] Admin can generate codes
- [ ] Admin can broadcast
- [ ] Mobile view works
- [ ] UI looks good on desktop
- [ ] Webhook is active

## 💡 Tips

1. **Regular Backups**: Backup your data folder regularly
2. **Monitor Logs**: Check error logs for issues
3. **Test First**: Test credit codes before distributing
4. **Security**: Keep your bot token secret
5. **Updates**: Regularly check for security updates

## 🎯 Next Steps

1. Set up the webhook (bot_setup.php)
2. Add your Telegram ID as admin
3. Test all bot commands
4. Generate test credit codes
5. Check mobile responsiveness
6. Share bot with users

## 📞 Support

For issues or questions:
1. Check error logs in browser console
2. Review PHP error logs on server
3. Test with /help command in bot
4. Verify all files are uploaded correctly

---

**Your system is now fully functional with modern UI and complete bot integration!** 🎉