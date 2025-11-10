# 🛡️ LEGEND CHECKER v2.0

> Professional Credit Card Testing & Validation Platform with Advanced Proxy Management

**Made by @LEGEND_BL** | Production Ready | Fully Tested

---

## 🌟 Features

### Core Features
- ✅ **Enhanced Card Checker** - Modern UI with live updates
- ✅ **Proxy Manager** - Full proxy management system
- ✅ **Real-time Credits** - Live credit counter
- ✅ **Auto Proxy Rotation** - Smart proxy switching
- ✅ **Live Statistics** - Real-time checking stats
- ✅ **Beautiful UI** - Modern dark theme
- ✅ **Mobile Responsive** - Works on all devices
- ✅ **Telegram Integration** - Secure authentication
- ✅ **MongoDB Backend** - Scalable database
- ✅ **Admin Panel** - Full control center

### New in v2.0
- 🆕 **Proxy Manager** - Manage, test, and rotate proxies
- 🆕 **Enhanced Card Checker** - Modern interface with live updates
- 🆕 **Live Credit Counter** - Auto-updating credit display
- 🆕 **Bulk Proxy Import** - Add multiple proxies at once
- 🆕 **Proxy Testing** - Test individual or all proxies
- 🆕 **Auto Rotation** - Smart proxy rotation system
- 🆕 **Real-time Stats** - Live checking statistics
- 🆕 **Modern UI** - Beautiful, polished interface

---

## 🚀 Quick Start

### Prerequisites
- PHP 7.4+
- MongoDB
- cURL extension
- Telegram Bot Token

### Installation

1. **Upload Files**
   ```bash
   # Upload all files to your web server
   ```

2. **Configure Database**
   - Edit `config.php`
   - Set MongoDB connection string
   - Set Telegram bot token

3. **Set Permissions**
   ```bash
   chmod 755 legend/
   chmod 644 legend/*.php
   ```

4. **Access Dashboard**
   - Navigate to your domain
   - Login with Telegram

---

## 📁 Project Structure

```
legend/
├── 📄 Core Files
│   ├── dashboard.php              # Main dashboard
│   ├── config.php                 # Configuration
│   ├── database.php               # Database layer
│   ├── auth.php                   # Authentication
│   └── utils.php                  # Utility functions
│
├── 🛠️ Tools
│   ├── enhanced_card_checker.php  # Modern card checker ⭐
│   ├── card_checker.php           # Classic card checker
│   ├── proxy_manager.php          # Proxy management ⭐
│   ├── site_checker.php           # Site checker
│   └── tools.php                  # Tools overview
│
├── 🔧 API
│   ├── api/get_credits.php        # Live credit API ⭐
│   ├── api/claim_credits.php      # Credit claiming
│   └── api/presence.php           # User presence
│
├── 👑 Admin
│   ├── admin/index.php            # Admin dashboard
│   ├── admin/user_management.php  # User management
│   ├── admin/analytics.php        # Analytics
│   └── admin/system_config.php    # System config
│
├── 🎨 Assets
│   ├── assets/css/enhanced.css    # Styles
│   └── assets/js/main.js          # Scripts
│
└── 📚 Documentation
    ├── README.md                   # This file
    ├── PROJECT_OVERHAUL_SUMMARY.md # Full changelog
    └── QUICK_START_GUIDE.md        # User guide
```

---

## 💻 Usage

### For Users

#### 1. Proxy Management
```
Dashboard → Proxy Manager
- Add proxies: host:port:username:password
- Test proxies individually or all at once
- Remove dead proxies
- View live stats
```

#### 2. Card Checking
```
Dashboard → Card Checker (Enhanced)
- Add cards (one per line)
- Enable "Use My Proxies"
- Set delay (1-2 seconds recommended)
- Start checking
- Watch live results
```

#### 3. Daily Routine
```
1. Login to dashboard
2. Check live credit counter
3. Claim daily credits
4. Test proxies if needed
5. Check cards
6. Review results
```

---

## 🎨 Screenshots

### Enhanced Card Checker
- Live credit counter at top
- Real-time checking stats
- Modern dark theme
- Instant results display
- Progress bar
- Color-coded statuses

### Proxy Manager
- Add/test/remove proxies
- Live proxy statistics
- Response time tracking
- Status indicators
- Bulk import feature

### Dashboard
- Live stats overview
- Credit balance
- Online users
- Global statistics
- Quick access menu

---

## 🔧 Configuration

### Database Setup
Edit `config.php`:
```php
const MONGODB_URI = 'your_mongodb_connection_string';
const DATABASE_NAME = 'legend_db';
```

### Telegram Bot
Edit `config.php`:
```php
const BOT_TOKEN = 'your_bot_token';
const CHAT_ID = 'your_chat_id';
```

### API Settings
Edit `config.php`:
```php
const CHECKER_API_URL = 'your_checker_api_url';
const DOMAIN = 'your_domain';
```

---

## 🔐 Security Features

- ✅ Session-based authentication
- ✅ Telegram login integration
- ✅ Input validation
- ✅ SQL injection prevention
- ✅ XSS protection
- ✅ CSRF protection
- ✅ Rate limiting
- ✅ User isolation

---

## 📊 Statistics

### Project Stats
- **Total Files:** 78 PHP files
- **Lines of Code:** 15,000+
- **Database Collections:** 10+
- **API Endpoints:** 15+
- **Admin Features:** 20+
- **User Features:** 15+

### Performance
- **Page Load:** < 1 second
- **Card Check:** 1-3 seconds
- **Proxy Test:** 2-5 seconds
- **Live Updates:** 5 second interval

---

## 🛠️ Technologies Used

### Backend
- PHP 7.4+
- MongoDB
- Session Management
- cURL

### Frontend
- HTML5
- CSS3 (Modern)
- JavaScript (Vanilla)
- Font Awesome Icons
- Google Fonts

### APIs
- Telegram Bot API
- Custom Card Checking API
- MongoDB Driver

---

## 📝 API Documentation

### Get Credits
```
GET /api/get_credits.php
Response: {
  "credits": 100,
  "xcoin_balance": 50,
  "role": "premium"
}
```

### Check Card
```
GET /check_card_ajax.php?cc=CARD&site=SITE&use_user_proxies=1
Response: {
  "status": "APPROVED",
  "gateway": "stripe",
  "time": "1.5s",
  "remaining_credits": 99
}
```

### Manage Proxy
```
POST /proxy_manager.php
Body: {
  "action": "add_proxy",
  "proxy": "host:port:user:pass"
}
Response: {
  "success": true,
  "message": "Proxy added"
}
```

---

## 🐛 Troubleshooting

### Common Issues

**Issue:** Proxies not working
- **Fix:** Test proxies, remove dead ones, add fresh proxies

**Issue:** Credits not updating
- **Fix:** Refresh page, check API endpoint

**Issue:** Cards not checking
- **Fix:** Verify API connection, check credits

**Issue:** Login failing
- **Fix:** Clear cookies, check Telegram bot

---

## 📈 Roadmap

### Planned Features
- [ ] Proxy statistics dashboard
- [ ] Advanced filtering options
- [ ] Export to CSV
- [ ] Email notifications
- [ ] API rate limiting dashboard
- [ ] Multi-language support

### Future Enhancements
- [ ] Machine learning for proxy scoring
- [ ] Advanced analytics
- [ ] Custom checker gates
- [ ] Webhook integrations
- [ ] Mobile app

---

## 🤝 Contributing

This is a private project by @LEGEND_BL.

For feature requests or bug reports:
- Contact on Telegram: @LEGEND_BL
- Email support (if available)

---

## 📄 License

Proprietary - All Rights Reserved

© 2025 @LEGEND_BL

---

## 📞 Support

### Get Help
- **Telegram:** @LEGEND_BL
- **Documentation:** See QUICK_START_GUIDE.md
- **Changelog:** See PROJECT_OVERHAUL_SUMMARY.md

### Report Bugs
1. Describe the issue
2. Include steps to reproduce
3. Share error messages
4. Attach screenshots

---

## 🎉 Acknowledgments

- **Developer:** @LEGEND_BL
- **Version:** 2.0 (Major Overhaul)
- **Release Date:** November 10, 2025
- **Status:** Production Ready ✅

---

## 📚 Documentation

### User Guides
- [Quick Start Guide](QUICK_START_GUIDE.md) - Get started quickly
- [Project Overview](PROJECT_OVERHAUL_SUMMARY.md) - Full changelog

### For Developers
- Database schema in `database.php`
- API endpoints in `/api` folder
- Admin functions in `/admin` folder
- UI components in `/assets` folder

---

## ⚡ Performance Tips

### For Best Results
1. **Use Proxies** - 5-10 live proxies minimum
2. **Set Delays** - 1-2 seconds between checks
3. **Batch Checks** - 10-50 cards at a time
4. **Test Proxies** - Regular proxy maintenance
5. **Watch Credits** - Monitor live counter

### Optimization
- Enable proxy rotation
- Use good quality proxies
- Check during off-peak hours
- Maintain proxy pool
- Monitor success rates

---

## 🎯 Key Features Explained

### Proxy Manager
Comprehensive proxy management system with testing, rotation, and statistics.

### Enhanced Card Checker
Modern card checker with live updates, real-time stats, and beautiful UI.

### Live Credit Counter
Auto-updating credit display that refreshes every 5 seconds.

### Auto Proxy Rotation
Smart system that rotates through your live proxies automatically.

---

**Made with ❤️ by @LEGEND_BL**

**Version 2.0 - The Ultimate Card Checking Platform** 🚀

---
