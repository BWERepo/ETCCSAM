# Phase 2 Security - Quick Start Guide

## What Was Added?

Silent Auction Manager now has enterprise-grade security with:
- ✓ Comprehensive audit logging of all operations
- ✓ AES-256-CBC encryption for email/phone
- ✓ Rate limiting to prevent abuse
- ✓ Soft delete with 30-day recovery
- ✓ Server-side session timeout
- ✓ Automated daily backups
- ✓ Payment validation
- ✓ Debug log access control

## Files Added

1. **security-helpers.php** - All security functions
2. **run-migrations.php** - Database schema updates
3. **PHASE2_IMPLEMENTATION.md** - Complete documentation
4. **PHASE2_QUICK_START.md** - This file

## Files Modified

1. **.env** - Added security configuration
2. **api.php** - Integrated security throughout
3. **.htaccess** - Added access controls

## 30-Second Setup

```bash
# 1. Generate encryption key
ENCRYPTION_KEY=$(php -r 'echo bin2hex(random_bytes(32));')

# 2. Update .env
echo "ENCRYPTION_KEY=$ENCRYPTION_KEY" >> .env

# 3. Run migrations
php run-migrations.php

# Done!
```

## Usage Examples

### View Audit Log
```bash
curl -X POST https://etccapps.com/apps/sam/api.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "get_audit_log",
    "limit": 50,
    "filter": { "action": "save_winners" }
  }'
```

### Create Backup
```bash
curl -X POST https://etccapps.com/apps/sam/api.php \
  -H "Content-Type: application/json" \
  -d '{"action": "create_backup"}'
```

### View Debug Log
```bash
curl -X POST https://etccapps.com/apps/sam/api.php \
  -H "Content-Type: application/json" \
  -d '{"action": "get_debug_log", "limit": 100}'
```

## Key Configuration Values

Edit `.env`:

```
# Encryption (REQUIRED - generate with: php -r 'echo bin2hex(random_bytes(32));')
ENCRYPTION_KEY=your_generated_key_here

# Session timeout in seconds (default: 1800 = 30 minutes)
SESSION_TIMEOUT=1800

# Enable/disable security features
AUDIT_LOG_ENABLED=true
RATE_LIMITING_ENABLED=true

# Debug log settings
DEBUG_LOG_MAX_SIZE=10485760  # 10 MB
DEBUG_LOG_RETENTION_DAYS=30

# Soft delete settings
SOFT_DELETE_RETENTION_DAYS=30
```

## Rate Limits

| Action | Limit | Window |
|--------|-------|--------|
| scan_inbox | 1 | 5 min |
| set_password | 5 | 15 min |
| save_items | 100 | 1 min |
| save_bidders | 100 | 1 min |
| save_winners | 100 | 1 min |
| save_payments | 100 | 1 min |
| get_all_data | 10 | 1 min |
| clear_all | 1 | 1 hour |

## What Gets Encrypted?

When ENCRYPTION_KEY is set:
- Bidder emails
- Bidder phone numbers
- Donor emails
- Donor phone numbers

All other fields remain plaintext for performance.

## What Gets Logged?

Audit log records:
- All save operations (items, bidders, winners, payments)
- Who did it (user_id from session)
- When it happened (timestamp)
- What changed (old_value → new_value)
- IP address
- Success/failure status

## What Gets Soft Deleted?

Records with deleted_at timestamp:
- Items
- Bidders
- Winners
- Payments
- Members
- Registrations
- Emails

Deleted records stay in database for 30 days (configurable), then permanently purged.

## Testing

### Test Rate Limiting
```bash
for i in {1..6}; do
  curl -X POST https://etccapps.com/apps/sam/api.php \
    -H "Content-Type: application/json" \
    -d '{"action": "save_payments", "data": {}}'
done
# 5 succeed, 6th gets 429 Too Many Requests
```

### Test Encryption
```bash
# Add bidder with email
curl -X POST https://etccapps.com/apps/sam/api.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "save_bidders",
    "data": [{"bidder_number": 1, "email": "test@example.com"}]
  }'

# Check database
mysql> SELECT email FROM bidders WHERE bidder_number = 1;
# Returns: base64-encoded encrypted value (not plaintext)
```

### Test Audit Logging
```bash
# Save something
curl -X POST https://etccapps.com/apps/sam/api.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "save_winners",
    "data": {"200-1": {"bidder_number": 1, "winning_bid": "100.00"}}
  }'

# Check audit log
mysql> SELECT * FROM audit_log ORDER BY timestamp DESC LIMIT 1;
# Should show: action='save_winners', table_affected='winners', status='success'
```

## Troubleshooting

### "ENCRYPTION_KEY is not set"
Set `ENCRYPTION_KEY` in .env with: `php -r 'echo bin2hex(random_bytes(32));'`

### "Rate limit exceeded" too often
Increase limits in `getRateLimitConfig()` in security-helpers.php

### Audit log not recording
Check `AUDIT_LOG_ENABLED=true` in .env

### Backups directory not found
Run: `mkdir -p /path/to/sam/backups && chmod 700 /path/to/sam/backups`

## Daily Maintenance

### Monitor Audit Log Size
```bash
mysql -u user -p -e "SELECT COUNT(*) FROM audit_log;"
# If > 1M rows, consider archiving old entries
```

### Verify Daily Backup
```bash
ls -lh backups/
# Should see latest backup_YYYY-MM-DD_HH-mm-ss.sql.gz from today
```

### Check for Failed Operations
```bash
mysql -u user -p -e "SELECT action, COUNT(*) FROM audit_log WHERE status='failure' GROUP BY action;"
```

## Advanced Config

### Adjust Payment Validation Limits
In security-helpers.php, `validatePaymentAmount()`:
```php
// Change max amount from $10,000 to $50,000
if ($amt > 50000) { ... }
```

### Adjust Session Timeout
In .env:
```
SESSION_TIMEOUT=3600  # 1 hour instead of 30 minutes
```

### Adjust Soft Delete Retention
In .env:
```
SOFT_DELETE_RETENTION_DAYS=60  # Keep for 60 days instead of 30
```

## Security Checklist

Before going live:
- [ ] .env has strong ENCRYPTION_KEY
- [ ] .env has `SESSION_TIMEOUT` set (default: 1800)
- [ ] .env has `AUDIT_LOG_ENABLED=true`
- [ ] .env has `RATE_LIMITING_ENABLED=true`
- [ ] Ran `php run-migrations.php` successfully
- [ ] /backups directory created and writable
- [ ] .htaccess blocks direct access to debug_log.txt
- [ ] Tested encryption by checking database (email should be encrypted)
- [ ] Tested audit log by saving something and checking audit_log table
- [ ] Tested rate limiting by making multiple requests
- [ ] Setup cron for daily backups
- [ ] Verified backup creation

## What Changed for Users?

**Nothing visible!** All Phase 2 security is backend:
- Login/logout still works same
- Data saving still works same  
- No performance impact
- No UI changes

Only administrators see:
- Audit log in Settings (new feature)
- Debug log access control (now requires auth)
- Backup management (new feature)

## Support

For questions about Phase 2, see:
- **PHASE2_IMPLEMENTATION.md** - Complete technical docs
- **PHASE2_SECURITY.md** - Security architecture
- **api.php** - Actual implementation
- **security-helpers.php** - All security functions

