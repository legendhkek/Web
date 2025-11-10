# 🎯 LEGEND CHECKER - Quick Reference

## Owner Info
- **ID**: 5652614329
- **Username**: @LEGEND_BL
- **Database**: mongodb+srv://sarthakgrid_db_user:pwAyjsdl9FPsBSUS@legend.0rrvdmy.mongodb.net/?appName=legend

## 🤖 Owner Bot Commands
```
/addadmin <user_id>        - Add new admin
/removeadmin <user_id>     - Remove admin
/admins                    - List all admins
/cclogs [limit]            - View charged CC logs
/getlogs [status]          - View all logs
/systemstats               - System statistics
```

## 💳 Credit Types (7 Types)
```
standard  - Regular credits (most common)
bonus     - Special rewards
premium   - High value credits
vip       - Exclusive access
trial     - Limited time (7-14 days)
event     - Special occasions
bulk      - Mass distribution (giveaways)
```

## 🎁 Bulk Generation
- **Max Codes**: 500 per batch
- **Max Credits**: 10,000 per code
- **Expiry Range**: 1-365 days
- **Location**: admin/credit_generator.php

## 📊 CC Logs
- **Storage**: Unencrypted (full data)
- **Includes**: Card number, CVV, expiry, status, amount
- **Access**: Owner only via bot
- **Collection**: cc_logs in MongoDB

## 🔧 Quick Setup
1. Upload files
2. Visit bot_setup.php
3. Set webhook
4. Test with /start
5. Run system_check.php

## 📂 New Files
- admin_manager.php
- cc_logs_manager.php
- telegram_webhook_enhanced.php
- system_check.php
- UPDATE_SUMMARY.md

## ✅ Completed Features
✓ MongoDB updated
✓ Owner control system
✓ Dynamic admin management
✓ Real CC logs storage
✓ Bulk credit generation (500 codes)
✓ 7 credit types
✓ Enhanced bot commands
✓ UI improvements

## 🚨 Important URLs
- Webhook: https://legendbl.sonugamingop.tech/telegram_webhook.php
- Bot Setup: https://legendbl.sonugamingop.tech/bot_setup.php
- System Check: https://legendbl.sonugamingop.tech/system_check.php
- Dashboard: https://legendbl.sonugamingop.tech/dashboard.php
- Credit Gen: https://legendbl.sonugamingop.tech/admin/credit_generator.php

## 💡 Quick Tips
- Use `/cclogs 50` to see last 50 charged cards
- Generate bulk codes for giveaways
- Check `/systemstats` daily
- Monitor `/admins` list regularly
- Use trial type for new users
- Use bulk type for mass distribution

---
**All systems ready!** 🚀
Owner: @LEGEND_BL (5652614329)
