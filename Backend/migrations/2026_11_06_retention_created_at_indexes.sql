-- Optional migration for efficient chronological retention deletion.
-- Apply at a maintenance window: adding indexes to a large existing
-- history table can consume disk and take a long time.
-- Run with a privileged schema-migration account, not the web application.
SET @key_index = (
    SELECT IF(COUNT(*)>0, 'SELECT 1',
        'ALTER TABLE key_logs ADD INDEX idx_key_logs_created_at_id (created_at,id)')
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='key_logs'
      AND INDEX_NAME='idx_key_logs_created_at_id'
);
PREPARE monitor_stmt FROM @key_index;
EXECUTE monitor_stmt;
DEALLOCATE PREPARE monitor_stmt;

SET @usb_index = (
    SELECT IF(COUNT(*)>0, 'SELECT 1',
        'ALTER TABLE usb_device_logs ADD INDEX idx_usb_logs_created_at_id (created_at,id)')
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='usb_device_logs'
      AND INDEX_NAME='idx_usb_logs_created_at_id'
);
PREPARE monitor_stmt FROM @usb_index;
EXECUTE monitor_stmt;
DEALLOCATE PREPARE monitor_stmt;

-- browser_history_logs already has idx_created_at in database_setup.sql.
