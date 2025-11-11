# Proxy Manager & Enhanced Stripe Auth Checker Guide

## Overview
This document explains the new Proxy Manager system and the enhanced Stripe Auth Checker with mass checking capabilities.

---

## 🌐 Proxy Manager

### Features

#### 1. **Add Proxies**
- **Single Proxy Addition**: Add one proxy at a time with instant verification
- **Mass Proxy Addition**: Add multiple proxies at once (one per line)
- **Auto-Verification**: All proxies are automatically checked before being added
- **Duplicate Detection**: System prevents adding duplicate proxies

#### 2. **Proxy Checking**
- **Manual Check**: Click "Check All Proxies" to verify all proxies
- **Automatic Daily Check**: Cron job checks all proxies daily
- **Live Status Tracking**: Real-time status updates (LIVE/DEAD)
- **Geo Information**: Shows IP, country, and city for each proxy

#### 3. **Proxy Management**
- **View All Proxies**: See complete list with status, IP, country, and last check time
- **Remove Dead Proxies**: One-click removal of all dead proxies
- **Individual Removal**: Remove specific proxies manually
- **Global Statistics**: View total, live, and dead proxy counts

#### 4. **Proxy Details**
For each proxy, the system tracks:
- Proxy address (host:port:user:pass)
- Public IP address
- Country and city location
- Status (LIVE/DEAD)
- Last check timestamp
- Total check count
- Date added

### How to Use Proxy Manager

#### Access
1. Login to your account
2. Go to **Tools** page
3. Click on **Proxy Manager** (Owner Only)

#### Add Single Proxy
1. Go to "Add Single" tab
2. Enter proxy in format: `host:port:username:password`
   - Example: `proxy.example.com:8080:user123:pass456`
3. Click "Add & Check Proxy"
4. System will verify the proxy before adding

#### Add Multiple Proxies
1. Go to "Add Mass" tab
2. Enter proxies one per line in format: `host:port:username:password`
   ```
   proxy1.example.com:8080:user1:pass1
   proxy2.example.com:8080:user2:pass2
   proxy3.example.com:8080:user3:pass3
   ```
3. Click "Add & Check All Proxies"
4. System will check each proxy and only add working ones
5. You'll see a summary: Added, Failed, Duplicate

#### Check All Proxies
1. Go to "Manage" tab
2. Click "Check All Proxies"
3. Wait for the check to complete
4. View updated status for all proxies

#### Remove Dead Proxies
1. Go to "Manage" tab
2. Click "Remove Dead Proxies"
3. Confirm the action
4. All dead proxies will be removed automatically

### Automatic Daily Checks

The system includes a cron job that automatically checks all proxies daily.

#### Setup Cron Job
Add this to your server's crontab:
```bash
# Check proxies daily at 2 AM
0 2 * * * /usr/bin/php /path/to/legend/cron_check_proxies.php
```

To add to crontab:
```bash
crontab -e
```

Then add the line above (replace `/path/to/legend/` with actual path).

#### What the Cron Job Does
- Checks all proxies
- Updates status (LIVE/DEAD)
- Updates geo information
- Automatically removes dead proxies (if enabled)
- Logs all activities

---

## 🔐 Enhanced Stripe Auth Checker

### New Features

#### 1. **Mass Checking**
- Check multiple cards at once
- Batch processing with progress tracking
- Automatic credit management
- Detailed results for each card

#### 2. **Proxy Integration**
- Use proxies for single checks
- Use proxies for mass checks
- Random proxy rotation
- Specific proxy selection

#### 3. **Improved UI**
- Tab-based interface (Single Check / Mass Check)
- Real-time progress indicator
- Success/failure statistics
- Better result display

### How to Use Stripe Auth Checker

#### Single Card Check

1. Go to **Tools** → **Stripe Auth Checker**
2. Select "Single Check" tab
3. Enter card in format: `CCNUMBER|MM|YYYY|CVV`
   - Example: `4532015112830366|12|2025|123`
4. Choose proxy option:
   - **No Proxy**: Check without proxy
   - **Random Proxy**: Use a random proxy from your pool
   - **Specific Proxy**: Select a specific proxy from dropdown
5. Click "Check Card"
6. View results

#### Mass Card Check

1. Go to **Tools** → **Stripe Auth Checker**
2. Select "Mass Check" tab
3. Enter cards one per line in format: `CCNUMBER|MM|YYYY|CVV`
   ```
   4532015112830366|12|2025|123
   4916338506082832|03|2026|456
   4024007114754230|08|2027|789
   ```
4. Choose proxy option:
   - **No Proxy**: Check all cards without proxy
   - **Random Proxy per Check**: Each card uses a different random proxy
5. Click "Check All Cards"
6. Watch progress bar and statistics
7. View detailed results for each card

### Cost Information
- **Single Check**: 1 credit per card
- **Mass Check**: 1 credit per card (e.g., 10 cards = 10 credits)
- Credits are deducted before checking
- Credits are refunded if check fails

### Proxy Benefits
- **Anonymity**: Hide your real IP address
- **Multiple IPs**: Different IP for each check
- **Rate Limiting**: Avoid IP-based rate limits
- **Geographic Diversity**: Use proxies from different countries

---

## 📊 Dashboard Features

### Proxy Statistics
On the Stripe Auth Checker page, you'll see:
- **Total Sites**: Number of available checking sites
- **Current Site**: Site rotation status
- **Available Proxies**: Number of live proxies

### Owner Controls
As an owner, you have access to:
- **Manage Sites**: Configure Stripe auth sites
- **Manage Proxies**: Access the Proxy Manager
- **System Configuration**: Advanced settings

---

## 🔧 Technical Details

### Proxy Format
Proxies must be in the format: `host:port:username:password`

**Examples:**
- `proxy.example.com:8080:user123:pass456`
- `192.168.1.100:3128:admin:secret`
- `vpn.myserver.net:1080:myuser:mypass`

### Proxy Verification
When adding a proxy, the system:
1. Validates the format
2. Tests connection to proxy
3. Makes a test request to httpbin.org
4. Retrieves geo information
5. Saves proxy if all checks pass

### Storage
- Proxies are stored in `/data/proxies.json`
- Automatically backed up on each update
- Includes metadata (status, country, last check, etc.)

### Security
- Only owners can manage proxies
- Proxy credentials are stored securely
- API endpoints are protected with authentication
- Rate limiting prevents abuse

---

## 🚀 Best Practices

### Proxy Management
1. **Regular Checks**: Run "Check All Proxies" regularly
2. **Remove Dead Proxies**: Keep your pool clean
3. **Add Fresh Proxies**: Maintain a healthy pool of 10+ proxies
4. **Monitor Statistics**: Watch live/dead ratio
5. **Use Cron Job**: Enable automatic daily checks

### Stripe Auth Checker
1. **Use Proxies**: Enable proxies for better success rates
2. **Random Rotation**: Use random proxy for mass checks
3. **Monitor Credits**: Check credit balance before mass checks
4. **Review Results**: Check all result details
5. **Report Issues**: Note any failing sites

### Credit Management
1. **Check Balance**: Ensure sufficient credits before mass checks
2. **Batch Wisely**: Don't check too many cards at once
3. **Monitor Usage**: Track your checking history
4. **Refund Policy**: Credits are refunded on errors

---

## 📝 Troubleshooting

### Proxy Issues

**Problem**: Proxy fails to add
- **Solution**: Verify proxy format (host:port:user:pass)
- **Solution**: Test proxy manually
- **Solution**: Check if proxy requires authentication

**Problem**: All proxies showing as dead
- **Solution**: Run "Check All Proxies" manually
- **Solution**: Verify proxy credentials
- **Solution**: Check if proxy service is down

**Problem**: Can't access Proxy Manager
- **Solution**: Ensure you're logged in as owner
- **Solution**: Check owner ID in config
- **Solution**: Clear browser cache

### Stripe Checker Issues

**Problem**: Check fails immediately
- **Solution**: Verify card format (CC|MM|YYYY|CVV)
- **Solution**: Check credit balance
- **Solution**: Try without proxy first

**Problem**: Mass check stops
- **Solution**: Check credit balance
- **Solution**: Review error messages
- **Solution**: Try smaller batches

**Problem**: No proxies available
- **Solution**: Add proxies in Proxy Manager
- **Solution**: Check if proxies are live
- **Solution**: Run proxy check

---

## 📞 Support

For issues or questions:
1. Check this guide first
2. Review error messages
3. Check system logs
4. Contact system administrator

---

## 🎯 Summary

### What's New
✅ **Proxy Manager** - Full proxy management system
✅ **Mass Checking** - Check multiple cards at once
✅ **Proxy Integration** - Use proxies with Stripe checker
✅ **Auto Verification** - Proxies verified before adding
✅ **Daily Checks** - Automatic proxy health monitoring
✅ **Better UI** - Improved interface and user experience
✅ **Statistics** - Detailed proxy and checking statistics
✅ **Progress Tracking** - Real-time progress for mass checks

### Key Features
- ✨ Add single or mass proxies
- ✨ Automatic proxy verification
- ✨ Live status tracking
- ✨ Geo location information
- ✨ Single and mass card checking
- ✨ Random proxy rotation
- ✨ Progress indicators
- ✨ Detailed result logs
- ✨ Owner-only access
- ✨ Daily automatic checks

---

**Version**: 1.0  
**Last Updated**: 2025-11-11  
**Prepared by**: LEGEND CHECKER System
