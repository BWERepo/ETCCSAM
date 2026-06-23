# Phase 3 Security Fixes — Quick Start Guide

**13 MEDIUM Priority Security Fixes — All Implemented**

---

## What's New

### Server-Side (api.php & phase3-helpers.php)
- 6 new API validation endpoints
- Pagination support for large datasets
- 10 validation/monitoring functions
- Request timeout protection (30 sec)
- HTTP security headers

### Client-Side (index.html)
- Email scan rate limiting (5 min cooldown)
- Storage quota monitoring (80% threshold)
- Page visibility auto-logout (30 min)
- Validation helpers (payment, bidder, category)
- Duplicate email detection

### Testing (test.html)
- 73 new Phase 3 test cases
- 13 dedicated test suites
- All fixes covered by tests

---

## Using Phase 3 Features

### 1. Email Scan Rate Limiting
```js
// Automatically checked before scan
const rateCheck = await EmailScanRateLimit.canScan();
if (!rateCheck.allowed) {
  // Show countdown: "Please wait 120 seconds"
}
```

### 2. Storage Quota Monitoring
```js
// Automatically runs on app startup
await StorageMonitor.checkAndWarnIfNeeded();
// Shows warning if > 80% used: "Using 4.2MB of 5MB (84%)"
```

### 3. Payment Validation
```js
// Server-side validation
const validation = await PaymentValidator.validatePayment(
  payment,      // { bidder_number, method }
  itemNumber,   // "200-3"
  winningBid,   // 250.00
  itemValue,    // 100.00
  reserve       // 0
);
if (!validation.valid) {
  console.error(validation.bid_message);
}
```

### 4. Bidder Validation
```js
// Email format check (client)
const isValidEmail = BidderValidator.validateEmail('john@example.com');

// Phone format check (client)
const isValidPhone = BidderValidator.validatePhone('(865) 555-1234');

// Server validation
const result = await BidderValidator.validateBidderOnServer(bidder);
if (!result.valid) {
  result.errors.forEach(err => console.error(err));
}
```

### 5. Category Validation
```js
// Quick client-side check
const isValid = CategoryValidator.isValidCode(200);

// Server validation with category name
const result = await CategoryValidator.validateOnServer(200);
// Returns: { valid: true, category: "Corvette Items", message: "..." }
```

### 6. Duplicate Email Detection
```js
const duplicate = await DuplicateEmailChecker.checkForDuplicates(
  "Corvette Model Car",
  "John Smith",
  24 // hours window
);
if (duplicate.isDuplicate) {
  console.warn('Similar item found:', duplicate.similarEmail);
}
```

### 7. Page Visibility Logout
```js
// Automatically initialized on app startup
PageVisibilityManager.init();
// Sessions auto-logout after 30 minutes of page being hidden
```

---

## API Endpoints

### validate_payment
```json
POST /api.php
{
  "action": "validate_payment",
  "payment": { "bidder_number": 1, "method": "cash" },
  "item_number": "200-3",
  "winning_bid": 250.00,
  "item_value": 100.00,
  "reserve_amount": 0
}

Response:
{
  "bid_valid": true,
  "bid_message": "Valid bid",
  "payment_valid": true,
  "payment_message": "Valid payment",
  "valid": true
}
```

### validate_category
```json
POST /api.php
{
  "action": "validate_category",
  "category_code": 200
}

Response:
{
  "valid": true,
  "category": "Corvette Items",
  "message": "Valid category"
}
```

### validate_bidder
```json
POST /api.php
{
  "action": "validate_bidder",
  "bidder": {
    "first_name": "John",
    "last_name": "Smith",
    "email": "john@example.com",
    "phone": "(865) 555-1234"
  }
}

Response:
{
  "valid": true,
  "errors": []
}
```

### check_email_scan_cooldown
```json
POST /api.php
{
  "action": "check_email_scan_cooldown",
  "min_cooldown": 300
}

Response:
{
  "allowed": true,
  "secondsUntilNext": 0,
  "message": "Scan allowed"
}
```

### check_duplicate_email
```json
POST /api.php
{
  "action": "check_duplicate_email",
  "description": "Corvette Model Car",
  "donor_name": "John Smith",
  "hours_window": 24
}

Response:
{
  "isDuplicate": false,
  "similarEmail": null,
  "message": "No duplicates found"
}
```

### get_storage_report
```json
POST /api.php
{
  "action": "get_storage_report"
}

Response:
{
  "totalBytes": 3200000,
  "estimatedMB": 3.2,
  "quotaMB": 5,
  "percentageUsed": 64.0,
  "warning": false,
  "message": "Storage usage: 64.0%"
}
```

### get_all (with pagination)
```json
POST /api.php
{
  "action": "get_all",
  "page": 1,
  "limit": 100
}

Response:
{
  "data": [...items...],
  "page": 1,
  "limit": 100,
  "total": 523,
  "totalPages": 6,
  "hasMore": true
}
```

---

## Configuration

### Email Scan Cooldown
**File:** api.php (phase3-helpers.php)  
**Default:** 300 seconds (5 minutes)  
**Change:**
```php
$minCooldown = intval($input['min_cooldown'] ?? 300);
// Change 300 to desired seconds
```

### Storage Quota Warning
**File:** phase3-helpers.php  
**Default:** 80% of 5MB  
**Change:**
```php
$warningThreshold = 80; // Change percentage
$quotaBytes = 5 * 1024 * 1024; // Change quota
```

### Session Timeout
**File:** index.html  
**Default:** 1800 seconds (30 minutes)  
**Change:**
```js
PageVisibilityManager.sessionTimeoutSeconds = 1800;
// Change to desired seconds
```

### Request Timeout
**File:** api.php  
**Default:** 30 seconds  
**Change:**
```php
setSafeTimeout(30); // Change to desired seconds (max 300)
```

### Pagination Limit
**File:** phase3-helpers.php  
**Default:** 1000 max records per page  
**Automatic:** No change needed, enforced server-side

---

## Testing Phase 3 Features

### Run Full Test Suite
```
https://etccapps.com/apps/samtest/test.html
```

### Manual Testing Checklist

**Email Scan Rate Limiting**
- [ ] Click Scan button
- [ ] Button becomes disabled
- [ ] Countdown shows (e.g., "📧 Scan (120s)")
- [ ] After 5 minutes, button re-enables

**Storage Quota Monitoring**
- [ ] Fill localStorage near limit
- [ ] See warning: "⚠️ Storage Warning: X% full"
- [ ] Check Settings for storage report

**Payment Validation**
- [ ] Try bid < item value → Error
- [ ] Try bid > 300% of item value → Error
- [ ] Try invalid method → Error

**Bidder Validation**
- [ ] Try invalid email → Error
- [ ] Try phone with < 10 digits → Error
- [ ] Missing name fields → Error

**Category Validation**
- [ ] Try invalid code (e.g., 999) → Error
- [ ] Try valid codes (100-900) → Success

**Duplicate Email Detection**
- [ ] Submit same item twice → Warning shown
- [ ] Similar items within 24h → Warning shown

**Page Visibility Logout**
- [ ] Switch to another tab
- [ ] Wait 30+ minutes (or modify timeout)
- [ ] Switch back → Session expired message

**HTTP Security Headers**
- [ ] Browser DevTools → Network tab
- [ ] Check Response Headers
- [ ] Verify 6 new headers present

---

## Files Overview

### New Files
- **phase3-helpers.php** (180 lines)
  - 10 validation/monitoring functions
  - Server-side security helpers

### Modified Files
- **api.php** (+150 lines)
  - 6 new validation endpoints
  - Pagination support
  - Request timeout
  
- **index.html** (+200 lines)
  - Client-side security features
  - Validation helpers
  - Rate limiting
  
- **test.html** (+150 lines)
  - 73 new test cases
  - 13 test suites
  
- **.htaccess** (+15 lines)
  - 6 HTTP security headers
  - Protection for PHP helpers

---

## Error Messages

**Email Scan Cooldown:**
```
⏳ Email scan cooldown active. Please wait 120 seconds.
```

**Storage Warning:**
```
⚠️ Storage Warning: Using 4.2MB of 5MB (84% full)
```

**Payment Validation:**
```
Winning bid (250.00) must be at least the item value (100.00)
Winning bid (450.00) exceeds maximum (300% of 100.00 = 300.00)
Invalid payment method: invalid_method
```

**Bidder Validation:**
```
First name is required
Last name is required
Invalid email format
Phone must be 10 digits (US format)
```

**Category Validation:**
```
Invalid category code: 999. Valid codes: 100, 200, 300, 400, 500, 600, 700, 800, 900
```

**Duplicate Email:**
```
Similar item from same donor found recently
```

**Session Timeout:**
```
Your session expired due to inactivity
```

---

## Security Best Practices

### Passwords & Secrets
- ✅ No hardcoded credentials in code
- ✅ Use .env file for sensitive config
- ✅ Exclude from export backups
- ✅ HTTP-only for authentication tokens

### Data Validation
- ✅ Validate on client & server
- ✅ Whitelist allowed values
- ✅ Show clear error messages
- ✅ Log validation failures

### Session Security
- ✅ Auto-logout on inactivity
- ✅ Page visibility detection
- ✅ CSRF tokens on forms
- ✅ Session timeout enforcement

### HTTP Security
- ✅ HTTPS only (HSTS)
- ✅ X-Frame-Options to prevent clickjacking
- ✅ CSP to prevent XSS
- ✅ Referrer policy for privacy

### Data Protection
- ✅ Quota monitoring prevents loss
- ✅ Backups excluded from export
- ✅ Encryption at rest (ready)
- ✅ Audit logging enabled

---

## Troubleshooting

**Q: Email scan keeps saying "cooldown active"**  
A: Wait 5 minutes between scans. This protects Gmail API quota.

**Q: Storage warning won't go away**  
A: Delete old data or export archive. You're using >80% of quota.

**Q: Validation says email is invalid but looks correct**  
A: Check for spaces, special characters. Must match standard email format.

**Q: Payment validation fails but bid looks right**  
A: Make sure: 1) Bid ≥ item value, 2) Bid ≤ 300% of value, 3) Method valid.

**Q: Session timeout logout too aggressive**  
A: Can increase timeout in PageVisibilityManager (default 30 min).

**Q: HTTP headers not showing in browser**  
A: Reload page with Ctrl+Shift+R (hard refresh). Check HTTPS connection.

---

## Support

For questions or issues:
1. Check test.html for examples
2. Review PHASE3_IMPLEMENTATION.md for details
3. Look at console logs (F12 → Console)
4. Check audit logs in Settings

---

**Phase 3 Security Fixes — Production Ready** ✅
