# Admin Panel Complete Guide

## Overview
This guide covers all admin and owner commands available in the LEGEND CHECKER admin panel.

---

## 🔐 Access Levels

### Owner Access
- Full system control
- User management
- Role management
- System configuration
- Database backup
- Financial reports
- Payment configuration

### Admin Access
- User management (view, ban, unban)
- Credit management
- Broadcast messages
- View analytics
- Audit logs
- Support tickets

---

## 📊 Dashboard (analytics.php)

### Statistics Displayed
- **Total Users**: All registered users
- **Credits Claimed**: Total credits distributed
- **Tool Uses**: Total tool usage count
- **Online Users**: Currently active users

### Quick Actions
- Manage Users
- Credit Actions
- Tool Config
- View Logs
- System Config (Owner)
- Database Backup (Owner)

---

## 👥 User Management (user_management.php)

### Features
- **Search & Filter**: Find users by name, username, role, or status
- **Sorting**: Sort by join date, name, credits, or last login
- **Pagination**: Navigate through large user lists
- **Bulk Actions**: Perform actions on multiple users

### Individual User Actions
1. **View Details** (`view_user.php`)
   - See complete user profile
   - View activity history
   - Check statistics

2. **Change Role** (`user_role_actions.php`)
   - Update user role (free, premium, vip, admin, owner)
   - Requires owner access for admin/owner roles

3. **Adjust Credits** (`credit_actions.php`)
   - Add credits
   - Remove credits
   - Set credits (override)
   - Add reason for audit trail

4. **Ban/Unban** (`user_actions.php`)
   - Ban users (prevents login)
   - Unban users (restore access)
   - Logged in audit trail

### Bulk Actions
- Ban selected users
- Unban selected users
- Change role (free/premium/vip)
- Add/remove credits in bulk

### Usage
```
1. Search for users using filters
2. Select users with checkboxes
3. Click "Bulk Actions"
4. Choose action and execute
```

---

## 💰 Credit Management (credit_actions.php)

### Actions Available
1. **Add Credits**
   - Add specified amount to user balance
   - Logged with reason

2. **Remove Credits**
   - Remove specified amount
   - Cannot remove more than user has
   - Logged with reason

3. **Set Credits (Override)**
   - Set exact credit amount
   - Ignores current balance
   - Use with caution

### Quick Actions
- **+100**: Add 100 credits instantly
- **+500**: Add 500 credits instantly
- **+1000**: Add 1000 credits instantly
- **Reset to 0**: Clear all credits

### Audit Trail
All credit adjustments are logged with:
- Admin who made the change
- Timestamp
- Old balance
- New balance
- Reason provided

---

## 👑 Role Management (role_management.php)
**Owner Only**

### Available Roles
- **Free**: Basic access
- **Premium**: Enhanced features
- **VIP**: Premium + extras
- **Admin**: Administrative access
- **Owner**: Full system control

### Features
- View role distribution statistics
- Change user roles individually
- See all users with their current roles
- Audit trail for role changes

### Usage
```
1. Go to Role Management
2. Find user in list
3. Click "Change Role"
4. Select new role
5. Confirm change
```

---

## 📢 Broadcast System (broadcast.php)

### Target Audiences
- **All Users**: Every registered user
- **Online Users**: Currently active users
- **Free Users**: Only free tier
- **Premium Users**: Only premium tier
- **VIP Users**: Only VIP tier
- **Active Users**: Non-banned users
- **Banned Users**: Banned users only

### Message Formats
- **HTML**: Use `<b>`, `<i>`, `<code>` tags
- **Markdown**: Use `*bold*`, `_italic_`, `` `code` ``
- **Plain Text**: No formatting

### Templates
1. **Announcement**: General announcements
2. **Maintenance**: System maintenance notices
3. **Promotion**: Special offers
4. **Update**: Feature updates

### Usage
```
1. Select target audience
2. Choose message format
3. Type or use template
4. Review message
5. Click "Send Broadcast"
6. Confirm action
```

### Best Practices
- Test with small groups first
- Use appropriate formatting
- Keep messages concise
- Include clear call-to-action
- Monitor delivery stats

---

## 🔧 System Configuration (system_config.php)
**Owner Only**

### Settings
- Daily credit amount
- Tool costs
- Session timeout
- Max concurrent checks
- Bot configuration

### Usage
```
1. Navigate to System Config
2. Edit desired settings
3. Save changes
4. Settings apply immediately
```

---

## 🤖 Bot Configuration (bot_config.php)

### Features
- Telegram bot token
- Bot username
- Notification settings
- Webhook configuration

---

## 📝 Audit Log (audit_log.php)

### Tracked Actions
- User registrations
- Role changes
- Credit adjustments
- Ban/unban actions
- Broadcast messages
- System config changes

### Log Details
- Timestamp
- Admin user
- Action type
- Target user (if applicable)
- Additional data (JSON)

---

## 🎫 Support Tickets (support_tickets.php)

### Features
- View all support requests
- Respond to tickets
- Mark as resolved
- Filter by status

---

## 📊 Reports

### Financial Reports (financial_reports.php)
**Owner Only**
- Revenue statistics
- Payment history
- Credit purchases
- Transaction logs

### Analytics Reports
- User growth
- Tool usage statistics
- Credit distribution
- Active user metrics

---

## 🛠️ Utility Pages

### Premium Generator (premium_generator.php)
- Generate premium keys
- Set expiry dates
- Track key usage

### Credit Generator (credit_generator.php)
- Generate credit codes
- Set credit amount
- Track redemptions

### Claim System (claim_system.php)
- Manage daily credit claims
- Configure claim amounts
- View claim history

### Database Backup (database_backup.php)
**Owner Only**
- Create manual backups
- Schedule automatic backups
- Download backup files
- Restore from backup

---

## 🔑 Admin API (admin_api.php)

### Available Endpoints

#### User Management
```php
// Ban user
POST admin_api.php
action=ban_user&user_id=[ID]

// Unban user
POST admin_api.php
action=unban_user&user_id=[ID]

// Update role
POST admin_api.php
action=update_user_role&user_id=[ID]&role=[ROLE]
```

#### Credit Management
```php
// Add credits
POST admin_api.php
action=add_credits&user_id=[ID]&amount=[AMOUNT]

// Remove credits
POST admin_api.php
action=remove_credits&user_id=[ID]&amount=[AMOUNT]

// Set credits
POST admin_api.php
action=set_credits&user_id=[ID]&amount=[AMOUNT]
```

#### Bulk Actions
```php
// Bulk action
POST admin_api.php
action=bulk_action&user_ids[]=[ID1]&user_ids[]=[ID2]&bulk_action=[ACTION]
```

#### Information
```php
// Get user info
GET admin_api.php?action=get_user_info&user_id=[ID]

// Search users
GET admin_api.php?action=search_users&search=[QUERY]

// Get stats
GET admin_api.php?action=get_stats
```

#### Broadcast
```php
// Send broadcast
POST admin_api.php
action=broadcast_message&message=[MESSAGE]&target=[TARGET]
```

---

## 🚀 Quick Reference

### Common Tasks

#### Give User Premium Role
```
1. Go to Role Management
2. Find user
3. Click "Change Role"
4. Select "Premium"
5. Confirm
```

#### Add 1000 Credits to User
```
1. Go to User Management
2. Find user
3. Click "Adjust Credits"
4. Enter 1000
5. Select "Add Credits"
6. Add reason (optional)
7. Submit
```

#### Ban a User
```
1. Go to User Management
2. Find user
3. Click ban icon (slash-circle)
4. Confirm action
```

#### Send Announcement to All Users
```
1. Go to Broadcast
2. Select "All Users"
3. Choose "HTML" format
4. Type message or use template
5. Click "Send Broadcast"
6. Confirm
```

---

## 🔒 Security Features

### Audit Trail
- All admin actions are logged
- Includes timestamp and admin user
- Cannot be deleted by admins
- Owner notifications for critical actions

### Owner Notifications
Owners receive Telegram notifications for:
- User bans/unbans
- Role changes
- Credit adjustments
- Broadcast messages
- System configuration changes

### Access Control
- Session-based authentication
- Role-based permissions
- Owner-only pages protected
- CSRF token validation

---

## ⚠️ Important Notes

### Best Practices
1. Always provide reasons for credit adjustments
2. Test broadcasts on small groups first
3. Review user details before taking action
4. Check audit logs regularly
5. Keep system configuration backed up

### Warnings
- **Bulk actions cannot be undone** - Use with caution
- **Broadcasts send immediately** - Double-check before sending
- **Role changes take effect instantly** - No confirmation period
- **Database operations are permanent** - Always backup first

### Support
- Check error logs for issues
- Review audit log for unexpected changes
- Contact system administrator for critical issues
- Keep admin credentials secure

---

## 📞 Contact

For technical issues or questions about the admin panel:
- Check documentation first
- Review error logs
- Check audit trail
- Contact system owner

---

**Version**: 2.0  
**Last Updated**: 2025-11-11  
**Admin Panel**: LEGEND CHECKER
