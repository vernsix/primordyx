-- =============================================================================
-- convert_charset_down.sql
-- =============================================================================
-- Migration: {{MIGRATION_NAME}}
-- Type: {{MIGRATION_TYPE}}
-- Generated: {{TIMESTAMP}}
-- Rollback: Convert charset for {{TABLE_NAME}} back to {{OLD_CHARSET}}

ALTER TABLE {{TABLE_NAME}}
    CONVERT TO CHARACTER SET {{OLD_CHARSET}} COLLATE {{OLD_COLLATION}};
