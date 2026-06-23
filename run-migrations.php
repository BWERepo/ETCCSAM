<?php
/**
 * Phase 2 Security Database Migrations
 * Run this script once to apply all Phase 2 schema changes
 *
 * Usage: php run-migrations.php
 */

require_once __DIR__ . '/api.php'; // Reuse database connection and environment loading

// This file is included after api.php loads environment and database, so we have $pdo and $env

error_log("Starting Phase 2 security migrations...");

try {
    // ═══════════════════════════════════════════════════════════════════════════
    // MIGRATION 1: Create audit_log table
    // ═══════════════════════════════════════════════════════════════════════════
    echo "Creating audit_log table...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS audit_log (
        audit_id INT AUTO_INCREMENT PRIMARY KEY,
        timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        user_id VARCHAR(255),
        action VARCHAR(100),
        table_affected VARCHAR(100),
        record_id VARCHAR(255),
        old_value LONGTEXT,
        new_value LONGTEXT,
        ip_address VARCHAR(45),
        status VARCHAR(20),
        details LONGTEXT,
        INDEX idx_timestamp (timestamp),
        INDEX idx_user_action (user_id, action),
        INDEX idx_record (table_affected, record_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "✓ audit_log table created\n";

    // ═══════════════════════════════════════════════════════════════════════════
    // MIGRATION 2: Add deleted_at columns to main tables
    // ═══════════════════════════════════════════════════════════════════════════
    echo "Adding deleted_at columns...\n";

    $tables = ['items', 'bidders', 'winners', 'payments', 'members', 'registrations', 'emails'];

    foreach ($tables as $table) {
        try {
            $pdo->exec("ALTER TABLE `$table` ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL");
            echo "✓ Added deleted_at to $table\n";
        } catch (Exception $e) {
            // Column might already exist, which is fine
            echo "~ Skipped $table (column may already exist)\n";
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // MIGRATION 3: Add indexes for soft delete queries
    // ═══════════════════════════════════════════════════════════════════════════
    echo "Creating soft delete indexes...\n";

    foreach ($tables as $table) {
        try {
            $pdo->exec("CREATE INDEX idx_deleted_at ON `$table` (deleted_at)");
            echo "✓ Created deleted_at index for $table\n";
        } catch (Exception $e) {
            echo "~ Skipped index for $table (may already exist)\n";
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // MIGRATION 4: Create backups directory
    // ═══════════════════════════════════════════════════════════════════════════
    echo "Creating backups directory...\n";
    $backupDir = __DIR__ . '/backups';
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0700, true);
        echo "✓ Backups directory created at $backupDir\n";
    } else {
        echo "~ Backups directory already exists\n";
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // MIGRATION 5: Verify encryption settings
    // ═══════════════════════════════════════════════════════════════════════════
    echo "Verifying encryption settings...\n";
    $encKey = $env['ENCRYPTION_KEY'] ?? '';
    if (empty($encKey) || $encKey === 'sam_encryption_key_2026_change_in_production') {
        echo "⚠ WARNING: ENCRYPTION_KEY is not properly set in .env\n";
        echo "  Please update .env with a strong ENCRYPTION_KEY\n";
        echo "  Example: ENCRYPTION_KEY=$(php -r 'echo bin2hex(random_bytes(32));')\n";
    } else {
        echo "✓ ENCRYPTION_KEY is set\n";
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // MIGRATION 6: Log initial audit entry
    // ═══════════════════════════════════════════════════════════════════════════
    echo "Logging migration completion...\n";
    $query = "INSERT INTO audit_log (user_id, action, table_affected, record_id, status, details)
              VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        'system',
        'phase2_migration',
        'system',
        'database',
        'success',
        'Phase 2 security migrations completed successfully'
    ]);
    echo "✓ Migration logged to audit_log\n";

    echo "\n✓ All Phase 2 migrations completed successfully!\n";
    echo "   Next steps:\n";
    echo "   1. Update ENCRYPTION_KEY in .env to a strong random value\n";
    echo "   2. Deploy updated api.php and security-helpers.php\n";
    echo "   3. Test all endpoints with rate limiting enabled\n";
    echo "   4. Setup cron job for daily backups\n";

} catch (Exception $e) {
    echo "ERROR: Migration failed - " . $e->getMessage() . "\n";
    error_log("Migration failed: " . $e->getMessage());
    exit(1);
}

exit(0);
