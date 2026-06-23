# Phase 2 Security - Deployment Checklist

**Project:** Silent Auction Manager
**Phase:** Phase 2 Security Hardening
**Date:** 2026-06-23

---

## Pre-Deployment Phase

### Environment Verification
- [ ] Production server is accessible via SSH/SFTP
- [ ] Database credentials are correct and tested
- [ ] Backup of current database exists (`mysqldump -u user -ppass dbname > backup_pre_phase2.sql`)
- [ ] Current .env file backed up
- [ ] Current api.php backed up
- [ ] Current .htaccess backed up
- [ ] At least 50MB free disk space available
- [ ] PHP version >= 7.0 (verify with `php -v`)
- [ ] OpenSSL extension enabled (`php -m | grep openssl`)

### Team Coordination
- [ ] Deployment time scheduled (preferably off-peak)
- [ ] Team member assigned to monitor after deployment
- [ ] Rollback plan reviewed by team
- [ ] Communication plan in place (notify users if needed)

---

## File Deployment Phase

### Step 1: Deploy Security Helpers
- [ ] Upload `security-helpers.php` to production `/apps/sam/`
- [ ] Verify file uploaded correctly
- [ ] Check file permissions: `644` (readable by web server)
- [ ] Verify no corruption: File size should be ~654 lines

### Step 2: Update Configuration (.env)
- [ ] Generate new ENCRYPTION_KEY:
  ```bash
  php -r 'echo "ENCRYPTION_KEY=" . bin2hex(random_bytes(32));'
  ```
- [ ] Copy output to clipboard
- [ ] Update `.env` file with new ENCRYPTION_KEY
- [ ] Verify all new settings in .env:
  ```
  ENCRYPTION_KEY=<new-key>
  SESSION_TIMEOUT=1800
  AUDIT_LOG_ENABLED=true
  RATE_LIMITING_ENABLED=true
  DEBUG_LOG_MAX_SIZE=10485760
  DEBUG_LOG_RETENTION_DAYS=30
  SOFT_DELETE_RETENTION_DAYS=30
  ```
- [ ] Upload updated `.env` to production
- [ ] Verify `.env` file is NOT web-accessible (should be in .gitignore)

### Step 3: Deploy Updated API
- [ ] Upload `api.php` to production `/apps/sam/`
- [ ] Verify file uploaded correctly
- [ ] Check file size is ~1372 lines (significantly larger)
- [ ] Check file permissions: `644`

### Step 4: Deploy Updated .htaccess
- [ ] Upload updated `.htaccess` to production `/apps/sam/`
- [ ] Verify rules are present:
  - `FilesMatch "^debug_log"` - blocks debug log
  - `DirectoryMatch "^/.*backups"` - blocks backups
  - `FilesMatch "security-helpers"` - blocks helper file
  - `Strict-Transport-Security` - HSTS header
- [ ] Check file permissions: `644`

### Step 5: Create Backups Directory
- [ ] Create `/apps/sam/backups/` directory
  ```bash
  mkdir -p /var/www/etccapps.com/apps/sam/backups
  chmod 700 /var/www/etccapps.com/apps/sam/backups
  ```
- [ ] Verify directory is not web-accessible
- [ ] Verify directory is writable by web server

### Step 6: Deploy Migration Script
- [ ] Upload `run-migrations.php` to production `/apps/sam/`
- [ ] Check file permissions: `644`

### Step 7: Upload Documentation
- [ ] Upload `PHASE2_IMPLEMENTATION.md`
- [ ] Upload `PHASE2_QUICK_START.md`
- [ ] Upload `PHASE2_SUMMARY.md`
- [ ] These can be web-accessible (non-sensitive)

---

## Database Migration Phase

### Pre-Migration Validation
- [ ] Database connection tested
- [ ] Database has >= 50MB free space
- [ ] No other processes accessing database

### Run Migrations
- [ ] SSH into production server
- [ ] Navigate to `/apps/sam/` directory
- [ ] Run migrations:
  ```bash
  php run-migrations.php
  ```
- [ ] Verify output shows success:
  ```
  ✓ audit_log table created
  ✓ Added deleted_at to items
  ✓ All Phase 2 migrations completed successfully!
  ```

### Post-Migration Validation
- [ ] Check audit_log table exists:
  ```sql
  mysql> SELECT COUNT(*) FROM audit_log;
  ```
- [ ] Check deleted_at column added to items:
  ```sql
  mysql> DESCRIBE items;  -- Should show deleted_at column
  ```
- [ ] Check indexes created:
  ```sql
  mysql> SHOW INDEXES FROM items;  -- Should show idx_deleted_at
  ```
- [ ] Verify migration logged:
  ```sql
  mysql> SELECT * FROM audit_log WHERE action='phase2_migration';
  ```

---

## Security Verification Phase

### Test Encryption
- [ ] Send test request to save a bidder:
  ```bash
  curl -X POST https://etccapps.com/apps/sam/api.php \
    -H "Content-Type: application/json" \
    -d '{
      "action": "save_bidders",
      "data": [{
        "bidder_number": 999,
        "first_name": "Test",
        "last_name": "User",
        "email": "test@example.com",
        "phone": "(555) 123-4567"
      }]
    }'
  ```
- [ ] Check database for encryption:
  ```sql
  mysql> SELECT bidder_number, email, phone FROM bidders WHERE bidder_number=999;
  ```
  - Email should be base64-encoded encrypted value (~100+ chars)
  - Phone should be base64-encoded encrypted value (~100+ chars)
  - Should NOT be plaintext "test@example.com"
- [ ] Delete test record:
  ```sql
  mysql> DELETE FROM bidders WHERE bidder_number=999;
  ```

### Test Audit Logging
- [ ] Send test request to save items:
  ```bash
  curl -X POST https://etccapps.com/apps/sam/api.php \
    -H "Content-Type: application/json" \
    -d '{
      "action": "save_items",
      "data": [{
        "item_number": "999-1",
        "item_category": "Test",
        "description": "Phase 2 test item"
      }]
    }'
  ```
- [ ] Check audit_log:
  ```sql
  mysql> SELECT * FROM audit_log WHERE action='save_items' ORDER BY timestamp DESC LIMIT 1;
  ```
- [ ] Verify audit entry has:
  - `action = 'save_items'`
  - `table_affected = 'items'`
  - `record_id = '999-1'`
  - `status = 'success'`
  - `ip_address` populated
  - `timestamp` is recent
- [ ] Verify audit log API works:
  ```bash
  curl -X POST https://etccapps.com/apps/sam/api.php \
    -H "Content-Type: application/json" \
    -d '{"action": "get_audit_log", "limit": 5}'
  ```
  (Should return recent audit entries)

### Test Rate Limiting
- [ ] Send rapid save requests (> 100 in 1 minute):
  ```bash
  for i in {1..6}; do
    curl -X POST https://etccapps.com/apps/sam/api.php \
      -H "Content-Type: application/json" \
      -d '{"action": "save_payments", "data": {}}' \
      -H "Cookie: PHPSESSID=$SESSIONID"
  done
  ```
- [ ] Verify 6th request returns 429:
  ```json
  {"error": "Rate limit exceeded", "retry_after": 45}
  ```
- [ ] Verify HTTP headers include:
  ```
  HTTP/1.1 429 Too Many Requests
  Retry-After: 45
  ```

### Test Session Timeout
- [ ] Login to application
- [ ] Note session ID
- [ ] Wait 31+ minutes
- [ ] Send API request with same session
- [ ] Verify returns 401 Unauthorized:
  ```json
  {"error": "Session expired due to inactivity"}
  ```

### Test Debug Log Protection
- [ ] Try direct access to debug log (should fail):
  ```bash
  curl https://etccapps.com/apps/sam/debug_log.txt
  # Should return 403 Forbidden
  ```
- [ ] Try access via API without auth (should fail):
  ```bash
  curl -X POST https://etccapps.com/apps/sam/api.php \
    -H "Content-Type: application/json" \
    -d '{"action": "get_debug_log"}'
  # Should return 401 Unauthorized
  ```
- [ ] Try access via API with auth (should succeed):
  ```bash
  curl -X POST https://etccapps.com/apps/sam/api.php \
    -H "Content-Type: application/json" \
    -H "Cookie: PHPSESSID=$SESSIONID" \
    -d '{"action": "get_debug_log", "limit": 10}'
  # Should return array of log lines
  ```

### Test Backup Creation
- [ ] Create backup:
  ```bash
  curl -X POST https://etccapps.com/apps/sam/api.php \
    -H "Content-Type: application/json" \
    -H "Cookie: PHPSESSID=$SESSIONID" \
    -d '{"action": "create_backup"}'
  ```
- [ ] Verify response indicates success
- [ ] Check /backups directory:
  ```bash
  ls -lh /var/www/etccapps.com/apps/sam/backups/
  # Should show backup_YYYY-MM-DD_HH-mm-ss.sql.gz file
  ```
- [ ] Verify backup is readable/not corrupted:
  ```bash
  gzip -t /var/www/etccapps.com/apps/sam/backups/backup_*.sql.gz
  # Should return without error
  ```

### Test Soft Delete
- [ ] Add test bidder (number 888)
- [ ] Verify it exists:
  ```sql
  mysql> SELECT COUNT(*) FROM bidders WHERE bidder_number=888 AND deleted_at IS NULL;
  # Should return 1
  ```
- [ ] Soft delete it (update directly for testing):
  ```sql
  mysql> UPDATE bidders SET deleted_at=NOW() WHERE bidder_number=888;
  ```
- [ ] Verify it's hidden from SELECT:
  ```sql
  mysql> SELECT COUNT(*) FROM bidders WHERE bidder_number=888 AND deleted_at IS NULL;
  # Should return 0
  ```
- [ ] Verify raw data still exists:
  ```sql
  mysql> SELECT bidder_number, deleted_at FROM bidders WHERE bidder_number=888;
  # Should show deleted_at populated
  ```
- [ ] Restore it:
  ```sql
  mysql> UPDATE bidders SET deleted_at=NULL WHERE bidder_number=888;
  ```
- [ ] Verify it's visible again:
  ```sql
  mysql> SELECT COUNT(*) FROM bidders WHERE bidder_number=888 AND deleted_at IS NULL;
  # Should return 1
  ```

### Test Payment Validation
- [ ] Send save_payments with invalid amount:
  ```bash
  curl -X POST https://etccapps.com/apps/sam/api.php \
    -H "Content-Type: application/json" \
    -d '{
      "action": "save_payments",
      "data": {
        "1": {"method": "Cash", "paid": -100}
      }
    }'
  ```
- [ ] Verify returns validation error
- [ ] Check audit_log shows failure
- [ ] Send with invalid method:
  ```bash
  curl -X POST https://etccapps.com/apps/sam/api.php \
    -H "Content-Type: application/json" \
    -d '{
      "action": "save_payments",
      "data": {
        "1": {"method": "Bitcoin", "paid": 100}
      }
    }'
  ```
- [ ] Verify returns validation error

---

## Application Testing Phase

### Core Functionality
- [ ] Login to SAM (verify authentication works)
- [ ] Navigate to Home screen (all features visible)
- [ ] Load Item Emails (scan, parse, save items)
- [ ] Create Bid Sheets (generate PDFs, print)
- [ ] Register Bidders (CRUD operations on bidders)
- [ ] Record Winning Bidders (save winners)
- [ ] Pay & Pickup (save payments)
- [ ] View reports (all screens functional)
- [ ] Logout and re-login (session works)

### New Features
- [ ] Settings → Developer Tools → View Audit Log (if added to UI)
- [ ] Settings → Developer Tools → View Backup (if added to UI)
- [ ] Verify no visible UI changes (all Phase 2 is backend)

### Performance
- [ ] Monitor response times (should be same or faster)
- [ ] Check browser console (no errors)
- [ ] Verify database queries still fast
- [ ] Monitor server CPU/memory (should be normal)

---

## Monitoring & Post-Deployment Phase

### Setup Monitoring
- [ ] Add monitoring for audit_log growth:
  ```bash
  # Add to monitoring system
  SELECT COUNT(*) FROM audit_log;
  # Alert if > 1,000,000 rows
  ```
- [ ] Add monitoring for backup creation:
  ```bash
  # Check backup file exists and is recent
  ls -l /var/www/etccapps.com/apps/sam/backups/backup_*.sql.gz | tail -1
  # Alert if file older than 25 hours
  ```
- [ ] Add monitoring for rate limiting hits:
  ```bash
  # Monitor for 429 errors in access.log
  grep "429" /var/log/apache2/access.log
  # Alert if > 100 per hour
  ```

### Setup Cron Jobs
- [ ] Add daily backup job (2 AM):
  ```bash
  # Edit crontab
  crontab -e
  
  # Add line:
  0 2 * * * cd /var/www/etccapps.com/apps/sam && php -r "
  session_start();
  \$_SESSION['authenticated'] = true;
  require_once 'api.php';
  \$backup = createDatabaseBackup(\$pdo, __DIR__ . '/backups', \$env['DB_NAME']);
  error_log('[BACKUP] ' . (\$backup['success'] ? 'SUCCESS' : 'FAILED') . ' - ' . (\$backup['file'] ?? \$backup['error']));
  " 2>&1 >> /var/log/sam_backup.log
  ```
- [ ] Add weekly audit log archive job:
  ```bash
  # Every Sunday at 3 AM
  0 3 * * 0 mysql -u user -ppass dbname -e "
  INSERT INTO audit_log_archive SELECT * FROM audit_log WHERE timestamp < DATE_SUB(NOW(), INTERVAL 90 DAY);
  DELETE FROM audit_log WHERE timestamp < DATE_SUB(NOW(), INTERVAL 90 DAY);
  " 2>&1 >> /var/log/sam_archive.log
  ```
- [ ] Add cleanup job for old backups:
  ```bash
  # Every Monday at 4 AM
  0 4 * * 1 find /var/www/etccapps.com/apps/sam/backups -name "backup_*.sql.gz" -mtime +30 -delete
  ```
- [ ] Verify cron jobs are active:
  ```bash
  crontab -l | grep sam
  ```

### Monitor Logs
- [ ] Check error_log for Phase 2 issues:
  ```bash
  tail -f /var/log/apache2/error.log | grep -i "phase2\|encryption\|audit\|rate"
  ```
- [ ] Check access.log for 429 errors:
  ```bash
  tail -f /var/log/apache2/access.log | grep "429"
  ```
- [ ] Check application logs for issues:
  ```bash
  tail -f /var/www/etccapps.com/apps/sam/debug_log.txt
  ```

### Verify Data Integrity
- [ ] Run daily check:
  ```sql
  -- Check database consistency
  SELECT COUNT(*) FROM items WHERE deleted_at IS NOT NULL;
  SELECT COUNT(*) FROM bidders WHERE deleted_at IS NOT NULL;
  SELECT COUNT(*) FROM audit_log WHERE status='failure';
  SELECT COUNT(*) FROM audit_log WHERE status='success';
  ```
- [ ] Verify encryption working:
  ```sql
  -- Sample encrypted data
  SELECT bidder_number, email, phone FROM bidders LIMIT 1;
  # Should see encrypted values if encryption enabled
  ```

### Document Deployment
- [ ] Record deployment time: _______________
- [ ] Record deployed version: Phase 2.0
- [ ] Record encryption key location: (in .env, production only)
- [ ] Record backup location: /var/www/etccapps.com/apps/sam/backups/
- [ ] Record any issues encountered: _______________
- [ ] Record who approved deployment: _______________
- [ ] Sign off deployment: _______________

---

## Post-Deployment Follow-up

### Daily (First Week)
- [ ] Check backup creation (verify daily)
- [ ] Review audit log for errors
- [ ] Monitor rate limiting hits
- [ ] Check server performance/load
- [ ] Verify no user complaints

### Weekly (First Month)
- [ ] Review audit_log for suspicious patterns
- [ ] Check soft delete records aging correctly
- [ ] Verify encryption consistency
- [ ] Test restore from backup
- [ ] Review security metrics

### Monthly
- [ ] Archive old audit logs
- [ ] Review failed operations
- [ ] Check backup completeness
- [ ] Rotate encryption key (if policy requires)
- [ ] Security audit of new features

---

## Rollback Plan (If Needed)

If critical issues occur:

1. **Restore Previous State:**
   ```bash
   # Stop application
   cp /path/to/backup/api.php /var/www/etccapps.com/apps/sam/api.php
   cp /path/to/backup/.htaccess /var/www/etccapps.com/apps/sam/.htaccess
   cp /path/to/backup/.env /var/www/etccapps.com/apps/sam/.env
   rm /var/www/etccapps.com/apps/sam/security-helpers.php
   ```

2. **Restore Database (if needed):**
   ```bash
   # Only restore if corruption detected
   mysql -u user -p dbname < backup_pre_phase2.sql
   ```

3. **Verify Application:**
   - Test login
   - Test save operations
   - Verify no errors

**Note:** Soft delete and audit_log changes are non-breaking and can remain.

---

## Sign-Off

- **Deployment Manager:** _________________ Date: _________
- **Technical Lead:** _________________ Date: _________
- **System Administrator:** _________________ Date: _________
- **Project Owner:** _________________ Date: _________

---

## Contact Information

For deployment issues:
1. Review logs and audit_log table
2. Check PHASE2_IMPLEMENTATION.md for solutions
3. Contact technical lead
4. Have backup ready for rollback if needed

