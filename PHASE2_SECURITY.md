# Phase 2 Security Implementation Plan

## Overview
This document outlines the comprehensive Phase 2 security hardening for Silent Auction Manager.

## Implementation Phases

### Phase 2a: Foundation & Core Security
1. **Audit Logging System** - Create audit_log table, logging infrastructure
2. **PII Encryption** - AES-256-CBC encryption for sensitive fields
3. **Server-Side Session Timeout** - Replace client-side timeout with server validation
4. **.env Configuration** - Add ENCRYPTION_KEY, audit settings

### Phase 2b: Rate Limiting & Input Validation  
1. **Rate Limiting** - Per-endpoint sliding window rate limits
2. **Payment Validation** - Amount, method, data integrity checks
3. **Enhanced XSS Prevention** - Replace innerHTML, add content security

### Phase 2c: Data Protection & Soft Delete
1. **Soft Delete Implementation** - Add deleted_at fields, 30-day recycle bin
2. **Debug Log Security** - Authentication required, log rotation
3. **Audit Trail Integration** - Link soft deletes to audit log

### Phase 2d: Operational Resilience
1. **Backup/Recovery System** - Daily automated backups, restore endpoint
2. **Database Migrations** - Schema updates for new features
3. **Monitoring & Health Checks** - Audit log analysis, recovery validation

## Files to Modify

### Backend
- `api.php` - Add security functions, audit logging, encryption, rate limiting
- `.env` - Add ENCRYPTION_KEY, audit log settings
- `.htaccess` - Block debug log, add rate limit headers

### Frontend
- `index.html` - Remove client-side session timeout, XSS fixes, auth checks

### Database
- Schema migrations for audit_log, soft delete timestamps

## Security Checklist

### Phase 2a
- [ ] Create audit_log table
- [ ] Implement logAudit() function
- [ ] Add encrypt/decrypt functions
- [ ] Add ENCRYPTION_KEY to .env
- [ ] Implement server-side session timeout
- [ ] Update session validation on all protected endpoints

### Phase 2b
- [ ] Implement rate limiting per endpoint
- [ ] Add payment method whitelist validation
- [ ] Add payment amount validation (positive, decimal places)
- [ ] Replace innerHTML with textContent for user data
- [ ] Add DOMPurify for rich content if needed

### Phase 2c
- [ ] Add deleted_at field to items, bidders, payments, etc.
- [ ] Update SELECT queries to filter deleted records
- [ ] Add authentication check for debug log access
- [ ] Implement debug log rotation (10MB, 30-day retention)
- [ ] Move debug log outside webroot or protect with .htaccess

### Phase 2d
- [ ] Create daily backup job
- [ ] Implement backup encryption
- [ ] Add restore_from_backup endpoint
- [ ] Log all backup/restore operations
- [ ] Document disaster recovery procedure

## Deployment Order

1. Update .env with new configuration values
2. Deploy updated api.php with security functions
3. Run database migrations for new tables/fields
4. Deploy updated index.html with XSS fixes
5. Deploy .htaccess changes
6. Enable audit logging and rate limiting
7. Setup cron job for daily backups
8. Test all endpoints with rate limiting active
9. Run regression test suite

## Backward Compatibility

- Unencrypted PII fields are detected and automatically encrypted on first write
- Soft delete only affects SELECT queries; existing DELETE operations still work
- Rate limiting returns 429 status (standard HTTP response code)
- New audit_log table doesn't affect existing data
- Session timeout uses PHP session variables; no client changes required

## Performance Considerations

- Encryption/decryption only on read/write of sensitive fields
- Rate limiting uses in-memory sliding window (session-based)
- Soft delete adds WHERE clause to SELECTs (minimal performance impact)
- Audit logging is asynchronous (non-blocking)
- Backup runs as separate cron job (doesn't affect normal operations)

## Security Standards

- AES-256-CBC encryption (NIST approved)
- Token bucket rate limiting algorithm
- 30-day soft delete grace period (GDPR compliant)
- 30-minute server session timeout (standard)
- Comprehensive audit trail for compliance
- Daily encrypted backups for disaster recovery

