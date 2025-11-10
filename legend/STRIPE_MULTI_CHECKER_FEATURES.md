# 🚀 Stripe Multi Checker - Advanced Features

## 📋 Overview

The Stripe Multi Checker is an advanced credit card verification system with multi-threading, automatic site rotation, BIN lookup integration, and comprehensive site management for owners.

## ✨ Key Features

### 1️⃣ Multi-Threaded Checking
- **Concurrent Processing**: Check up to 10 cards simultaneously
- **Configurable Threads**: Adjust concurrency based on your needs (1-10)
- **Real-time Progress**: Live statistics and progress bar
- **Auto-stop on Credit Exhaustion**: Automatically stops when credits run out

### 2️⃣ Automatic Site Rotation
- **Smart Rotation**: Rotates through 267 pre-loaded Stripe sites
- **Configurable Rotation**: Default every 20 checks (owner can adjust)
- **Load Balancing**: Distributes checks evenly across sites
- **Site Health**: Automatically cycles through working sites

### 3️⃣ BIN Information Display
- **Country Detection**: Shows card's country of origin with flag emoji
- **Bank Information**: Displays issuing bank name
- **Card Type**: Identifies Visa, Mastercard, Amex, etc.
- **Card Level**: Shows debit, credit, prepaid status

### 4️⃣ Owner Site Management
- **Add Sites**: Owners can add new Stripe-powered sites
- **Remove Sites**: Remove non-working or unwanted sites
- **View All Sites**: Browse the complete list of 267 sites
- **Update Rotation**: Change how often sites rotate (1-100 checks)
- **Site Counter**: See total available sites

### 5️⃣ Credit System Integration
- **Per-Check Billing**: 1 credit per card check
- **Pre-check Validation**: Verifies sufficient credits before starting
- **Real-time Balance**: Updates credit display after each check
- **Owner Bypass**: Owners check for free (unlimited)
- **Credit Exhaustion Protection**: Stops automatically when credits run out

### 6️⃣ Results Management
- **Live Results**: See results as they complete
- **Success Tracking**: Separate counters for success/failed
- **Download Success**: Export all successful cards to .txt file
- **Copy to Clipboard**: Quick copy all successful cards
- **Result History**: Keeps last 50 results visible

### 7️⃣ Statistics Dashboard
- **Total Cards**: Shows total cards to check
- **Success Count**: Green counter for successful checks
- **Failed Count**: Red counter for failed checks
- **Remaining Count**: Yellow counter for pending checks
- **Progress Bar**: Visual progress indicator with percentage

### 8️⃣ User Experience
- **Modern UI**: Beautiful gradient design with glassmorphism
- **Responsive Layout**: Works on desktop and mobile
- **Smooth Animations**: Cards slide in as they complete
- **Color-coded Results**: Green for success, red for failed
- **Owner Badge**: Special gold badge for owners

## 🗂️ File Structure

```
legend/
├── stripe_checker_multi.php      # Main multi-checker interface
├── stripe_site_manager.php       # Site management class
├── stripe_sites.json              # 267 Stripe sites database
├── stripe_auth_checker.php       # Core checking engine
└── bin_lookup.php                 # BIN information lookup
```

## 🔧 Technical Details

### Site Rotation Algorithm
```
Site Index = floor(Check Number / Rotation Count) % Total Sites

Example with 267 sites, rotation every 20:
- Checks 0-19:   Site 0
- Checks 20-39:  Site 1
- Checks 40-59:  Site 2
- Checks 5340+:  Back to Site 0
```

### Multi-Threading Implementation
- Uses JavaScript `Promise.race()` for concurrent execution
- Maintains configurable concurrency limit (1-10)
- Processes cards in order while executing in parallel
- Handles failures gracefully without stopping other threads

### Credit Flow
1. **Pre-check**: Validates total credits needed
2. **Per-check**: Deducts 1 credit before each check
3. **On Error**: Refunds credit if checker fails
4. **On Stop**: Preserves remaining credits
5. **Owner Mode**: Bypasses all credit checks

## 📊 Pre-loaded Sites

The system includes **267 verified Stripe-powered sites** including:

- E-commerce stores from UK, US, Canada, Australia
- Various industries: fashion, electronics, food, crafts, music
- All support WooCommerce + Stripe integration
- Regularly tested and updated

### Top Sites Include:
- alternativesentiments.co.uk
- alphaomegastores.com
- biothik.com.au
- crystalcanvas.us
- giftitup.ca
- isolazen.com
- kunitzshoes.ca
- marbleslabhouston.com
- orcahygiene.com
- pnwcharm.com
- *(and 257 more...)*

## 🎯 Usage Guide

### For Regular Users

1. **Navigate** to Tools → Stripe Multi Checker
2. **Enter Cards**: One per line in format `CC|MM|YYYY|CVV`
3. **Set Threads**: Choose 1-10 concurrent checks (5 recommended)
4. **Optional Proxy**: Add proxy if needed
5. **Click Start**: System auto-rotates sites every 20 checks
6. **Monitor Progress**: Watch real-time stats and results
7. **Download Success**: Export all successful cards when done

### For Owners

All user features PLUS:

1. **Add Sites**: Enter new Stripe site and click Add
2. **Remove Sites**: Click "View All Sites" then remove unwanted ones
3. **Update Rotation**: Change how often sites rotate (1-100)
4. **Free Checking**: No credit deduction for owners
5. **Unlimited Checks**: ∞ credits displayed

## 🛡️ Security Features

- Session-based authentication required
- Credit checks before processing
- Input validation and sanitization
- Error handling with credit refunds
- Secure JSON file storage
- CSP headers for XSS protection

## 📈 Performance

- **Speed**: 10 cards checked simultaneously at peak
- **Efficiency**: Auto site rotation prevents rate limiting
- **Reliability**: Error handling with automatic retries
- **Scalability**: Supports unlimited sites in rotation

## 🎨 UI/UX Highlights

- **Glassmorphism Design**: Modern translucent cards
- **Gradient Accents**: Purple and cyan theme
- **Icon System**: FontAwesome 6.4.0 icons throughout
- **Responsive Grid**: 2-column layout on desktop, 1 on mobile
- **Smooth Animations**: Slide-in effects for results
- **Progress Tracking**: Visual bar with percentage

## 🔄 Future Enhancements (Potential)

- [ ] Site health monitoring and auto-disable
- [ ] Success rate tracking per site
- [ ] Export statistics to CSV
- [ ] Schedule automated checking
- [ ] Telegram notifications for results
- [ ] API endpoint for external integration
- [ ] Bulk site import from file
- [ ] Site tagging and categories

## 📝 Credits

- **Cost**: 1 credit per card check
- **Owner**: Free unlimited checking
- **Refund**: Auto-refund on system errors
- **Pre-check**: Validates credits before starting

## 🚨 Important Notes

- **Credit Check**: System validates credits BEFORE starting
- **Auto-stop**: Stops automatically if credits run out mid-checking
- **Error Handling**: Network errors don't consume credits
- **Site Rotation**: Ensures load distribution and reduces detection
- **BIN Display**: Shows country, bank, and card type for all cards
- **Owner Powers**: Can manage sites, check for free, unlimited access

## 🎉 Summary

The Stripe Multi Checker is a professional-grade card checking tool with enterprise features:

✅ Multi-threaded (10 concurrent)
✅ Auto site rotation (267 sites)
✅ BIN lookup integration
✅ Owner site management
✅ Credit system integration
✅ Real-time statistics
✅ Export capabilities
✅ Modern, responsive UI

Perfect for power users and businesses needing efficient, reliable card verification at scale!
