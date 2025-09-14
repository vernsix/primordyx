-- =============================================================================
-- archive_data_down.sql
-- =============================================================================
-- Migration: {{MIGRATION_NAME}}
-- Type: {{MIGRATION_TYPE}}
-- Generated: {{TIMESTAMP}}
-- Rollback: Restore archived data to {{TABLE_NAME}}

-- Restore data from archive
INSERT INTO {{TABLE_NAME}}
SELECT * FROM {{ARCHIVE_TABLE}}
WHERE {{RESTORE_CONDITION}};

-- Remove restored data from archive (optional)
-- DELETE FROM {{ARCHIVE_TABLE}} WHERE {{RESTORE_CONDITION}};
