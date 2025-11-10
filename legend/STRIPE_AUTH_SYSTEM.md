# Stripe Auth Checker System - Documentation

## Overview
The Stripe Auth Checker is a professional tool for testing Stripe authentication on WooCommerce sites. It automatically creates accounts, adds payment methods, and validates cards using Stripe's payment gateway.

## Features

### ✨ Core Features
- **Automatic Site Rotation**: Sites rotate every 20 requests for optimal distribution
- **Credit System**: 1 credit = 1 check
- **Real-time Results**: Live/Dead card status with detailed responses
- **Telegram Notifications**: Get instant notifications for all checks
- **Owner Panel**: Full site management through admin interface
- **BIN Lookup**: Automatic card information retrieval (bank, type, country)
- **Proxy Support**: Optional proxy configuration for enhanced privacy

### 🔧 Technical Features
- **Three Python Scripts**:
  - `stripe_auth_checker.py` - Main checker logic
  - `bin_lookup.py` - BIN information lookup
  - `telegram_bot.py` - Telegram bot integration
  
- **Database Collections**:
  - `stripe_auth_sites` - Store and manage Stripe auth sites
  - `cc_logs` - Log all card check attempts
  
- **Site Rotation Logic**: 
  - Each site tracks request count
  - Automatically resets to 0 after 20 requests
  - Load balanced across all active sites

## Installation & Setup

### 1. Add Initial Sites
Run the initialization script (one-time only):
```
http://your-domain.com/legend/admin/add_initial_stripe_sites.php
```

This will add all 271 pre-configured Stripe Auth sites to your database.

### 2. Access Points

**For Users:**
- **Tool Page**: `/legend/stripe_auth_checker_tool.php`
- **Tools Menu**: Accessible from main tools page

**For Admins/Owners:**
- **Management Panel**: `/legend/admin/stripe_auth_sites.php`
- **Admin Menu**: Direct link in admin dashboard

### 3. Python Dependencies
Ensure Python 3 is installed with required packages:
```bash
pip install requests python-telegram-bot
```

## Usage

### For Regular Users

1. **Access Tool**: Navigate to Tools → Stripe Auth Checker
2. **Enter Cards**: Add cards in format: `cc|mm|yyyy|cvv`
   ```
   4111111111111111|12|2025|123
   5444224035733160|02|2029|832
   ```
3. **Optional Proxy**: Add proxy in format: `ip:port:user:pass`
4. **Start Checking**: Click "Start Checking" button
5. **View Results**: Real-time results with Live/Dead status

### For Owners/Admins

#### Add Single Site
1. Go to Admin → Stripe Auth Sites
2. Use "Add Single Site" form
3. Enter domain (without http://)
4. Click "Add Site"

#### Bulk Add Sites
1. Use "Bulk Add Sites" form
2. Add multiple domains (one per line)
3. Click "Bulk Add Sites"

#### Manage Sites
- **Activate/Deactivate**: Toggle site status
- **Remove**: Delete sites from rotation
- **View Stats**: See request counts and last used time

## Site Rotation Logic

The system implements intelligent site rotation:

1. **Request Counting**: Each check increments site request count
2. **Load Balancing**: Site with lowest count gets next request
3. **Auto Reset**: Count resets to 0 after 20 requests
4. **Fair Distribution**: Ensures even load across all sites

Example:
```
Site A: 5 requests → Next check
Site B: 10 requests
Site C: 18 requests
Site D: 0 requests → Highest priority
```

## Credit System

- **Cost**: 1 credit per card check
- **Deduction**: Automatic credit deduction on check
- **Balance**: Real-time balance display
- **Tracking**: All checks logged in database

## Telegram Integration

### Notification Format
```
🔔 *Stripe Auth Check*

👤 User: John Doe (@johndoe)
💳 Card: 411111****1111
🌐 Site: example.com
📊 Status: ✅ APPROVED / ❌ DECLINED
💬 Response: Payment method added successfully

🏦 *Card Info:*
Bank: Chase Bank
Type: Visa Credit
Country: United States 🇺🇸

⏱️ Response Time: 2.5s
```

## API Endpoints

### Check Card Endpoint
**URL**: `/legend/check_stripe_ajax.php`

**Method**: POST

**Parameters**:
- `card` (required): Card string in format `cc|mm|yyyy|cvv`
- `proxy` (optional): Proxy string in format `ip:port:user:pass`

**Response**:
```json
{
  "success": true,
  "status": "APPROVED",
  "message": "Payment method added successfully",
  "site": "example.com",
  "card": "4111111111111111|12|2025|123",
  "cardInfo": {
    "bank": "Chase Bank",
    "type": "Visa Credit",
    "country": "United States",
    "country_code": "US"
  },
  "response_time": 2.5,
  "credits_used": 1,
  "credits_remaining": 49
}
```

## Database Schema

### stripe_auth_sites Collection
```javascript
{
  "domain": "example.com",
  "active": true,
  "added_by": 5652614329,
  "request_count": 15,
  "last_used": ISODate("2025-01-10T12:00:00Z"),
  "created_at": ISODate("2025-01-01T00:00:00Z"),
  "updated_at": ISODate("2025-01-10T12:00:00Z")
}
```

### cc_logs Collection
```javascript
{
  "user_id": 5652614329,
  "card": "411111****1111",
  "gateway": "Stripe Auth",
  "site": "example.com",
  "status": "APPROVED",
  "message": "Payment method added successfully",
  "response_time": 2.5,
  "credits_used": 1,
  "timestamp": ISODate("2025-01-10T12:00:00Z")
}
```

## Pre-loaded Sites

The system comes with **271 verified Stripe Auth sites**, including:
- UK sites: `.co.uk` domains
- US sites: `.com`, `.us` domains  
- Canadian sites: `.ca` domains
- Australian sites: `.com.au` domains
- Global sites: Various TLDs

All sites are tested and working with Stripe WooCommerce integration.

## Troubleshooting

### Site Not Working?
1. Check if site is active in admin panel
2. Verify site still has Stripe integration
3. Test manually to confirm
4. Remove or deactivate if broken

### Low Success Rate?
1. Check proxy configuration
2. Verify card format is correct
3. Try different sites from rotation
4. Check if cards are expired

### Python Script Errors?
1. Verify Python 3 is installed
2. Check required packages are installed
3. Ensure scripts have execute permissions
4. Check error logs in admin panel

## Security Notes

- All cards are masked in logs (first 6 + last 4 digits only)
- Proxy support for enhanced privacy
- Credit tracking prevents abuse
- Owner-only site management
- Telegram notifications for monitoring

## Credits

- **Developer**: @amanpandey1212
- **System**: Legend Checker Pro
- **Python Integration**: Stripe Auth Checker + BIN Lookup
- **Total Sites**: 271 verified Stripe Auth sites

## Support

For support or questions:
- Telegram: @LEGEND_BL
- Admin Panel: Check audit logs
- System Logs: Check database logs

---

**Last Updated**: January 2025  
**Version**: 1.0.0  
**Status**: Production Ready ✅
